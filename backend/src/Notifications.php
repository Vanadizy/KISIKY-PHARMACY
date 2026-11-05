<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

function email_delivery_configured(): bool {
    return trim(envv('SMTP_HOST')) !== '' && trim(envv('MAIL_FROM_ADDRESS')) !== '';
}

function sms_delivery_configured(): bool {
    return trim(envv('SMS_PROFILE_ID')) !== ''
        && trim(envv('SMS_PASSWORD')) !== '' && trim(envv('SMS_SENDER_ID')) !== '';
}

function pharmacy_website_preview_url(): string {
    $base = rtrim(envv('APP_URL'), '/');
    return $base !== '' ? $base.'/api/share' : '';
}

function notification_message(string $message): string {
    $url = pharmacy_website_preview_url();
    return $url !== '' ? rtrim($message)."\n\nPharmacy website: ".$url : $message;
}

function pharmacy_display_name(): string {
    try {
        $name = db()->query("SELECT setting_value FROM pharmacy_settings WHERE setting_key='pharmacy_name' LIMIT 1")->fetchColumn();
        return trim((string)$name) ?: 'Pharmacy';
    } catch (Throwable $e) { return 'Pharmacy'; }
}

function order_email_preview_html(int $orderId, bool $customerView): string {
    try {
        $q=db()->prepare("SELECT o.order_reference,o.status,o.payment_status,o.subtotal,c.full_name customer,iv.invoice_reference,b.name branch_name FROM orders o JOIN branches b ON b.id=o.branch_id JOIN customers c ON c.id=o.customer_id LEFT JOIN order_invoices iv ON iv.order_id=o.id WHERE o.id=?");$q->execute([$orderId]);$order=$q->fetch();if(!$order)return '';
        $q=db()->prepare('SELECT product_name_snapshot,product_code_snapshot,requested_boxes,confirmed_boxes,box_price_snapshot,line_total FROM order_items WHERE order_id=? ORDER BY id');$q->execute([$orderId]);$items=$q->fetchAll();
        $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$name=$esc($order['branch_name']?:pharmacy_display_name());$reference=$esc($order['invoice_reference']?:$order['order_reference']);$url=rtrim(envv('APP_URL'),'/').($customerView?'/my-orders?order='.$orderId:'/?staff=1&order='.$orderId);$pdfUrl=rtrim(envv('APP_URL'),'/').($customerView?'/api/customer/orders/'.$orderId.'/pdf':'/api/orders/'.$orderId.'/pdf');
        $rows='';$previewTotal=0.0;foreach($items as $it){$confirmed=(int)$it['confirmed_boxes'];$qty=$confirmed>0?$confirmed:(int)$it['requested_boxes'];$unit=$it['box_price_snapshot']===null?null:(float)$it['box_price_snapshot'];$line=$it['line_total']===null?null:(float)$it['line_total'];if($line!==null)$previewTotal+=$line;$unitText=$unit===null?'Pending branch review':'TZS '.number_format($unit,0);$lineText=$line===null?'Pending':'TZS '.number_format($line,0);$rows.='<tr><td style="padding:8px;border-bottom:1px solid #e5eee9">'.$esc($it['product_name_snapshot']).'<br><small>'.$esc($it['product_code_snapshot']).'</small></td><td style="padding:8px;border-bottom:1px solid #e5eee9;text-align:right">'.$qty.' x '.$unitText.'</td><td style="padding:8px;border-bottom:1px solid #e5eee9;text-align:right">'.$lineText.'</td></tr>';}
        return '<section style="margin:24px 0;padding:18px;background:#f7fbf9;border:1px solid #dce8e3;border-radius:10px"><h2 style="margin:0 0 6px;color:#193a37;font-size:17px">Order invoice</h2><p style="margin:0 0 12px;color:#61766d">'.$name.' - '.$reference.' - '.ucwords(str_replace('_',' ',$order['status'])).' - '.ucwords($order['payment_status']).'</p><p style="margin:0 0 8px">Customer: '.$esc($order['customer']).'</p><table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr><th style="text-align:left;padding:8px">Item</th><th style="text-align:right;padding:8px">Quantity / unit</th><th style="text-align:right;padding:8px">Amount</th></tr></thead><tbody>'.$rows.'</tbody></table><p style="text-align:right;font-weight:bold">Total: TZS '.number_format((float)$order['subtotal']>0?(float)$order['subtotal']:$previewTotal,0).'</p><a href="'.$esc($pdfUrl).'" style="display:inline-block;padding:10px 15px;border-radius:7px;background:#087967;color:#fff;text-decoration:none;font-weight:bold">Open invoice PDF</a> <a href="'.$esc($url).'" style="display:inline-block;padding:10px 15px;border-radius:7px;border:1px solid #087967;color:#087967;text-decoration:none;font-weight:bold">Open order details</a><p style="margin:10px 0 0;color:#71827d;font-size:11px">The invoice PDF is also attached to this email.</p></section>';
    } catch(Throwable $e) { error_log('Order email preview generation failed: '.$e->getMessage()); return ''; }
}

