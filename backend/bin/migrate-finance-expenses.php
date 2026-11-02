<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$pdo=db();
$pdo->exec("INSERT INTO permissions(code,module_name,label) VALUES('finance.expense','Finance','Record expenses') ON DUPLICATE KEY UPDATE module_name=VALUES(module_name),label=VALUES(label)");
$pdo->exec("CREATE TABLE IF NOT EXISTS expenses (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, expense_reference VARCHAR(50) NOT NULL UNIQUE, category VARCHAR(100) NOT NULL, description VARCHAR(500) NOT NULL, amount DECIMAL(14,2) NOT NULL, expense_date DATE NOT NULL, payment_method VARCHAR(40) NOT NULL DEFAULT 'cash', transaction_reference VARCHAR(120) NULL, recorded_by BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(recorded_by) REFERENCES users(id), INDEX(expense_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Finance expense records and permission are ready.\n";
