<?php
/**
 * ATHLETICA — Production Database Configuration Template
 *
 * Copy to config/database.php or set corresponding environment variables
 * on the production server (e.g., via systemd, php-fpm pool, or .env).
 *
 * Requirements:
 * - PHP 8.2+
 * - PDO MySQL extension
 * - MySQL 8.0+ / MariaDB 10.5+
 * - utf8mb4 collation (utf8mb4_unicode_ci)
 */

declare(strict_types=1);

// Production Environment Credentials (with secure fallbacks)
$dbHost     = getenv('DB_HOST')     ?: '127.0.0.1';
$dbPort     = getenv('DB_PORT')     ?: '3306';
$dbName     = getenv('DB_NAME')     ?: 'athletica_prod';
$dbUser     = getenv('DB_USER')     ?: 'athletica_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'SECURE_RANDOM_PASSWORD_HERE';
$dbCharset  = 'utf8mb4';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";

$options = [
    // Throw exceptions on SQL errors
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Return associative arrays by default
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Disable native prepared statement emulation for strict type binding & SQLi protection
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Ensure persistent connections for high-throughput performance (optional, default false)
    PDO::ATTR_PERSISTENT         => false,
    // Connection timeout (seconds)
    PDO::ATTR_TIMEOUT            => 5,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);
} catch (PDOException $e) {
    // In production, log error privately and never expose raw DSN or password to clients
    error_log('ATHLETICA Database Connection Error: ' . $e->getMessage());

    if (php_sapi_name() !== 'cli' && str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=UTF-8', true, 500);
        echo json_encode([
            'success' => false,
            'error'   => [
                'code'    => 'DATABASE_CONNECTION_ERROR',
                'message' => 'Service temporarily unavailable. Please try again later.',
            ],
            'meta'    => ['timestamp' => gmdate('Y-m-d\TH:i:s\Z')],
        ]);
        exit;
    }

    // Generic friendly HTML response for storefront/admin in production
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>500 — Service Unavailable</title></head><body style="background:#0f0f0f;color:#e5e5e5;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;"><div style="text-align:center;"><h1>ATHLETICA</h1><p>Service temporarily unavailable. Our engineers are investigating.</p></div></body></html>';
    exit;
}
