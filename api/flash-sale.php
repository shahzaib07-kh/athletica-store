<?php
/**
 * ATHLETICA — REST API: Flash Sale Endpoint
 * GET /api/flash-sale.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_require_method('GET');

try {
    // 1. Check for active flash sale where NOW() is between starts_at and ends_at
    $sql = "
        SELECT id, title, starts_at, ends_at,
               TIMESTAMPDIFF(SECOND, NOW(), ends_at) AS remaining_seconds
        FROM flash_sales
        WHERE is_active = 1
          AND NOW() BETWEEN starts_at AND ends_at
        ORDER BY ends_at ASC
        LIMIT 1
    ";

    $stmt = $pdo->query($sql);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        api_success(null);
    }

    $remainingSeconds = max(0, (int)$sale['remaining_seconds']);
    $flashSaleId = (int)$sale['id'];

    // 2. Fetch associated products
    $prodSql = "
        SELECT p.id, p.category_id, p.brand_id, p.name, p.slug, p.sku, p.price, p.old_price,
               fsp.sale_price, fsp.sort_order,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS primary_image,
               (SELECT GROUP_CONCAT(ps.size ORDER BY ps.sort_order SEPARATOR ', ')
                FROM product_sizes ps
                WHERE ps.product_id = p.id AND ps.is_available = 1) AS available_sizes_str
        FROM flash_sale_products fsp
        JOIN products p ON p.id = fsp.product_id
        WHERE fsp.flash_sale_id = :sale_id
          AND p.status = 'active'
        ORDER BY fsp.sort_order ASC, p.name ASC
    ";

    $prodStmt = $pdo->prepare($prodSql);
    $prodStmt->execute([':sale_id' => $flashSaleId]);
    $rawProducts = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

    $products = array_map(static function (array $p): array {
        $regPrice = (float)$p['price'];
        $salePrice = (float)$p['sale_price'];
        $discount = ($regPrice > 0)
            ? (int)round(((1 - ($salePrice / $regPrice)) * 100))
            : 0;

        $sizes = !empty($p['available_sizes_str'])
            ? array_map('trim', explode(',', $p['available_sizes_str']))
            : [];

        return [
            'id'               => (int)$p['id'],
            'name'             => (string)$p['name'],
            'slug'             => (string)$p['slug'],
            'sku'              => (string)($p['sku'] ?? ''),
            'regular_price'    => number_format($regPrice, 2, '.', ''),
            'sale_price'       => number_format($salePrice, 2, '.', ''),
            'discount_percent' => $discount,
            'primary_image'    => $p['primary_image'] ?? null,
            'available_sizes'  => $sizes,
        ];
    }, $rawProducts ?: []);

    $data = [
        'id'                => $flashSaleId,
        'title'             => (string)$sale['title'],
        'starts_at'         => (string)$sale['starts_at'],
        'ends_at'           => (string)$sale['ends_at'],
        'remaining_seconds' => $remainingSeconds,
        'products'          => $products,
    ];

    api_success($data);

} catch (PDOException $e) {
    // If table doesn't exist or DB error, return null
    api_success(null);
}
