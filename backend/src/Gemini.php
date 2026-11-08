<?php
declare(strict_types=1);

function ai_normalize_name(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    // Separate joined dosage-form/count tokens (for example "tabs40") before
    // removing dosage-form words so the product can still match its catalog name.
    $value = preg_replace('/(?<=\pL)(?=\d)|(?<=\d)(?=\pL)/u', ' ', $value) ?? $value;
    $value = preg_replace('/\b(syp|syr)\b/u', 'syrup', $value) ?? $value;
    $value = preg_replace('/\b(inj)\b/u', 'injection', $value) ?? $value;
    $value = preg_replace('/\b(susp)\b/u', 'suspension', $value) ?? $value;
    $value = preg_replace('/\b(crm)\b/u', 'cream', $value) ?? $value;
    $value = preg_replace('/\b(oint)\b/u', 'ointment', $value) ?? $value;
    $value = preg_replace('/\b(tab|tabs|tablet|tablets|cap|caps|capsule|capsules)\b/u', ' ', $value) ?? $value;
    return trim(preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value);
}

function ai_extract_strength(string $value): string
{
    if (preg_match('/\b(\d+(?:\.\d+)?)\s*(mcg|µg|mg|g|iu|units?)\b/i', $value, $m)) return strtolower($m[1].$m[2]);
    return '';
}

