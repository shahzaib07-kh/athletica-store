<?php
/**
 * ATHLETICA — API: Product Catalog Endpoint
 * GET /api/products/index.php
 *
 * Supports search (q), category, brand, size, product_type, condition,
 * sorting, and pagination.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_method('GET');

try {
    $search = trim($_GET['q'] ?? $_GET['search'] ?? '');
    $categorySlug = trim($_GET['category'] ?? '');
    $brandSlug = trim($_GET['brand'] ?? '');
    $size = trim($_GET['size'] ?? '');
    $productType = trim($_GET['type'] ?? '');
    $condition = trim($_GET['condition'] ?? '');
    $sort = trim($_GET['sort'] ?? 'newest');
    $tab = trim($_GET['tab'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // Base WHERE conditions
    $where = ["p.status = 'active'"];
    $params = [];

    $tab = trim($_GET['tab'] ?? $_GET['filter'] ?? '');

    // Tab filter for homepage / curated sections
    if ($tab === 'featured') {
        $where[] = "p.is_featured = 1";
    } elseif ($tab === 'imported') {
        $where[] = "p.is_imported = 1 AND p.product_type = 'unique'";
    } elseif ($tab === 'trending') {
        $where[] = "p.is_trending = 1";
    } elseif ($tab === 'bestseller' || $tab === 'best_seller' || $tab === 'bestsellers') {
        $where[] = "p.is_bestseller = 1";
    } elseif ($tab === 'new' || $tab === 'new_arrivals' || $tab === 'newarrivals') {
        $where[] = "p.condition = 'new' AND p.product_type = 'regular'";
        $sort = 'newest';
    }

    // Search query (name, description, sku, category, brand)
    if ($search !== '') {
        $where[] = "(p.name LIKE :search OR p.description LIKE :search OR p.sku LIKE :search OR c.name LIKE :search OR b.name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    // Category & Subcategory filter
    $subcategory = trim($_GET['subcategory'] ?? '');
    if ($categorySlug !== '') {
        if ($categorySlug === 'apparel') {
            if ($subcategory === 'shirts') {
                $where[] = "(c.slug = 'shirts' OR p.name LIKE '%shirt%' OR p.name LIKE '%tee%')";
            } elseif ($subcategory === 'trousers') {
                $where[] = "(c.slug = 'trousers' OR p.name LIKE '%trouser%' OR p.name LIKE '%pant%' OR p.name LIKE '%jogger%')";
            } else {
                $where[] = "(c.slug IN ('apparel', 'shirts', 'trousers') OR c.id = 8)";
            }
        } else {
            $where[] = "c.slug = :cat_slug";
            $params[':cat_slug'] = $categorySlug;
        }
    }

    // Brand filter
    if ($brandSlug !== '') {
        $where[] = "b.slug = :brand_slug";
        $params[':brand_slug'] = $brandSlug;
    }

    // Product type filter
    if (in_array($productType, ['unique', 'regular'], true)) {
        $where[] = "p.product_type = :ptype";
        $params[':ptype'] = $productType;
    }

    // Condition filter
    if (in_array($condition, ['new', 'used', 'refurbished'], true)) {
        $where[] = "p.`condition` = :pcond";
        $params[':pcond'] = $condition;
    }

    // Size filter: match in product_sizes table OR in products.sizes column
    if ($size !== '') {
        $cleanSize = preg_replace('/^(EU\s*|EUR\s*)/i', '', $size);
        $sizeConditions = [];
        
        // 1. Column matching (comma-separated or exact in p.sizes)
        $sizeConditions[] = "FIND_IN_SET(:size_exact, REPLACE(p.sizes, ' ', '')) > 0";
        $params[':size_exact'] = $cleanSize;

        // 2. product_sizes variant table matching
        $sizeConditions[] = "EXISTS (
            SELECT 1 FROM product_sizes ps_chk 
            WHERE ps_chk.product_id = p.id 
              AND ps_chk.is_available = 1 
              AND (
                  ps_chk.size = :size_raw 
                  OR ps_chk.size = :size_eu 
                  OR ps_chk.size = :size_eur
                  OR REPLACE(REPLACE(ps_chk.size, 'EUR ', ''), 'EU ', '') = :size_clean
              )
        )";
        $params[':size_raw'] = $size;
        $params[':size_eu'] = 'EU ' . $cleanSize;
        $params[':size_eur'] = 'EUR ' . $cleanSize;
        $params[':size_clean'] = $cleanSize;

        $where[] = '(' . implode(' OR ', $sizeConditions) . ')';
    }

    $whereSql = implode(' AND ', $where);

    // 1. Get total count for pagination
    $countSql = "
        SELECT COUNT(DISTINCT p.id)
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE {$whereSql}
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalItems / $limit);

    // 2. Sorting (default highlights featured articles first, respecting featured_sort_order, then newest)
    $orderBy = match ($sort) {
        'price_asc' => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'newest' => 'p.created_at DESC, p.id DESC',
        default => ($tab === 'featured')
            ? 'CASE WHEN p.featured_sort_order IS NULL THEN 1 ELSE 0 END, p.featured_sort_order ASC, p.id ASC'
            : 'CASE WHEN p.is_featured = 1 AND p.featured_sort_order IS NOT NULL THEN 0 WHEN p.is_featured = 1 THEN 1 ELSE 2 END, p.featured_sort_order ASC, p.is_featured DESC, p.created_at DESC, p.id DESC',
    };

    // 3. Fetch products
    $sql = "
        SELECT p.id, p.category_id, p.brand_id, p.name, p.slug, p.sku, p.description,
               p.price, p.old_price, p.color, p.`condition`, p.product_type, p.status, 
               p.is_featured, p.advance_payment_percentage, p.featured_sort_order, p.is_trending, p.is_bestseller, p.is_imported, p.origin_country, p.sizes AS sizes_column,
               p.created_at,
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
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = array_map(static function (array $p): array {
        $price = (float)$p['price'];
        $oldPrice = !empty($p['old_price']) ? (float)$p['old_price'] : null;
        $discount = ($oldPrice && $oldPrice > $price)
            ? (int)round(((1 - ($price / $oldPrice)) * 100))
            : 0;

        // Collect sizes from product_sizes or fallback to sizes column
        if (!empty($p['available_sizes_str'])) {
            $sizes = array_map('trim', explode(',', $p['available_sizes_str']));
        } elseif (!empty($p['sizes_column'])) {
            $sizes = array_map('trim', explode(',', $p['sizes_column']));
        } else {
            $sizes = [];
        }

        return [
            'id' => (int)$p['id'],
            'name' => $p['name'],
            'slug' => $p['slug'],
            'sku' => $p['sku'],
            'description' => $p['description'],
            'price' => number_format($price, 2, '.', ''),
            'old_price' => $oldPrice ? number_format($oldPrice, 2, '.', '') : null,
            'discount_percent' => $discount,
            'color' => $p['color'],
            'condition' => $p['condition'],
            'product_type' => $p['product_type'],
            'status' => $p['status'],
            'is_featured' => (int)$p['is_featured'],
            'advance_payment_percentage' => isset($p['advance_payment_percentage']) && $p['advance_payment_percentage'] !== null ? (float)$p['advance_payment_percentage'] : null,
            'featured_sort_order' => $p['featured_sort_order'] !== null ? (int)$p['featured_sort_order'] : null,
            'is_trending' => (int)($p['is_trending'] ?? 0),
            'is_bestseller' => (int)($p['is_bestseller'] ?? 0),
            'is_imported' => (int)($p['is_imported'] ?? 0),
            'origin_country' => !empty($p['origin_country']) ? (string)$p['origin_country'] : null,
            'sizes' => !empty($p['sizes_column']) ? $p['sizes_column'] : implode(',', $sizes),
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

    api_success([
        'products' => $products,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'has_next_page' => $page < $totalPages,
            'has_prev_page' => $page > 1,
        ],
    ]);

} catch (PDOException $e) {
    api_error('Database error querying products catalog.', 'DB_ERROR', 500);
}
