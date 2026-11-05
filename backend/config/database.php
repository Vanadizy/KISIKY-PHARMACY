<?php
declare(strict_types=1);

/**
 * Give connection failures a safe, actionable classification without ever
 * exposing credentials or connection strings to API clients or logs.
 */
final class DatabaseConnectionException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}

function classify_database_connection_error(PDOException $e): DatabaseConnectionException
{
    $info = $e->errorInfo ?? null;
    $sqlState = is_array($info) ? (string)($info[0] ?? $e->getCode()) : (string)$e->getCode();
    $driverCode = is_array($info) ? (int)($info[1] ?? 0) : 0;
    $message = strtolower($e->getMessage());

    if (str_contains($message, 'could not find driver')) {
        return new DatabaseConnectionException('driver_missing', 'The server is missing the PHP MySQL PDO driver (pdo_mysql). Ask the host to enable it.', $e);
    }
    if ($driverCode === 1049 || str_contains($message, 'unknown database')) {
        return new DatabaseConnectionException('database_not_found', 'MySQL is reachable, but the configured database was not found. Verify DB_NAME in the server .env and create/import the database in the hosting panel.', $e);
    }
    if ($driverCode === 1045 || str_contains($message, 'access denied for user')) {
        return new DatabaseConnectionException('access_denied', 'MySQL rejected the configured login. Verify DB_USER and DB_PASSWORD, confirm the user is assigned to DB_NAME with privileges, and check the host value.', $e);
    }
    if ($driverCode === 2002 || $driverCode === 2003 || str_contains($message, 'connection refused') || str_contains($message, 'name or service not known')) {
        return new DatabaseConnectionException('server_unreachable', 'The MySQL server could not be reached. Verify DB_HOST and DB_PORT with the hosting provider.', $e);
    }

    return new DatabaseConnectionException('connection_failed', 'The application could not connect to MySQL. Check the hosting PHP error log and database settings.', $e);
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;

    $host = envv('DB_HOST', '127.0.0.1');
    $port = envv('DB_PORT', '3306');
    $name = envv('DB_NAME', 'pharmacy');
    $dsn = 'mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, envv('DB_USER', 'root'), envv('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        $classified = classify_database_connection_error($e);
        // Log only the safe classification and SQLSTATE/driver code, not the
        // raw exception (which may include usernames, hostnames, or DSN data).
        $info = $e->errorInfo ?? null;
        $sqlState = is_array($info) ? (string)($info[0] ?? $e->getCode()) : (string)$e->getCode();
        $driverCode = is_array($info) ? (int)($info[1] ?? 0) : 0;
        error_log('Database connection failure ['.$classified->reason.']; SQLSTATE '.$sqlState.'; driver code '.$driverCode.'.');
        throw $classified;
    }
}
