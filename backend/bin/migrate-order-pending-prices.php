<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$pdo=db();
$columns=[];
$q=$pdo->prepare("SELECT COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME IN ('box_price_snapshot','line_total')");
$q->execute();
foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$columns[$row['COLUMN_NAME']]=$row['IS_NULLABLE'];
if(!isset($columns['box_price_snapshot'],$columns['line_total']))throw new RuntimeException('Order item price columns were not found; verify the database schema.');
$changes=[];
if($columns['box_price_snapshot']!=='YES')$changes[]='MODIFY box_price_snapshot DECIMAL(14,2) NULL';
if($columns['line_total']!=='YES')$changes[]='MODIFY line_total DECIMAL(14,2) NULL';
if($changes)$pdo->exec('ALTER TABLE order_items '.implode(', ',$changes));
echo "Pending-price customer order migration complete.\n";
