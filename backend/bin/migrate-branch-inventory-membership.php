<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$pdo=db();
$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='branch_inventory' AND COLUMN_NAME='is_active'");
$q->execute();
if(!(int)$q->fetchColumn())$pdo->exec('ALTER TABLE branch_inventory ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE AFTER stock_units');
echo "Branch inventory membership migration complete.\n";