function ai_parse_spreadsheet(string $path,string $originalName): array
{
    if(!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class))throw new RuntimeException('The PHP spreadsheet reader is not installed on this server.');
    $maxRows=max(1,(int)envv('AI_MAX_SPREADSHEET_ROWS','20000'));
    $normalize=static fn($v)=>preg_replace('/[^a-z0-9]/','',strtolower(trim((string)$v)));
    $aliases=[
        'name'=>['productname','product','itemname','item','medicine','medicinename','drug','drugname','productdescription','itemdescription','name','productdetails','medicineandstrength','drugproduct'],
        'weak_name'=>['description','details'],
        'code'=>['productcode','code','sku','itemcode','productsku'],
        'quantity'=>['quantity','quantityboxes','quantitybox','quantityboxesrequested','requestedquantityboxes','quantityrequiredboxes','quantityboxrequired','boxes','boxquantity','boxqty','qtybox','qtyboxes','qtyrequested','requestedquantity','requestedqty','requestedqtyboxes','requestedboxquantity','requestedamount','amount','orderquantity','orderqty','orderqtyboxes','quantityordered','quantityrequired','requiredquantity','qtyrequired','qtyordered','quantityneeded','qtyneeded','numberofboxes','quantityinboxes','qty','stockquantity','openingstock','openingstockboxes','openingquantity','currentstock','currentstockboxes','stockonhand','availableboxes','availablequantity','closingstock','closingstockboxes','restockquantity','restockboxes'],
        'unit'=>['unit','units','unitofmeasure','packagingunit','unittype'],
        'strength'=>['strength','dose'],'form'=>['dosageform','form'],'packaging'=>['packaging','packsize','package']
    ];
    $items=[];$foundSheet=false;$csv=strtolower(pathinfo($originalName,PATHINFO_EXTENSION))==='csv';$delimiter=',';$encoding='UTF-8';
    if($csv){$sample=(string)file_get_contents($path,false,null,0,65536);$firstLine=strtok($sample,"\r\n")?:'';$scores=[];foreach([',',';',"\t",'|'] as $candidate)$scores[$candidate]=count(str_getcsv($firstLine,$candidate,'"','\\'));arsort($scores);$delimiter=(string)array_key_first($scores);if(function_exists('mb_detect_encoding'))$encoding=mb_detect_encoding($sample,['UTF-8','Windows-1252','ISO-8859-1'],true)?:'UTF-8';}
    try{
        $probe=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);$sheetNames=$csv?['Worksheet']:$probe->listWorksheetNames($path);unset($probe);
        if(!$sheetNames)throw new RuntimeException('No worksheets were found.');
        foreach($sheetNames as $sheetName){
            $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);$reader->setReadDataOnly(true);
            $reader->setReadFilter(new class($maxRows) implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter{public function __construct(private int $maxRows){}public function readCell(string $columnAddress,int $row,string $worksheetName=''):bool{return $row<=$this->maxRows;}});
            if(method_exists($reader,'setLoadSheetsOnly')&&!$csv)$reader->setLoadSheetsOnly([$sheetName]);
            if($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Csv){$reader->setDelimiter($delimiter);$reader->setEnclosure('"');$reader->setEscapeCharacter('\\');$reader->setInputEncoding($encoding);}
            $book=$reader->load($path);$sheet=$csv?$book->getActiveSheet():($book->getSheetByName($sheetName)??$book->getActiveSheet());if($sheet->getHighestDataRow()>$maxRows)throw new RuntimeException('Worksheet "'.$sheetName.'" exceeds the configured row limit of '.$maxRows.'. Increase AI_MAX_SPREADSHEET_ROWS or split the workbook.');$data=$sheet->toArray(null,true,true,false);$headerRow=null;$indices=[];
            foreach(array_slice($data,0,30,true) as $rowNumber=>$candidateRow){$header=array_map($normalize,$candidateRow);$find=static function(array $options)use($header){foreach($header as $index=>$value)if(in_array($value,$options,true))return $index;return null;};$nameIndex=$find($aliases['name']);if($nameIndex===null){foreach($header as $candidateIndex=>$candidateHeader)if(preg_match('/^(?:product|medicine|drug|item)(?:name|description|details)$/',$candidateHeader)){$nameIndex=$candidateIndex;break;}}if($nameIndex===null)$nameIndex=$find($aliases['weak_name']);$codeIndex=$find($aliases['code']);$quantityCandidates=[];foreach($header as $index=>$value){if(!in_array($value,$aliases['quantity'],true)&&!preg_match('/quantity|qty|boxes|boxqty|stock|onhand|available|amount/',$value))continue;if(preg_match('/price|cost|selling|buying|value|reorder|minimum|maximum|threshold|level|unitsperbox|packsize/',$value))continue;$quantityCandidates[$index]=preg_match('/reorder|minimum|threshold|level/',$value)?-100:(preg_match('/openingstock|stockonhand|currentstock|availablequantity|availableboxes/',$value)?100:(preg_match('/requested|order|quantityboxes|qtyboxes|boxes|quantity|qty/',$value)?80:40));}if(($nameIndex!==null||$codeIndex!==null)&&$quantityCandidates){arsort($quantityCandidates);$qtyIndex=(int)array_key_first($quantityCandidates);$headerRow=$rowNumber;$indices=['name'=>$nameIndex,'code'=>$codeIndex,'quantity'=>$qtyIndex,'quantity_header'=>$header[$qtyIndex]??'','unit'=>$find($aliases['unit']),'strength'=>$find($aliases['strength']),'form'=>$find($aliases['form']),'packaging'=>$find($aliases['packaging'])];break;}}
            if($headerRow!==null){$foundSheet=true;foreach(array_slice($data,$headerRow+1,null,true) as $rowOffset=>$row){$rowNumber=$rowOffset+1;$nameIndex=$indices['name'];$codeIndex=$indices['code'];$qtyIndex=$indices['quantity'];$name=trim((string)($nameIndex!==null?($row[$nameIndex]??''):''));$code=trim((string)($codeIndex!==null?($row[$codeIndex]??''):''));$quantityRaw=trim((string)($row[$qtyIndex]??''));if($name===''&&$code===''&&$quantityRaw==='')continue;if($name===''&&$code==='')throw new RuntimeException('Worksheet "'.$sheetName.'", row '.$rowNumber.' needs a product name or code.');$quantityValue=null;$inlineUnit='';if($quantityRaw!==''&&preg_match('/^\s*(-?[0-9][0-9,]*(?:\.[0-9]+)?)\s*(.*?)\s*$/u',$quantityRaw,$quantityMatch)){$numericQuantity=str_replace(',','',$quantityMatch[1]);$inlineUnit=trim($quantityMatch[2]);if(is_numeric($numericQuantity)){$numeric=(float)$numericQuantity;$quantityValue=floor($numeric)===$numeric?(int)$numeric:$numeric;}}$unitIndex=$indices['unit'];$unit=trim((string)($unitIndex!==null?($row[$unitIndex]??''):''));if($unit==='')$unit=$inlineUnit;if($unit===''&&preg_match('/boxes|boxquantity|quantitybox|qtybox|boxqty|cases|casequantity/',$indices['quantity_header']??''))$unit='BOX';$interpretedUnit=$unit!==''&&preg_match('/^(boxes|box|bx|case|cases)$/i',$unit)?'BOX':($unit!==''?strtoupper($unit):'BOX');$strengthIndex=$indices['strength'];$formIndex=$indices['form'];$packagingIndex=$indices['packaging'];$items[]=['original_text'=>substr(($name?:$code).' - '.$quantityRaw.($unit!==''?' '.$unit:''),0,2000),'product_name'=>$name,'product_code'=>$code,'requested_quantity'=>$quantityValue,'original_unit'=>$unit?:null,'interpreted_unit'=>$interpretedUnit,'unit_source'=>$unit?'DOCUMENT':'DEFAULT_ASSUMPTION','strength'=>$strengthIndex!==null?trim((string)($row[$strengthIndex]??'')):null,'dosage_form'=>$formIndex!==null?trim((string)($row[$formIndex]??'')):null,'packaging'=>$packagingIndex!==null?trim((string)($row[$packagingIndex]??'')):null,'sheet_name'=>(string)$sheetName,'row_number'=>$rowNumber,'confidence'=>1,'uncertain_fields'=>array_merge((!is_int($quantityValue)||$quantityValue<1)?['requested_quantity']:[],($unit!==''&&preg_match('/^(boxes|box|bx|case|cases)$/i',$unit))?[]:['unit'])];if(count($items)>$maxRows)throw new RuntimeException('The spreadsheet exceeds the configured row limit of '.$maxRows.'. Increase AI_MAX_SPREADSHEET_ROWS on the server or split the workbook.');}}
            $book->disconnectWorksheets();unset($book,$data,$sheet,$reader);
        }
    }catch(RuntimeException $e){throw $e;}catch(Throwable $e){error_log('AI spreadsheet read failed: '.get_class($e).' - '.substr($e->getMessage(),0,300));throw new RuntimeException('The workbook could not be read. Save it as a valid XLS, XLSX or CSV file and try again.');}
    if(!$foundSheet)throw new RuntimeException('Could not find product and quantity columns in any worksheet. Add headers such as Product Name and Quantity (Boxes), then retry.');
    if(!$items)throw new RuntimeException('No product rows were found. Check that the workbook sheets contain product names and quantities.');
    return $items;
}

