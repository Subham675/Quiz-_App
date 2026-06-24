<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Base path the app is served from, derived from APP_URL in .env.
// Defaults to '' (root) if APP_URL has no path component, e.g. https://example.com
if (!defined('BASE_PATH')) {
    $appUrlPath = parse_url($_ENV['APP_URL'] ?? '', PHP_URL_PATH) ?? '';
    define('BASE_PATH', rtrim($appUrlPath, '/'));
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES         => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Service temporarily unavailable. Please try again later.');
        }
    }
    return $pdo;
}