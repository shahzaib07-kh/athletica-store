<?php
/**
 * ATHLETICA — API: Categories Endpoint
 * GET /api/categories.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_require_method('GET');

try {
    $sql = "
        SELECT c.id, c.name, c.slug, c.description, c.image, c.sort_order,
               COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.sort_order ASC, c.name ASC
    ";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(static function (array $cat): array {
        return [
            'id' => (int)$cat['id'],
            'name' => $cat['name'],
            'slug' => $cat['slug'],
            'description' => $cat['description'],
            'image' => $cat['image'],
            'sort_order' => (int)$cat['sort_order'],
            'product_count' => (int)$cat['product_count'],
        ];
    }, $categories ?: []);

    api_success($formatted);

} catch (PDOException $e) {
    api_error('Database error fetching categories.', 'DB_ERROR', 500);
}
