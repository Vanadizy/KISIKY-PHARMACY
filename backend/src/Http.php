<?php
declare(strict_types=1);
function json_input(): array { $data=json_decode(file_get_contents('php://input')?:'[]',true); return is_array($data)?$data:[]; }
function respond(array $data,int $status=200): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function audit(string $action,string $type,?int $id=null,array $details=[]): void { if(isset($_SESSION['active_branch_id']))$details['branch_id']??=(int)$_SESSION['active_branch_id'];if($type==='branch'&&$id!==null)$details['branch_id']=$id;$q=db()->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)'); $q->execute([$_SESSION['user']['id']??null,$action,$type,$id,json_encode($details)]); }
function csrf_check(): void { $sent=$_SERVER['HTTP_X_CSRF_TOKEN']??''; if(!isset($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],$sent))respond(['error'=>'Invalid request token'],419); }
function body_string(array $data,string $key,int $max=255,bool $required=false): string { $v=trim((string)($data[$key]??'')); if(($required&&$v==='')||strlen($v)>$max)respond(['error'=>"Invalid $key"],422); return $v; }
