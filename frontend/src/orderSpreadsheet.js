import * as XLSX from 'xlsx';

const normalizeHeader=value=>String(value??'').trim().toLowerCase().replace(/[^a-z0-9]/g,'');
const text=value=>value===null||value===undefined?'':String(value).replace(/\u00a0/g,' ').trim();
const codeHeaders=new Set(['productcode','code','sku','itemcode','productsku']);

function headerKind(value){
  const h=normalizeHeader(value);
  if(codeHeaders.has(h))return 'code';
  if(/^(productname|name|itemname|medicinename|drugname|product|item|medicine|drug|productdescription|itemdescription|productdetails|medicineandstrength|drugproduct)$/.test(h))return 'name';
  if(/^(description|details)$/.test(h))return 'weak-name';
  if(/(quantity|qty|boxes|boxqty|numberofboxes|amount|orderqty|orderquantity|requestedqty|requestedquantity|stock|opening|closing|available|onhand|inventory)/.test(h)){
    if(/(price|cost|selling|buying|value|reorder|minimum|maximum|threshold|level|unitsperbox|unitspack|packsize)/.test(h))return '';
    const order=/(requested|order|purchase|restock|opening|closing|available|onhand|stock|quantity|qty|boxes)/.test(h);
    return order?'quantity':'';
  }
  return '';
}

function detectHeader(matrix){
  let best=null;
  for(let rowIndex=0;rowIndex<Math.min(matrix.length,30);rowIndex++){
    const row=matrix[rowIndex]||[],kinds=row.map(headerKind),nameIndex=kinds.indexOf('name'),weakNameIndex=kinds.indexOf('weak-name'),codeIndex=kinds.indexOf('code');
    const quantityIndexes=kinds.map((kind,index)=>kind==='quantity'?index:-1).filter(index=>index>=0);
    if(!quantityIndexes.length||(nameIndex<0&&codeIndex<0&&weakNameIndex<0))continue;
    const quantityIndex=quantityIndexes.sort((a,b)=>{
      const rank=index=>{const h=normalizeHeader(row[index]);if(/reorder|minimum|threshold|level/.test(h))return -100;if(/openingstock|stockonhand|currentstock|availablequantity|availableboxes/.test(h))return 100;if(/requested|order|quantityboxes|qtyboxes|boxes|quantity|qty/.test(h))return 80;return 40};return rank(b)-rank(a);
    })[0];
    const score=(nameIndex>=0?8:weakNameIndex>=0?2:0)+(codeIndex>=0?4:0)+6+Math.min(row.filter(v=>text(v)).length,8)/10;
    if(!best||score>best.score)best={row:rowIndex,codeIndex,nameIndex:nameIndex>=0?nameIndex:weakNameIndex,quantityIndex,score};
  }
  return best;
}

export function parseBoxQuantity(value){
  const raw=text(value),match=raw.match(/^\s*(-?[0-9][0-9,]*(?:\.[0-9]+)?)\s*(.*?)\s*$/u);
  if(!match)return {quantity:null,unit:'',valid:false,issue:'Quantity must be a positive whole number'};
  const quantity=Number(match[1].replace(/,/g,'')),unit=match[2].trim().toLowerCase();
  const boxUnit=!unit||['box','boxes','bx','case','cases'].includes(unit);
  if(!Number.isFinite(quantity))return {quantity:null,unit,valid:false,issue:'Quantity must be a positive whole number'};
  if(!boxUnit)return {quantity,unit,valid:false,issue:`Quantity unit “${unit}” is not boxes`};
  if(!Number.isSafeInteger(quantity)||quantity<1)return {quantity,unit,valid:false,issue:'Quantity must be a positive whole number'};
  return {quantity,unit,valid:true,issue:''};
}

/** Read each worksheet and infer meaningful product and quantity columns. */
export async function parseOrderSpreadsheet(file){
  const workbook=XLSX.read(await file.arrayBuffer(),{type:'array',cellDates:false});
  const parsed=[];
  for(const sheetName of workbook.SheetNames){
    const sheet=workbook.Sheets[sheetName];if(!sheet)continue;
    const matrix=XLSX.utils.sheet_to_json(sheet,{header:1,defval:'',blankrows:false,raw:true}),header=detectHeader(matrix);
    if(!header)continue;
    for(let i=header.row+1;i<matrix.length;i++){
      const row=matrix[i]||[],code=text(header.codeIndex>=0?row[header.codeIndex]:''),name=text(header.nameIndex>=0?row[header.nameIndex]:''),rawQuantity=text(row[header.quantityIndex]);
      if(!code&&!name&&!rawQuantity)continue;
      const parsedQuantity=parseBoxQuantity(rawQuantity),issues=[];
      if(!code&&!name)issues.push('Add a product code or name');if(!parsedQuantity.valid)issues.push(parsedQuantity.issue);
      parsed.push({sheet_name:sheetName,row:i+1,product_code:code,product_name:name,boxes:parsedQuantity.quantity,raw_quantity:rawQuantity,quantity_unit:parsedQuantity.unit,quantity_valid:parsedQuantity.valid,review_issues:issues});
    }
  }
  if(!parsed.length)throw new Error('Could not identify medicine and quantity columns. Use headers such as Product Name, Medicine, Item Name, Opening Stock (Boxes), Quantity, Boxes, or Qty.');
  return parsed;
}