function ai_match_products(PDO $pdo, array $items, int $branchId, bool $publicOnly): array
{
    $sql = 'SELECT p.id,p.code,p.name,p.description,p.units_per_box,p.allow_individual,p.is_public,c.name category,COALESCE(bi.stock_units,0) stock_units,COALESCE(bi.box_price,p.box_price) box_price,COALESCE(bi.cost_price,p.cost_price) cost_price FROM products p LEFT JOIN product_categories c ON c.id=p.category_id LEFT JOIN branch_inventory bi ON bi.product_id=p.id AND bi.branch_id=? WHERE p.is_active=1'.($publicOnly?' AND p.is_public=1':'').' ORDER BY p.name LIMIT 3000';
    $q=$pdo->prepare($sql);$q->execute([$branchId]);$products=$q->fetchAll();
    $seen=[];$out=[];
    foreach($items as $index=>$raw){
        $name=trim((string)($raw['product_name']??$raw['name']??''));$productCode=strtoupper(trim((string)($raw['product_code']??$raw['code']??'')));$original=trim((string)($raw['original_text']??$name));
        $qty=is_numeric($raw['requested_quantity']??$raw['quantity']??null)?(float)($raw['requested_quantity']??$raw['quantity']):null;
        if(preg_match('/^(.*?)\s+(tabs?|tablets?|caps?|capsules?)\s*(\d{1,5})$/iu',$name,$compact)){if($qty===null)$qty=(float)$compact[3];$name=trim($compact[1]).' '.(preg_match('/^cap/i',$compact[2])?'Caps':'Tabs');}
        elseif(preg_match('/^(.*?)\s*[-?]\s*(\d{1,5})(?:\s*(?:boxes?|bx|cases?))?$/iu',$name,$trailing)){if($qty===null)$qty=(float)$trailing[2];$name=trim($trailing[1]);} 
        $unit=trim((string)($raw['interpreted_unit']??$raw['unit']??''));$originalUnit=trim((string)($raw['original_unit']??''));
        $unitSource=strtoupper((string)($raw['unit_source']??''));
        if($unit===''){ $unit='BOX';$unitSource='DEFAULT_ASSUMPTION'; }
        if(!in_array($unitSource,['DOCUMENT','DEFAULT_ASSUMPTION','UNKNOWN'],true))$unitSource=$originalUnit!==''?'DOCUMENT':'UNKNOWN';
        $norm=ai_normalize_name($name);$strength=trim((string)($raw['strength']??''));$form=ai_normalize_name((string)($raw['dosage_form']??''));
        $crossed=!empty($raw['is_crossed_out'])||strtoupper((string)($raw['status']??''))==='CROSSED_OUT';
        $duplicate=$norm!==''&&isset($seen[$norm]);if($norm!=='')$seen[$norm]=true;
        $ranked=[];
        foreach($products as $p){
            $candidateName=(string)$p['name'];$candidateText=$candidateName.' '.(string)$p['description'];
            $docStrength=ai_extract_strength($strength)?:ai_extract_strength($name.' '.$original);
            $candidateStrength=ai_extract_strength($candidateText);
            if($docStrength!==''&&$candidateStrength!==''&&$docStrength!==$candidateStrength)continue;
            if($docStrength!==''&&$candidateStrength==='')continue;
            $packaging=trim((string)($raw['packaging']??''));if($packaging!==''&&preg_match('/\b(\d+)\s*(?:tablets?|capsules?|sachets?|pieces?|units?|bottles?|vials?|tubes?)\b/i',$packaging,$packCount)&&(int)$packCount[1]!==max(1,(int)$p['units_per_box']))continue;
            $candidateForm=ai_normalize_name((string)$p['category'].' '.$candidateText);
            if($form!==''&&preg_match('/\b(tablets?|capsules?|syrups?|suspensions?|injections?|creams?|ointments?|drops?|solutions?|sachets?)\b/u',$form,$fm)){
                if(!str_contains($candidateForm,$fm[1]))continue;
            }
            $candidateNorm=ai_normalize_name($candidateName);
            if($productCode!==''&&strtoupper((string)$p['code'])===$productCode)$score=1.0;
            elseif($norm!==''&&$candidateNorm===$norm)$score=1.0;
            else {
                $a=array_values(array_unique(array_filter(explode(' ',$norm))));$b=array_values(array_unique(array_filter(explode(' ', $candidateNorm))));
                $overlap=count(array_intersect($a,$b));$dice=($a||$b)?(2*$overlap/max(1,count($a)+count($b))):0;
                similar_text($norm,$candidateNorm,$pct);$score=max($dice,$pct/100);
                // Allow plausible spelling variation without requiring an exact catalog spelling.
                $tokenScores=[];foreach($a as $word){$bestToken=0.0;foreach($b as $candidateWord){$maxLen=max(strlen($word),strlen($candidateWord));if($maxLen<4)continue;$distance=levenshtein($word,$candidateWord);$bestToken=max($bestToken,1-($distance/$maxLen));}$tokenScores[]=$bestToken;}
                if($tokenScores){rsort($tokenScores);$fuzzy=($tokenScores[0]+(count($tokenScores)>1?$tokenScores[1]:$tokenScores[0]))/2;$score=max($score,$fuzzy*.92);}
                $generic=ai_normalize_name((string)($raw['generic_name']??''));$brand=ai_normalize_name((string)($raw['brand_name']??''));
                if($generic!==''&&str_contains($candidateNorm,$generic))$score=max($score,.82);
                if($brand!==''&&str_contains($candidateNorm,$brand))$score=max($score,.78);
                if($docStrength!==''&&$candidateStrength===$docStrength)$score=min(1,$score+.05);
            }
            if($score>=.4)$ranked[]=['product_id'=>(int)$p['id'],'product_code'=>$p['code'],'product_name'=>$p['name'],'units_per_box'=>(int)$p['units_per_box'],'allow_individual'=>(bool)$p['allow_individual'],'stock_units'=>(int)$p['stock_units'],'available_boxes'=>(int)floor((int)$p['stock_units']/max(1,(int)$p['units_per_box'])),'box_price'=>(float)$p['box_price'],'cost_price'=>(float)$p['cost_price'],'confidence'=>round($score,4),'category'=>$p['category']];
        }
        usort($ranked,static fn($a,$b)=>$b['confidence']<=>$a['confidence']);$best=$ranked[0]??null;
        $ambiguous=isset($ranked[1])&&$best&&$best['confidence']>=.75&&($best['confidence']-$ranked[1]['confidence'])<.025;
        $status=$crossed?'CROSSED_OUT':($duplicate?'DUPLICATE':((!$name&&!$productCode)||$qty===null||$qty<=0||floor($qty)!==$qty?'NEEDS_VERIFICATION':(!$best?'NOT_FOUND':($ambiguous?'AMBIGUOUS_MATCH':($best['confidence']>=.95?'EXACT_MATCH':($best['confidence']>=.75?'SUGGESTED_MATCH':'NEEDS_VERIFICATION'))))));
        $matched=$status==='EXACT_MATCH'?$best:null;
        $uncertain=$raw['uncertain_fields']??[];if(!is_array($uncertain))$uncertain=[];
        if($qty===null||$qty<=0||floor($qty)!==$qty)$uncertain[]='requested_quantity';if($name==='')$uncertain[]='product_name';$clearTrailingQuantity=(bool)preg_match('/(?:\s-\s*\d+(?:[.,]\d+)?|\b(?:tabs?|tablets?|caps?|capsules?)\s*\d+)(?:\s*[-\x{2013}\x{2014}]?)\s*$/iu',$original);if($unitSource==='DEFAULT_ASSUMPTION'&&!$clearTrailingQuantity)$uncertain[]='unit';
        $out[]=['line_key'=>(string)($raw['line_key']??($index+1)),'original_text'=>$original,'product_name'=>$name?:($best['product_name']??''),'product_code'=>$productCode?:($best['product_code']??''),'normalized_product_name'=>$norm,'generic_name'=>$raw['generic_name']??null,'brand_name'=>$raw['brand_name']??null,'strength'=>$strength?:null,'dosage_form'=>$raw['dosage_form']??null,'packaging'=>$raw['packaging']??null,'requested_quantity'=>$qty,'original_unit'=>$originalUnit?:null,'interpreted_unit'=>$unit,'unit_source'=>$unitSource,'units_per_box'=>isset($raw['units_per_box'])?(int)$raw['units_per_box']:null,'unit_price'=>isset($raw['unit_price'])?(float)$raw['unit_price']:null,'total_price'=>isset($raw['total_price'])?(float)$raw['total_price']:null,'category'=>$raw['category']??null,'sheet_name'=>isset($raw['sheet_name'])?(string)$raw['sheet_name']:null,'row_number'=>isset($raw['row_number'])?(int)$raw['row_number']:null,'page_number'=>isset($raw['page_number'])?(int)$raw['page_number']:null,'column_number'=>isset($raw['column_number'])?(int)$raw['column_number']:null,'line_number'=>isset($raw['line_number'])?(int)$raw['line_number']:null,'extraction_confidence'=>isset($raw['confidence'])?(float)$raw['confidence']:null,'uncertain_fields'=>array_values(array_unique($uncertain)),'is_crossed_out'=>$crossed,'is_duplicate'=>$duplicate,'status'=>$status,'matched_product'=>$matched,'candidates'=>array_slice($ranked,0,5)];
    }
    return $out;
}

