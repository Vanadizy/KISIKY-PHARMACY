<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
try { require __DIR__.'/../routes/api.php'; } catch(Throwable $e) { error_log((string)$e); respond(['error'=>envv('APP_ENV')==='development'?$e->getMessage():'Internal server error'],500); }
