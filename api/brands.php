<?php
/**
 * ATHLETICA — API: Brands Endpoint
 * GET /api/brands.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_require_method('GET');

try {
    $sql = "
        SELECT b.id, b.name, b.slug, b.logo, b.description, b.sort_order,
               COUNT(p.id) AS product_count
        FROM brands b
        LEFT JOIN products p ON p.brand_id = b.id AND p.status = 'active'
        WHERE b.is_active = 1
        GROUP BY b.id
        ORDER BY b.sort_order ASC, b.name ASC
    ";
    $stmt = $pdo->query($sql);
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(static function (array $brand): array {
        return [
            'id' => (int)$brand['id'],
            'name' => $brand['name'],
            'slug' => $brand['slug'],
            'logo' => $brand['logo'],
            'description' => $brand['description'],
            'sort_order' => (int)$brand['sort_order'],
            'product_count' => (int)$brand['product_count'],
        ];
    }, $brands ?: []);

    api_success($formatted);

} catch (PDOException $e) {
    api_error('Database error fetching brands.', 'DB_ERROR', 500);
}
