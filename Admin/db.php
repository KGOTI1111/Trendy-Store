<?php
declare(strict_types=1);

$httpsEnabled = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name('TRENDY_ADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $httpsEnabled,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function admin_database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('TRENDY_DB_HOST') ?: '127.0.0.1';
    $database = getenv('TRENDY_DB_NAME') ?: 'trendy_store';
    $username = getenv('TRENDY_DB_USER') ?: 'root';
    $password = getenv('TRENDY_DB_PASS');
    if ($password === false) {
        $password = '';
    }

    $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
    $connection = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    return $connection;
}

