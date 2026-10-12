<?php
/**
 * ATHLETICA — REST API Common Bootstrap & Helper
 *
 * Sets JSON response headers, handles CORS preflight requests,
 * loads database connection and shared utility functions, and provides
 * standard JSON envelope responses.
 */

declare(strict_types=1);

// 1. Set JSON headers & CORS headers
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
// Allow local Next.js dev server and configured production domains
$allowedOrigins = [
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:8000',
    'https://athletica.pk',
    'https://www.athletica.pk',
];

$envOrigins = getenv('CORS_ALLOWED_ORIGINS');
if ($envOrigins) {
    $parsed = array_filter(array_map('trim', explode(',', $envOrigins)));
    $allowedOrigins = array_merge($allowedOrigins, $parsed);
}

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Origin, Accept');
header('Access-Control-Max-Age: 86400');

// Handle HTTP OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 2. Load Core Dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart-helper.php';
require_once __DIR__ . '/../includes/pricing-helper.php';

/**
 * Sends a successful JSON response and terminates script execution.
 *
 * @param mixed $data Response payload
 * @param int $statusCode HTTP status code (default: 200)
 * @param array $extraMeta Optional additional metadata
 * @return never
 */
function api_success(mixed $data, int $statusCode = 200, array $extraMeta = []): void
{
    http_response_code($statusCode);
    $response = [
        'success' => true,
        'data' => $data,
        'meta' => array_merge([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $extraMeta),
    ];
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sends a standardized error JSON response and terminates script execution.
 *
 * @param string $message User-friendly error message
 * @param string $code Machine-readable error code
 * @param int $statusCode HTTP status code (default: 400)
 * @param array $fields Field-specific validation errors
 * @return never
 */
function api_error(string $message, string $code = 'BAD_REQUEST', int $statusCode = 400, array $fields = []): void
{
    http_response_code($statusCode);
    $response = [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
            'fields' => (object)$fields,
        ],
        'meta' => [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ],
    ];
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Parses and returns the decoded JSON payload from php://input.
 *
 * @return array Decoded associative array
 */
function api_get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        api_error('Invalid JSON payload in request body.', 'INVALID_JSON', 400);
    }
    return $decoded;
}

/**
 * Asserts the request method matches the expected HTTP verb.
 *
 * @param string $expectedMethod (e.g. 'GET', 'POST')
 */
function api_require_method(string $expectedMethod): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($expectedMethod)) {
        api_error(
            sprintf('Method %s not allowed. Expected %s.', $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN', $expectedMethod),
            'METHOD_NOT_ALLOWED',
            405
        );
    }
}

if (!function_exists('generate_order_number')) {
    /**
     * Generates a collision-resistant unique order number in format: ATH-YYYYMMDD-NNNN
     *
     * @param PDO $pdo
     * @return string
     */
    function generate_order_number(PDO $pdo): string
    {
        $datePrefix = 'ATH-' . date('Ymd') . '-';
        for ($i = 0; $i < 10; $i++) {
            $randomSuffix = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $datePrefix . $randomSuffix;
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = :num LIMIT 1");
            $stmt->execute([':num' => $candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
        }
        return $datePrefix . substr((string)time(), -4);
    }
}