function branch_notification_recipients(int $branchId, array $permissionCodes): array {
    if ($branchId < 1 || !$permissionCodes) return [];
    $marks=implode(',',array_fill(0,count($permissionCodes),'?'));
    $sql="SELECT DISTINCT u.email FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN user_branches ub ON ub.user_id=u.id AND ub.branch_id=? LEFT JOIN user_permissions up ON up.user_id=u.id LEFT JOIN permissions p ON p.id=up.permission_id WHERE u.status='active' AND u.email IS NOT NULL AND u.email<>'' AND (r.name='Administrator' OR (ub.branch_id IS NOT NULL AND p.code IN ($marks)))";
    $q=db()->prepare($sql);$q->execute(array_merge([$branchId],$permissionCodes));
    return array_values(array_unique(array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN))));
}

function send_branch_staff_notification(int $branchId, string $subject, string $message, array $permissionCodes, array $attachments=[]): int {
    $q=db()->prepare('SELECT name FROM branches WHERE id=?');$q->execute([$branchId]);$branchName=trim((string)$q->fetchColumn());
    if($branchName==='')return 0;
    $subject='['.$branchName.'] '.$subject;
    $message='Branch: '.$branchName."\n\n".$message;
    $sent=0;foreach(branch_notification_recipients($branchId,$permissionCodes) as $email){if(send_branded_email($email,$subject,$message,$attachments))$sent++;}
    if($sent===0)error_log('Branch notification had no deliverable Finance recipients: '.$subject);
    return $sent;
}

function send_branch_finance_email(int $branchId, string $subject, string $message, array $attachments=[]): int {
    return send_branch_staff_notification($branchId,$subject,$message,['finance.view','finance.approve','finance.reject'],$attachments);
}

function branch_finance_email_addresses(int $branchId): array {
    $q=db()->prepare("SELECT DISTINCT u.email FROM users u JOIN user_branches ub ON ub.user_id=u.id JOIN user_permissions up ON up.user_id=u.id JOIN permissions p ON p.id=up.permission_id WHERE ub.branch_id=? AND u.status='active' AND u.email IS NOT NULL AND u.email<>'' AND p.code IN ('finance.view','finance.approve','finance.reject') ORDER BY u.email");
    $q->execute([$branchId]);return array_values(array_unique(array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN))));
}

function send_order_staff_notification(string $subject, string $message, ?int $orderId=null): int {
    if($orderId){
        $q=db()->prepare('SELECT o.branch_id,b.name branch_name FROM orders o JOIN branches b ON b.id=o.branch_id WHERE o.id=?');$q->execute([$orderId]);$branch=$q->fetch();if(!$branch)return 0;
        $emails=branch_notification_recipients((int)$branch['branch_id'],['orders.view','finance.view','finance.approve','finance.reject']);
        $subject='['.$branch['branch_name'].'] '.$subject;$message='Branch: '.$branch['branch_name']."\n\n".$message;
        $sent=0;foreach($emails as $email)if(send_branded_email($email,$subject,$message,order_pdf_attachment($orderId),order_email_preview_html($orderId,false)))$sent++;
        if($sent===0)error_log('Branch order notification had no deliverable recipients: '.$subject);return $sent;
    }
    $sql = "SELECT DISTINCT u.email FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN user_permissions up ON up.user_id=u.id LEFT JOIN permissions p ON p.id=up.permission_id WHERE u.status='active' AND u.email IS NOT NULL AND u.email<>'' AND (r.name='Administrator' OR p.code='orders.view')";
    $sent = 0;foreach(db()->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $email)if(send_branded_email((string)$email,$subject,$message))$sent++;
    if($sent===0)error_log('Order staff notification had no deliverable recipients: '.$subject);return $sent;
}