function ai_gemini_extract(string $filePath,string $mime,string $originalName): array
{
    @set_time_limit(240);
    $key=envv('GEMINI_API_KEY',envv('GOOGLE_GEMINI_API_KEY'));
    if($key==='')throw new RuntimeException('AI is not configured yet. Add GEMINI_API_KEY to the server .env. Excel upload and manual entry are still available.');
    if(!function_exists('curl_init'))throw new RuntimeException('The PHP cURL extension is required for AI document processing.');
    $model=envv('GEMINI_MODEL','gemini-3.5-flash-lite');$started=microtime(true);
    $prompt="Read this pharmacy order document, inventory list, or stock valuation report, including handwritten pages, spreadsheets exported as PDF, and printed tables, and extract every medicine line into structured JSON. Return only {\"document_date\":string|null,\"heading\":string|null,\"items\":[...]}. Each item must contain original_text, product_name, product_code, generic_name, brand_name, strength, dosage_form, packaging, requested_quantity, original_unit, interpreted_unit, unit_source, units_per_box, unit_price, total_price, category, page_number, column_number, line_number, confidence (0..1), uncertain_fields (array), is_crossed_out (boolean). For page layouts with multiple columns, identify the column boundaries first, then read each column top-to-bottom from left to right; never jump across columns on the same horizontal line. For tables, keep each row's cells together and extract each medicine row once. If the document is a stock valuation report, use its Boxes column as requested_quantity and capture the product code/SKU exactly; ignore loose units, prices, valuation totals and profit columns. In a workbook, infer the medicine-name and requested-quantity columns from their meaning across the full header row, including headers such as Product Name, Medicine, Item Name, Opening Stock (Boxes), Available Boxes, Quantity or Qty. Prefer a real product-name column over Description when Description contains a category. Never use Reorder Level, price, cost, Units per Box or pack-size as requested quantity. Values like '1 box' mean one box. Process every page and do not omit faint or handwritten lines. A final dash followed by a clear number (for example, 'Medicine name - 5') means requested_quantity=5 even when no other details follow. Also interpret joined shorthand like 'Abitol tabs40' or 'Abito tabs40' as medicine name Abitol Tabs and 40 boxes, preserving source spelling and flagging only an uncertain medicine spelling. Do not mistake numbers attached to mg, mcg, g, ml, IU or units for box counts. In that case treat the quantity and product name as clear, use BOX as the default unit, and do not request verification just because the unit was not written. Do not treat dashes inside a medicine name as quantity separators. Preserve names, strengths, forms and brands as written. If spelling is imperfect but readable, transcribe the most likely literal medicine name and retain a lower confidence; do not silently substitute another medicine. If a field truly cannot be read, use null and mark only that field uncertain. Keep crossed-out entries and mark is_crossed_out true. Do not infer units_per_box, product equivalence, or personal customer information. For explicit units, preserve them and set unit_source DOCUMENT. File name: ".substr($originalName,0,120);
    $mediaResolution=str_starts_with($mime,'image/')?'MEDIA_RESOLUTION_HIGH':'MEDIA_RESOLUTION_MEDIUM';
    $body=['contents'=>[['parts'=>[['text'=>$prompt],['inline_data'=>['mime_type'=>$mime,'data'=>base64_encode((string)file_get_contents($filePath))]]]]],'generationConfig'=>['responseMimeType'=>'application/json','mediaResolution'=>$mediaResolution,'maxOutputTokens'=>65536]];
    $url='https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent?key='.rawurlencode($key);
    // Three provider attempts plus backoff must finish before this function's
    // 240-second PHP execution limit, so the worker can persist a real error.
    $curl=curl_init($url);curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>65]);
    $response=false;$status=0;$error='';
    // Keep the total retry window comfortably below the PHP request timeout.
    // Retrying four 180-second calls could make the browser see a dead request.
    for($attempt=0;$attempt<3;$attempt++){
        $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);$error=curl_error($curl);
        if($response!==false&&!in_array($status,[429,500,502,503,504],true))break;
        if($attempt<2)usleep((int)(1000000*(2**$attempt)));
    }
    curl_close($curl);
    $elapsed=(int)((microtime(true)-$started)*1000);
    if($response===false)throw new RuntimeException('Gemini service could not be reached. Please retry shortly.');
    $decoded=json_decode($response,true);
    if($status<200||$status>=300){
        $code=(string)($decoded['error']['status']??'GEMINI_HTTP_'.$status);
        if($code==='NOT_FOUND'||$status===404)throw new RuntimeException('Gemini model "'.$model.'" is unavailable for this API key. Update GEMINI_MODEL to a model currently enabled for your Google AI Studio project.');
        if($code==='RESOURCE_EXHAUSTED'||$status===429)throw new RuntimeException('Gemini free-tier quota or request rate limit was reached. Check the project quota in Google AI Studio, then retry when it resets.');
        if($code==='PERMISSION_DENIED'||$status===403)throw new RuntimeException('Google denied access to this Gemini model. Check that this API key belongs to the intended AI Studio project and that the Gemini API is available for it.');
        if($status===503||$code==='UNAVAILABLE')throw new RuntimeException('Gemini is busy right now. Please try again in a moment.');
        throw new RuntimeException('Gemini could not process this file ('.$code.'). Check the Gemini API configuration and try again.');
    }
    $text='';foreach(($decoded['candidates'][0]['content']['parts']??[]) as $part)$text.=(string)($part['text']??'');
    $result=json_decode(trim(preg_replace('/^```(?:json)?\s*|\s*```$/','',$text)??$text),true);
    if(!is_array($result)||!isset($result['items'])||!is_array($result['items'])){
        $finish=(string)($decoded['candidates'][0]['finishReason']??'');
        if($finish==='MAX_TOKENS')throw new RuntimeException('The extracted list is too long to return in one pass. Split this document into smaller sections and retry.');
        if(!empty($decoded['promptFeedback']['blockReason']))throw new RuntimeException('The document could not be processed. Check that it contains only the product information needed for this order.');
        throw new RuntimeException('No readable product list was returned for this file. Check that the PDF or image contains a clear product list and try again.');
    }
    $maxLines=function_exists('ai_max_order_lines')?ai_max_order_lines():max(1,(int)envv('AI_MAX_ORDER_LINES','20000'));if(count($result['items'])>$maxLines)throw new RuntimeException('The document exceeds the configured line limit of '.$maxLines.'. Increase AI_MAX_ORDER_LINES on the server or split the document.');
    foreach($result['items'] as &$item){if(!is_array($item))$item=[];$item['extraction_confidence']=isset($item['confidence'])?max(0,min(1,(float)$item['confidence'])):null;$item['original_text']=substr(trim((string)($item['original_text']??$item['product_name']??'')),0,2000);$item['product_name']=isset($item['product_name'])?substr(trim((string)$item['product_name']),0,255):'';if(isset($item['requested_quantity'])&&!is_numeric($item['requested_quantity']))$item['requested_quantity']=null;}
    unset($item);
    $usage=$decoded['usageMetadata']??[];
    $result['_meta']=['model'=>$model,'input_tokens'=>(int)($usage['promptTokenCount']??0),'output_tokens'=>(int)($usage['candidatesTokenCount']??0),'processing_ms'=>$elapsed];
    return $result;
}
