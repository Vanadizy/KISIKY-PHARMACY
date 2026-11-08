<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$days=max(1,min(3650,(int)envv('AI_DOCUMENT_RETENTION_DAYS','180')));
$pdo=db();$q=$pdo->prepare("SELECT id,storage_path FROM ai_document_uploads WHERE status<>'retained' AND created_at<DATE_SUB(NOW(),INTERVAL ? DAY)");$q->execute([$days]);$rows=$q->fetchAll();$base=realpath(dirname(__DIR__).'/storage/ai-documents');$removed=0;
foreach($rows as $row){$file=$base?realpath($base.'/'.basename((string)$row['storage_path'])):false;if($base&&$file&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)&&is_file($file)){if(!unlink($file))continue;$removed++;}$pdo->prepare("UPDATE ai_document_uploads SET status='retained',storage_path='[expired-retention]' WHERE id=?")->execute([(int)$row['id']]);}
echo 'Purged '.$removed.' document files older than '.$days.' days; extraction and usage history remains. Run this script periodically from the hosting cron manager. '.count($rows)." record(s) reviewed.\n";
