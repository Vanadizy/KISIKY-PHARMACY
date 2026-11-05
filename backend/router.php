<?php
declare(strict_types=1);

$publicRoot = realpath(__DIR__ . '/public');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rawurldecode($requestPath)), DIRECTORY_SEPARATOR);
$requestedFile = $publicRoot ? realpath($publicRoot . DIRECTORY_SEPARATOR . $relativePath) : false;

if ($publicRoot && $requestedFile && is_file($requestedFile) && str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR)) {
    return false;
}

require __DIR__ . '/public/index.php';
