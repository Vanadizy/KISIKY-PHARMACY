<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
try {
    require __DIR__.'/../routes/api.php';
} catch (DatabaseConnectionException $e) {
    // Connection diagnostics contain no secrets and help hosting support
    // identify whether credentials, database name, driver, or host is wrong.
    respond(['error' => $e->getMessage(), 'code' => 'database_'.$e->reason], 500);
} catch (Throwable $e) {
    error_log('Unhandled API exception: '.get_class($e).' '.$e->getMessage());
    respond(['error' => envv('APP_ENV') === 'development' ? $e->getMessage() : 'Internal server error'], 500);
}
