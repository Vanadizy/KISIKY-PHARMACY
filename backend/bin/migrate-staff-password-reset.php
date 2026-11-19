<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$pdo=db();
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_password_resets (user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_staff_password_resets_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_staff_password_resets_expiry(expires_at))");
echo "Staff password reset table is ready.\n";
