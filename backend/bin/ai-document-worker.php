<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$pdo=db();$limit=max(1,min(100,(int)($argv[1]??10)));$processed=0;
while($processed<$limit){$id=$pdo->query("SELECT id FROM ai_processing_jobs WHERE status='queued' OR (status='retry_pending' AND (locked_until IS NULL OR locked_until<NOW())) OR (status='processing' AND locked_until<NOW()) ORDER BY created_at,id LIMIT 1")->fetchColumn();if(!$id)break;$result=ai_process_job($pdo,(int)$id);$status=strtolower((string)($result['status']??'unknown'));fwrite(STDOUT,'job '.$id.' '.$status.PHP_EOL);$processed++;if($status==='processing')break;}
fwrite(STDOUT,'worker finished; jobs handled='.$processed.PHP_EOL);