function order_pdf_attachment(int $orderId): array {
    try {
        $q=db()->prepare("SELECT o.order_reference,o.status,o.payment_status,o.subtotal,o.created_at,c.full_name customer,c.email,c.phone,iv.invoice_reference,b.name pharmacy_name,b.location branch_location,b.address branch_address,b.phone branch_phone,b.contact_email branch_email,b.payment_instructions FROM orders o JOIN branches b ON b.id=o.branch_id JOIN customers c ON c.id=o.customer_id LEFT JOIN order_invoices iv ON iv.order_id=o.id WHERE o.id=?");
        $q->execute([$orderId]); $order=$q->fetch(); if(!$order) return [];
        $q=db()->prepare('SELECT product_name_snapshot,product_code_snapshot,requested_boxes,confirmed_boxes,box_price_snapshot,line_total FROM order_items WHERE order_id=? ORDER BY id'); $q->execute([$orderId]); $items=$q->fetchAll();
        $name=trim((string)($order['pharmacy_name']??''))?:'Pharmacy';
        $lines=[$name,'Location: '.($order['branch_location']?:'-'),'Address: '.($order['branch_address']?:'-'),'Phone: '.($order['branch_phone']?:'-'),'Email: '.($order['branch_email']?:'-'),'Payment: '.($order['payment_instructions']?:'Contact the selected branch for payment instructions'),'ORDER '.strtoupper((string)($order['invoice_reference']?:$order['order_reference'])),'Customer: '.$order['customer'],'Email: '.($order['email']?:'-').'   Phone: '.($order['phone']?:'-'),'Date: '.$order['created_at'],'Status: '.strtoupper(str_replace('_',' ',$order['status'])).' / Payment: '.strtoupper($order['payment_status']),'','ITEM                         CODE       QTY       UNIT PRICE       TOTAL','--------------------------------------------------------------------------------'];
        foreach($items as $it){$confirmed=(int)$it['confirmed_boxes'];$qty=$confirmed>0?$confirmed:(int)$it['requested_boxes'];$unit=$it['box_price_snapshot']===null?null:(float)$it['box_price_snapshot'];$total=$it['line_total']===null?null:(float)$it['line_total'];if($total!==null)$pdfTotal=($pdfTotal??0)+$total;$lines[]=substr((string)$it['product_name_snapshot'],0,27).'  '.substr((string)$it['product_code_snapshot'],0,10).'  '.$qty.' box(es)  '.($unit===null?'PENDING PRICE':'TZS '.number_format($unit,0)).'  '.($total===null?'PENDING':'TZS '.number_format($total,0));}
        $invoiceTotal=(float)$order['subtotal']>0?(float)$order['subtotal']:(float)($pdfTotal??0);$lines[]='';$lines[]='TOTAL: TZS '.number_format($invoiceTotal,0);$lines[]='';$lines[]='Thank you for choosing '.$name.'.';$lines[]='Website: '.(pharmacy_website_preview_url()?:'');
        $content="BT\n/F1 10 Tf\n50 790 Td\n14 TL\n";
        foreach(array_slice($lines,0,48) as $line){$line=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$line)?:'';$line=preg_replace('/[^\x20-\x7E]/','',$line)??'';$line=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$content.='('.$line.") Tj\nT*\n";}
        $content.="ET";
        $objs=[];$objs[]='<< /Type /Catalog /Pages 2 0 R >>';$objs[]='<< /Type /Pages /Kids [3 0 R] /Count 1 >>';$objs[]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>';$objs[]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';$objs[]='<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";
        $pdf="%PDF-1.4\n";$offsets=[0];foreach($objs as $i=>$obj){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objs)+1)."\n0000000000 65535 f \n";for($i=1;$i<count($offsets);$i++)$pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";$pdf.="trailer\n<< /Size ".(count($objs)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        $ref=preg_replace('/[^A-Za-z0-9_-]/','_', (string)($order['invoice_reference']?:$order['order_reference']));
        return ['filename'=>$ref.'.pdf','content'=>$pdf];
    } catch (Throwable $e) { error_log('Order PDF generation failed: '.$e->getMessage()); return []; }
}

