<?php
/**
 * ATHLETICA — API: Single Product Detail Endpoint
 * GET /api/products/detail.php?slug=nike-air-max-90 OR ?id=1
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_method('GET');

try {
    $slug = trim($_GET['slug'] ?? '');
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($slug === '' && !$id) {
        api_error('Product slug or id is required.', 'INVALID_PARAMETERS', 400);
    }

    if ($slug !== '') {
        $stmt = $pdo->prepare("
            SELECT p.*,
                   c.name AS category_name, c.slug AS category_slug,
                   b.name AS brand_name, b.slug AS brand_slug, b.logo AS brand_logo
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id
            WHERE p.slug = :slug AND p.status != 'inactive'
            LIMIT 1
        ");
        $stmt->execute([':slug' => $slug]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*,
                   c.name AS category_name, c.slug AS category_slug,
                   b.name AS brand_name, b.slug AS brand_slug, b.logo AS brand_logo
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id
            WHERE p.id = :id AND p.status != 'inactive'
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
    }

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        api_error('Product not found.', 'NOT_FOUND', 404);
    }

    $productId = (int)$product['id'];

    // 1. Fetch Images
    $imgStmt = $pdo->prepare("
        SELECT id, image_path, alt_text, is_primary, sort_order
        FROM product_images
        WHERE product_id = :pid
        ORDER BY is_primary DESC, sort_order ASC, id ASC
    ");
    $imgStmt->execute([':pid' => $productId]);
    $images = array_map(static function (array $img): array {
        return [
            'id' => (int)$img['id'],
            'image_path' => $img['image_path'],
            'alt_text' => $img['alt_text'],
            'is_primary' => (int)$img['is_primary'],
            'sort_order' => (int)$img['sort_order'],
        ];
    }, $imgStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // 2. Fetch Sizes
    $sizeStmt = $pdo->prepare("
        SELECT id, size, is_available, sort_order
        FROM product_sizes
        WHERE product_id = :pid
        ORDER BY sort_order ASC, id ASC
    ");
    $sizeStmt->execute([':pid' => $productId]);
    $sizes = array_map(static function (array $s): array {
        return [
            'id' => (int)$s['id'],
            'size' => $s['size'],
            'is_available' => (int)$s['is_available'],
            'sort_order' => (int)$s['sort_order'],
        ];
    }, $sizeStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $price = (float)$product['price'];
    $oldPrice = !empty($product['old_price']) ? (float)$product['old_price'] : null;
    $discount = ($oldPrice && $oldPrice > $price)
        ? (int)round(((1 - ($price / $oldPrice)) * 100))
        : 0;

    // Load WhatsApp number from site_settings
    $settingsStmt = $pdo->query("SELECT whatsapp_number FROM site_settings LIMIT 1");
    $rawWa = $settingsStmt->fetchColumn() ?: '+923001234567';
    $normalizedWa = normalize_whatsapp_number((string)$rawWa);

    $defaultSize = '';
    foreach ($sizes as $sz) {
        if ($sz['is_available'] === 1) {
            $defaultSize = $sz['size'];
            break;
        }
    }

    $inquiryMsg = sprintf(
        "Hello ATHLETICA, I want to inquire about \"%s\"%s (Price: Rs. %s).",
        $product['name'],
        $defaultSize ? ' (Size: ' . $defaultSize . ')' : '',
        number_format($price, 2)
    );
    $whatsappUrl = "https://wa.me/{$normalizedWa}?text=" . rawurlencode($inquiryMsg);

    $data = [
        'id' => $productId,
        'name' => $product['name'],
        'slug' => $product['slug'],
        'sku' => $product['sku'],
        'description' => $product['description'],
        'price' => number_format($price, 2, '.', ''),
        'old_price' => $oldPrice ? number_format($oldPrice, 2, '.', '') : null,
        'discount_percent' => $discount,
        'color' => $product['color'],
        'condition' => $product['condition'],
        'condition_notes' => $product['condition_notes'] ?? null,
        'features' => $product['features'] ?? null,
        'product_type' => $product['product_type'],
        'status' => $product['status'],
        'is_featured' => (int)$product['is_featured'],
        'advance_payment_percentage' => isset($product['advance_payment_percentage']) && $product['advance_payment_percentage'] !== null ? (float)$product['advance_payment_percentage'] : null,
        'is_imported' => (int)($product['is_imported'] ?? 0),
        'origin_country' => !empty($product['origin_country']) ? (string)$product['origin_country'] : null,
        'category' => [
            'id' => (int)$product['category_id'],
            'name' => $product['category_name'] ?? 'General',
            'slug' => $product['category_slug'] ?? '',
        ],
        'brand' => !empty($product['brand_id']) ? [
            'id' => (int)$product['brand_id'],
            'name' => $product['brand_name'] ?? '',
            'slug' => $product['brand_slug'] ?? '',
            'logo' => $product['brand_logo'] ?? null,
        ] : null,
        'images' => $images,
        'sizes' => $sizes,
        'whatsapp_inquiry_url' => $whatsappUrl,
    ];

    api_success($data);

} catch (PDOException $e) {
    api_error('Database error fetching product details.', 'DB_ERROR', 500);
}
