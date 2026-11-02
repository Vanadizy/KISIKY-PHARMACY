<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$pdo=db();
$columns=$pdo->query("SHOW COLUMNS FROM invoices LIKE 'invoice_status'")->fetchAll();
if(!$columns){$pdo->exec("ALTER TABLE invoices ADD COLUMN invoice_status ENUM('issued','void') NOT NULL DEFAULT 'issued' AFTER total");}
echo "POS invoice void status is ready.\n";