function send_admin_notification(string $subject, string $message): int {
    $q = db()->query("SELECT u.email FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='Administrator' AND u.status='active' AND u.email IS NOT NULL AND u.email<>''");
    $sent = 0;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $email) {
        if (send_branded_email((string)$email, $subject, $message)) $sent++;
    }
    if ($sent === 0) error_log('Administrator notification was not delivered: '.$subject);
    return $sent;
}

function send_sms(string $to, string $message): bool {
    $GLOBALS['sms_delivery_status'] = 'not sent';
    $GLOBALS['sms_delivery_http_status'] = 0;
    if (!sms_delivery_configured()) { $GLOBALS['sms_delivery_status']='not sent: profile ID, password, or sender ID is missing'; error_log('SMS delivery skipped: profile ID, password, or sender ID is missing'); return false; }
    if (!function_exists('curl_init')) { $GLOBALS['sms_delivery_status']='not sent: PHP cURL is unavailable'; error_log('SMS delivery failed: PHP cURL is unavailable'); return false; }
    $digits = preg_replace('/\D+/', '', $to) ?? '';
    if (str_starts_with($digits, '0')) $digits = '255'.substr($digits, 1);
    elseif (str_starts_with($digits, '7') || str_starts_with($digits, '6')) $digits = '255'.$digits;
    if (!preg_match('/^255\d{9}$/', $digits)) { $GLOBALS['sms_delivery_status']='not sent: invalid Tanzania mobile number'; error_log('SMS delivery skipped: recipient is not a valid Tanzania mobile number'); return false; }
    if (trim($message) === '') { $GLOBALS['sms_delivery_status']='not sent: message is empty'; error_log('SMS delivery skipped: message is empty'); return false; }
    $message = notification_message($message);
    $payload = [[
        'user' => envv('SMS_PROFILE_ID'), 'pwd' => envv('SMS_PASSWORD'),
        'number' => $digits, 'msg' => $message, 'sender' => envv('SMS_SENDER_ID'),
        'language' => 'English',
    ]];
    $ch = curl_init(envv('SMS_API_URL', 'https://mshastra.com/sendsms_api_json.aspx'));
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>20]);
    $response = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
    $GLOBALS['sms_delivery_http_status']=$status;
    if ($response === false || $status < 200 || $status >= 300) { $GLOBALS['sms_delivery_status']='not sent: '.($error ?: 'HTTP '.$status); error_log('SMS delivery failed: '.($error ?: 'HTTP '.$status)); return false; }
    $result = json_decode((string)$response, true);
    if (!is_array($result)) {
        $plain=trim((string)$response);$plainStatus=strtolower($plain);
        if (in_array($plainStatus,['send success','send successful'],true)) { $GLOBALS['sms_delivery_status']=$plain; return true; }
        $GLOBALS['sms_delivery_status']='not sent: '.substr(preg_replace('/[^A-Za-z0-9 _-]/','',$plain?:'unreadable provider response')??'provider response error',0,120);
        error_log('SMS delivery failed: provider returned a non-success response'); return false;
    }
    $rows = array_is_list($result) ? $result : [$result];
    if (!$rows) { $GLOBALS['sms_delivery_status']='not sent: empty provider response'; error_log('SMS delivery failed: provider returned an empty response'); return false; }
    foreach ($rows as $row) {
        $raw=is_array($row)?(string)($row['response']??$row['status']??''):'';
        $normalized=strtolower(trim($raw));
        if (!in_array($normalized,['send success','send successful'],true)) {
            $safe=substr(preg_replace('/[^A-Za-z0-9 _-]/','',$raw?:'provider rejected request')??'provider rejected request',0,120);
            $GLOBALS['sms_delivery_status']='not sent'.($safe!==''?': '.$safe:'');
            error_log('SMS delivery rejected by provider: '.$safe);
            return false;
        }
    }
    $displayStatus=substr(preg_replace('/[^A-Za-z0-9 _-]/','',$raw)??'Send Success',0,120);$GLOBALS['sms_delivery_status']=$displayStatus!==''?$displayStatus:'Send Success';
    return true;
}

