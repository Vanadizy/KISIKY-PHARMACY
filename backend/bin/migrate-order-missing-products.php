<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$pdo=db();
$q=$pdo->prepare("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='product_id'");
$q->execute();
$nullable=$q->fetchColumn();
if($nullable===false)throw new RuntimeException('order_items.product_id was not found; verify the database schema.');
if($nullable!=='YES')$pdo->exec('ALTER TABLE order_items MODIFY product_id BIGINT UNSIGNED NULL');
echo "Order missing-product migration complete.
";
