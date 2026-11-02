<?php
declare(strict_types=1);
$base = dirname(__DIR__, 2);
ini_set('log_errors', '1');
ini_set('error_log', $base . '/backend/storage/php-error.log');
if (is_file($base . '/.env')) { foreach (file($base . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) { $line=trim($line); if ($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue; [$key,$value]=explode('=',$line,2); $_ENV[trim($key)]=trim($value," \t\n\r\0\x0B\"'"); } }
function envv(string $key,string $default=''): string { return (string)($_ENV[$key] ?? getenv($key) ?: $default); }
if (PHP_SAPI !== 'cli') { $origin=$_SERVER['HTTP_ORIGIN']??''; $allowed=array_filter(array_map('trim',explode(',',envv('FRONTEND_ORIGIN','http://localhost:5173,http://127.0.0.1:5173')))); if($origin!==''&&in_array($origin,$allowed,true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Credentials: true');header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');} header('Content-Type: application/json; charset=utf-8'); if (($_SERVER['REQUEST_METHOD'] ?? '')==='OPTIONS') { http_response_code(204); exit; } }
ini_set('session.use_strict_mode','1'); session_name(envv('SESSION_NAME','pharmacy_session')); session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']); session_start();
require_once __DIR__.'/database.php'; require_once __DIR__.'/../src/Http.php'; require_once __DIR__.'/../src/Auth.php';
require_once __DIR__.'/../src/Notifications.php';
