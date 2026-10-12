<?php
/**
 * ATHLETICA — API: Sizes Aggregator Endpoint
 * GET /api/sizes.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_require_method('GET');

try {
    $sql = "
        SELECT DISTINCT ps.size, MIN(ps.sort_order) AS sort_order
        FROM product_sizes ps
        JOIN products p ON p.id = ps.product_id
        WHERE ps.is_available = 1 AND p.status = 'active'
        GROUP BY ps.size
        ORDER BY sort_order ASC, ps.size ASC
    ";
    $stmt = $pdo->query($sql);
    $sizes = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    api_success($sizes ?: []);

} catch (PDOException $e) {
    api_error('Database error fetching available sizes.', 'DB_ERROR', 500);
}
