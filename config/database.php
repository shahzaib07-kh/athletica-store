<?php
/**
 * ATHLETICA — Database Configuration
 * 
 * Centralized PDO connection for the entire application.
 * All database credentials are defined HERE ONLY.
 *
 * Usage:
 *   require __DIR__ . '/../config/database.php';
 *   // $pdo is now available as a PDO instance
 *
 * SECURITY:
 *   - This file must NEVER be accessible from the browser.
 *   - Place it outside the public web root if your hosting allows it.
 *   - Never commit production credentials to version control.
 *   - Never echo or print credentials in error messages.
 */

// ============================================================
// DATABASE CREDENTIALS
// Change these values to match your environment.
// ============================================================

define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');              // Database server (localhost for cPanel)
define('DB_PORT',    getenv('DB_PORT') ?: '3306');                   // MySQL default port
define('DB_NAME',    getenv('DB_NAME') ?: 'athlvikr_athletica_db');  // Live cPanel Database name
define('DB_USER',    getenv('DB_USER') ?: 'athlvikr_athletica_07');  // Live cPanel Database username
define('DB_PASS',    getenv('DB_PASS') !== false && getenv('DB_PASS') !== '' ? getenv('DB_PASS') : 'athletica@07'); // Live cPanel Database password
define('DB_CHARSET', 'utf8mb4');                                     // Character set (must match schema.sql)

// ============================================================
// PDO CONNECTION
// ============================================================

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    DB_HOST,
    DB_PORT,
    DB_NAME,
    DB_CHARSET
);

$pdoOptions = [
    // Throw exceptions on errors instead of silent failures
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

    // Return rows as associative arrays by default
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // Use real prepared statements (not emulated) for security
    PDO::ATTR_EMULATE_PREPARES   => false,

    // Preserve native data types (int stays int, not string)
    PDO::ATTR_STRINGIFY_FETCHES  => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
} catch (PDOException $e) {
    // --------------------------------------------------------
    // Log the real error internally (safe from browser exposure)
    // --------------------------------------------------------
    error_log('ATHLETICA DB Connection Failed: ' . $e->getMessage());

    http_response_code(503);

    // Provide actionable diagnostic guidance in local development environments
    $isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'], true)
        || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        || php_sapi_name() === 'cli';

    if ($isLocal) {
        $errorCode = (int)$e->getCode();
        $rawMessage = $e->getMessage();

        if ($errorCode === 2002 || str_contains($rawMessage, 'Connection refused') || str_contains($rawMessage, 'actively refused')) {
            exit('Database Connection Error (Local Dev): MySQL/MariaDB server is not running or not listening on port ' . htmlspecialchars(DB_PORT, ENT_QUOTES, 'UTF-8') . '. Please ensure MySQL service/daemon is running.');
        }

        if ($errorCode === 1049 || str_contains($rawMessage, 'Unknown database')) {
            exit('Database Configuration Error (Local Dev): Database \'' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '\' does not exist. Please check database configuration or import schema.');
        }

        if ($errorCode === 1045 || str_contains($rawMessage, 'Access denied')) {
            exit('Database Authentication Error (Local Dev): Invalid database credentials for user \'' . htmlspecialchars(DB_USER, ENT_QUOTES, 'UTF-8') . '\'.');
        }

        exit('Database Error (Local Dev): Database service is unavailable. Check your local MySQL configuration.');
    }

    // Generic safe error for production (no credentials or internal details leaked)
    exit('Service temporarily unavailable. Please try again later.');
}
