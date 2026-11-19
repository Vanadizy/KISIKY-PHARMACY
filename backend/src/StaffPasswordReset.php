<?php
declare(strict_types=1);

function staff_password_reset_route(string $path,string $method): ?array {
    if($path==='/staff/password-reset/request'&&$method==='POST'){
        csrf_check();$in=json_input();$email=body_string($in,'email',190,true);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))return ['status'=>422,'body'=>['error'=>'Enter a valid staff email address.','field'=>'email']];
        $q=db()->prepare("SELECT id,full_name,email FROM users WHERE email=? AND status='active' LIMIT 1");$q->execute([$email]);$user=$q->fetch();
        if(!$user)return ['status'=>404,'body'=>['error'=>'No active staff account was found for this email address.','field'=>'email']];
        if($user){
            $q=db()->prepare('SELECT user_id FROM staff_password_resets WHERE user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 MINUTE) LIMIT 1');$q->execute([(int)$user['id']]);
            if(!$q->fetchColumn()){
                if(!email_delivery_configured())return ['status'=>503,'body'=>['error'=>'Password reset email is not available right now. Contact your administrator.','field'=>'email']];
                $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$pdo=db();
                $pdo->prepare('INSERT INTO staff_password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),created_at=CURRENT_TIMESTAMP')->execute([(int)$user['id'],$hash]);
                $origins=array_values(array_filter(array_map('trim',explode(',',envv('FRONTEND_ORIGIN','')))));$frontend=rtrim($origins[0]??envv('APP_URL','https://kisikypharmacy.com'),'/');$url=$frontend.'/staffs/?token='.rawurlencode($token);$safeUrl=htmlspecialchars($url,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$safeName=htmlspecialchars((string)$user['full_name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
                $preview='<p>Hello '.$safeName.',</p><p>Use this link within one hour to reset your pharmacy staff password:</p><p><a href="'.$safeUrl.'" style="display:inline-block;padding:11px 16px;border-radius:7px;background:#087967;color:#fff;text-decoration:none;font-weight:bold">Reset staff password</a></p><p>If you did not request this, contact your administrator.</p>';
                if(!send_branded_email((string)$user['email'],'Reset your pharmacy staff password','Hello '.$user['full_name'].' — use this link within one hour to reset your password: '.$url,[],$preview)){$pdo->prepare('DELETE FROM staff_password_resets WHERE user_id=?')->execute([(int)$user['id']]);return ['status'=>503,'body'=>['error'=>'The reset email could not be sent. Check the email address or contact your administrator.','field'=>'email']];}
            }
        }
        return ['status'=>200,'body'=>['ok'=>true,'message'=>'Password reset instructions have been sent. Check your inbox and spam folder.']];
    }
    if($path==='/staff/password-reset/complete'&&$method==='POST'){
        csrf_check();$in=json_input();$token=body_string($in,'token',128,true);$password=(string)($in['password']??'');
        if(!preg_match('/^[a-f0-9]{64}$/',$token))return ['status'=>422,'body'=>['error'=>'This reset link is invalid or expired. Request a new one.','field'=>'password']];
        if(strlen($password)<12)return ['status'=>422,'body'=>['error'=>'Password must be at least 12 characters.','field'=>'password']];
        $pdo=db();$pdo->beginTransaction();try{$q=$pdo->prepare('SELECT user_id FROM staff_password_resets WHERE token_hash=? AND expires_at>NOW() FOR UPDATE');$q->execute([hash('sha256',$token)]);$userId=$q->fetchColumn();if(!$userId){$pdo->rollBack();return ['status'=>422,'body'=>['error'=>'This reset link is invalid or expired. Request a new one.','field'=>'password']];}$pdo->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=? AND status=\'active\'')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$userId]);$pdo->prepare('DELETE FROM staff_password_resets WHERE user_id=?')->execute([(int)$userId]);$pdo->commit();audit('auth.password.reset.complete','user',(int)$userId,['delivery'=>'email']);return ['status'=>200,'body'=>['ok'=>true,'message'=>'Password updated. You can now sign in.']];}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    return null;
}
