<?php
/**
 * ATHLETICA — API: Related Products Endpoint
 * GET /api/products/related.php?id=1&category_id=2&brand_id=1
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_method('GET');

try {
    $currentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
    $categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: 0;
    $brandId = filter_input(INPUT_GET, 'brand_id', FILTER_VALIDATE_INT) ?: null;
    $limit = max(1, min(12, (int)($_GET['limit'] ?? 4)));

    if ($categoryId <= 0 && !$brandId) {
        api_error('Valid category_id or brand_id is required.', 'INVALID_PARAMETERS', 400);
    }

    $where = ["p.status = 'active'", "p.id != :current_id"];
    $params = [':current_id' => $currentId];

    if ($categoryId > 0 && $brandId) {
        $where[] = "(p.category_id = :cat_id OR p.brand_id = :brand_id)";
        $params[':cat_id'] = $categoryId;
        $params[':brand_id'] = $brandId;
    } elseif ($categoryId > 0) {
        $where[] = "p.category_id = :cat_id";
        $params[':cat_id'] = $categoryId;
    } else {
        $where[] = "p.brand_id = :brand_id";
        $params[':brand_id'] = $brandId;
    }

    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT p.id, p.category_id, p.brand_id, p.name, p.slug, p.sku, p.description,
               p.price, p.old_price, p.color, p.`condition`, p.product_type, p.status, p.is_featured,
               c.name AS category_name, c.slug AS category_slug,
               b.name AS brand_name, b.slug AS brand_slug, b.logo AS brand_logo,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS primary_image,
               (SELECT GROUP_CONCAT(ps.size ORDER BY ps.sort_order SEPARATOR ', ')
                FROM product_sizes ps 
                WHERE ps.product_id = p.id AND ps.is_available = 1) AS available_sizes_str
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE {$whereSql}
        GROUP BY p.id
        ORDER BY p.is_featured DESC, p.created_at DESC
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = array_map(static function (array $p): array {
        $price = (float)$p['price'];
        $oldPrice = !empty($p['old_price']) ? (float)$p['old_price'] : null;
        $discount = ($oldPrice && $oldPrice > $price)
            ? (int)round(((1 - ($price / $oldPrice)) * 100))
            : 0;

        $sizes = !empty($p['available_sizes_str'])
            ? array_map('trim', explode(',', $p['available_sizes_str']))
            : [];

        return [
            'id' => (int)$p['id'],
            'name' => $p['name'],
            'slug' => $p['slug'],
            'sku' => $p['sku'],
            'price' => number_format($price, 2, '.', ''),
            'old_price' => $oldPrice ? number_format($oldPrice, 2, '.', '') : null,
            'discount_percent' => $discount,
            'color' => $p['color'],
            'condition' => $p['condition'],
            'product_type' => $p['product_type'],
            'status' => $p['status'],
            'is_featured' => (int)$p['is_featured'],
            'category' => [
                'id' => (int)$p['category_id'],
                'name' => $p['category_name'] ?? 'General',
                'slug' => $p['category_slug'] ?? '',
            ],
            'brand' => !empty($p['brand_id']) ? [
                'id' => (int)$p['brand_id'],
                'name' => $p['brand_name'] ?? '',
                'slug' => $p['brand_slug'] ?? '',
                'logo' => $p['brand_logo'] ?? null,
            ] : null,
            'primary_image' => $p['primary_image'] ?? null,
            'available_sizes' => $sizes,
        ];
    }, $rows ?: []);

    api_success($products);

} catch (PDOException $e) {
    api_error('Database error fetching related products.', 'DB_ERROR', 500);
}