function sms_delivery_report(): array {
    return ['provider_status'=>(string)($GLOBALS['sms_delivery_status']??'not sent'),'http_status'=>(int)($GLOBALS['sms_delivery_http_status']??0)];
}

function send_branded_email(string $to, string $subject, string $message, array $attachments = [], string $previewHtml = ''): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { error_log('Email delivery skipped: recipient address is invalid'); return false; }
    if (!email_delivery_configured()) { error_log('Email delivery skipped: SMTP host or sender address is missing'); return false; }
    try {
        // Normalize escaped newlines from single-quoted PHP message strings.
        $message = str_replace(['\\n', '\\r'], ["\n", ""], $message);
        $message = notification_message($message);
        $settings = array_column(db()->query("SELECT setting_key,setting_value FROM pharmacy_settings WHERE setting_key IN ('pharmacy_name','logo_url','contact_phone','contact_email')")->fetchAll(), 'setting_value', 'setting_key');
        $name = trim((string)($settings['pharmacy_name'] ?? 'Pharmacy')) ?: 'Pharmacy';
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logo = trim((string)($settings['logo_url'] ?? ''));
        $logoHtml = $logo !== '' ? '<img src="'.$esc($logo).'" alt="'.$esc($name).'" style="display:block;max-width:180px;max-height:72px;margin:0 0 18px">' : '';
        $contact = implode(' | ', array_filter([(string)($settings['contact_phone'] ?? ''), (string)($settings['contact_email'] ?? '')]));
        $html = '<!doctype html><html><body style="margin:0;background:#f1f6f3;font:14px Arial,sans-serif;color:#193a37;padding:28px"><div style="max-width:620px;margin:auto;background:#fff;border:1px solid #dce8e3;border-radius:12px;padding:28px">'.$logoHtml.'<h1 style="font-size:21px;color:#087967;margin:0 0 18px">'.$esc($name).'</h1><div style="line-height:1.7">'.nl2br($esc($message)).'</div>'.$previewHtml.'<p style="border-top:1px solid #e3ece7;padding-top:14px;margin-top:24px;color:#71827d;font-size:12px">'.$esc($contact).'</p></div></body></html>';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = trim(envv('SMTP_HOST'));
        $mail->Port = max(1, (int)envv('SMTP_PORT', '587'));
        // Port 465 is implicit TLS; STARTTLS on this port can stall or fail.
        $mail->Timeout = 15;
        $mail->SMTPAuth = trim(envv('SMTP_USERNAME')) !== '';
        if ($mail->SMTPAuth) { $mail->Username = envv('SMTP_USERNAME'); $mail->Password = envv('SMTP_PASSWORD'); }
        $encryption = strtolower(trim(envv('SMTP_ENCRYPTION', 'tls')));
        if ($mail->Port === 465 && $encryption === 'tls') $encryption = 'ssl';
        if ($encryption === 'ssl') $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        elseif ($encryption === 'tls') $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        else $mail->SMTPSecure = '';
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(envv('MAIL_FROM_ADDRESS'), envv('MAIL_FROM_NAME', $name));
        $mail->addAddress($to);
        foreach ($attachments as $attachment) { if (isset($attachment['content']) && !empty($attachment['filename'])) $mail->addStringAttachment((string)$attachment['content'], (string)$attachment['filename'], 'base64', (string)($attachment['mime']??'application/pdf')); }
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $message."\n\n".$name.($contact !== '' ? "\n".$contact : '');
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Email delivery failed: '.$e->getMessage());
        return false;
    }
}
