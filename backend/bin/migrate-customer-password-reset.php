<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
db()->exec('CREATE TABLE IF NOT EXISTS customer_password_resets (
    customer_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_customer_password_resets_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_password_resets_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
echo "Customer password reset migration complete.\n";
