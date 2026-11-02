<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$pdo=db();
$pdo->exec("INSERT INTO permissions(code,module_name,label) VALUES('products.delete','Products','Delete products'),('users.audit','Users','View user activity logs') ON DUPLICATE KEY UPDATE label=VALUES(label),module_name=VALUES(module_name)");
$pdo->exec("UPDATE permissions SET label='Edit product details and prices' WHERE code='products.edit'");
echo "Product and account activity permissions are ready.\n";
