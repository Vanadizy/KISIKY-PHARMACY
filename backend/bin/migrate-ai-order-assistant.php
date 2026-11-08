<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$pdo=db();
try{
    foreach(['007_ai_order_assistant.sql','008_ai_processing_jobs.sql'] as $file){$migration=dirname(__DIR__).'/migrations/'.$file;if(!is_file($migration))throw new RuntimeException('Required AI migration is missing: '.$file);$sql=file_get_contents($migration);$statements=preg_split('/;\s*(?:\R|$)/',$sql,-1,PREG_SPLIT_NO_EMPTY)?:[];foreach($statements as $statement){$statement=preg_replace('/^\s*--[^\r\n]*(?:\R|$)/m','',$statement)??$statement;$statement=trim($statement);if($statement!=='')$pdo->exec($statement);}echo 'Applied '.$file.PHP_EOL;}
    echo "AI Order Assistant database migrations complete.\n";
}catch(Throwable $e){error_log('AI Order Assistant migration failed: '.get_class($e).' '.$e->getMessage());fwrite(STDERR,"AI Order Assistant migration failed. Check backend/storage/php-error.log for details.\n");exit(1);}
