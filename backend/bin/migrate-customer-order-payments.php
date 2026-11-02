<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$pdo=db();
$q=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders'");
$q->execute();
$columns=$q->fetchAll(PDO::FETCH_COLUMN,0);
if(!in_array('payment_status',$columns,true))$pdo->exec("ALTER TABLE orders ADD COLUMN payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid' AFTER status");
if(!in_array('paid_at',$columns,true))$pdo->exec('ALTER TABLE orders ADD COLUMN paid_at DATETIME NULL AFTER payment_status');
if(!in_array('paid_by',$columns,true))$pdo->exec('ALTER TABLE orders ADD COLUMN paid_by BIGINT UNSIGNED NULL AFTER paid_at');
$fk=$pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='paid_by' AND REFERENCED_TABLE_NAME='users'")->fetchColumn();
if(!(int)$fk)$pdo->exec('ALTER TABLE orders ADD CONSTRAINT fk_orders_paid_by FOREIGN KEY(paid_by) REFERENCES users(id) ON DELETE SET NULL');
$pdo->exec("CREATE TABLE IF NOT EXISTS customer_order_payments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL UNIQUE, amount DECIMAL(14,2) NOT NULL, payment_method VARCHAR(40) NOT NULL, transaction_reference VARCHAR(120) NULL, recorded_by BIGINT UNSIGNED NOT NULL, paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(order_id) REFERENCES orders(id), FOREIGN KEY(recorded_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_invoices (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL UNIQUE, invoice_reference VARCHAR(50) NOT NULL UNIQUE, total DECIMAL(14,2) NOT NULL, created_by BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(order_id) REFERENCES orders(id), FOREIGN KEY(created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Customer order payment and invoice migration complete.\n";
