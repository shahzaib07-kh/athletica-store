<?php
/**
 * ATHLETICA — Shop / Catalog Page
 * Browse all active products with search and sorting
 */

$pageTitle = 'Shop All Products — ATHLETICA';
$pageDescription = 'Browse the complete catalog of authentic imported sneakers, shoes, caps, shirts, and athletic wear.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// Sanitize query params
$searchQuery = trim($_GET['search'] ?? $_GET['q'] ?? '');
$brandQuery = trim($_GET['brand'] ?? '');
$sizeQuery = trim($_GET['size'] ?? '');
$categoryQuery = trim($_GET['category'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

// Build SQL query
$sql = "
    SELECT DISTINCT p.*, 
           c.name AS category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
           (SELECT GROUP_CONCAT(ps.size ORDER BY ps.sort_order SEPARATOR ', ')
            FROM product_sizes ps 
            WHERE ps.product_id = p.id AND ps.is_available = 1) AS available_sizes
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN brands b ON b.id = p.brand_id
    LEFT JOIN product_sizes ps_filter ON ps_filter.product_id = p.id AND ps_filter.is_available = 1
    WHERE p.status = 'active'
";

$params = [];

if ($searchQuery !== '') {
    $sql .= " AND (p.name LIKE :search OR p.description LIKE :search OR c.name LIKE :search OR b.name LIKE :search)";
    $params[':search'] = '%' . $searchQuery . '%';
}

if ($brandQuery !== '') {
    $sql .= " AND (b.slug = :brand OR b.name = :brand_name)";
    $params[':brand'] = strtolower($brandQuery);
    $params[':brand_name'] = $brandQuery;
}

if ($sizeQuery !== '') {
    $sql .= " AND ps_filter.size = :size";
    $params[':size'] = $sizeQuery;
}

if ($categoryQuery !== '') {
    $sql .= " AND (c.slug = :category OR c.name = :category_name)";
    $params[':category'] = strtolower($categoryQuery);
    $params[':category_name'] = $categoryQuery;
}

// Sorting logic
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $products = [];
}
?>

<main class="section-padding">
    <div class="container">
        <!-- Breadcrumbs -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb">
            <a href="index.php">Home</a>
            <span class="breadcrumb-separator">/</span>
            <span style="color: #ffffff;">Shop All</span>
        </nav>

        <!-- Section Header -->
        <div class="section-header">
            <div>
                <h1 class="section-title">
                    <?php if ($searchQuery !== ''): ?>
                        Search: "<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
                    <?php elseif ($brandQuery !== ''): ?>
                        Brand: <?= htmlspecialchars(ucfirst($brandQuery), ENT_QUOTES, 'UTF-8') ?>
                    <?php elseif ($sizeQuery !== ''): ?>
                        Size: <?= htmlspecialchars($sizeQuery, ENT_QUOTES, 'UTF-8') ?>
                    <?php elseif ($categoryQuery !== ''): ?>
                        Category: <?= htmlspecialchars(ucfirst($categoryQuery), ENT_QUOTES, 'UTF-8') ?>
                    <?php else: ?>
                        All Products
                    <?php endif; ?>
                </h1>
                <p class="section-subtitle">Showing <?= count($products) ?> in-stock athletic items</p>
            </div>
        </div>

        <!-- Catalog Toolbar -->
        <div class="catalog-toolbar">
            <form action="shop.php" method="GET" style="display: flex; gap: 8px; flex: 1; max-width: 400px;">
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" 
                       placeholder="Search products..." 
                       style="flex: 1; padding: 8px 12px; background: var(--bg-surface); border: 1px solid var(--border-medium); border-radius: var(--radius-sm); color: #fff; font-size: 0.85rem;">
                <?php if ($brandQuery !== ''): ?>
                    <input type="hidden" name="brand" value="<?= htmlspecialchars($brandQuery, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <?php if ($sizeQuery !== ''): ?>
                    <input type="hidden" name="size" value="<?= htmlspecialchars($sizeQuery, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <?php if ($categoryQuery !== ''): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($categoryQuery, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <?php if ($sort !== 'newest'): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.8rem;">Search</button>
            </form>

            <!-- Sort Form -->
            <form action="shop.php" method="GET" id="sort_form" style="display: flex; align-items: center; gap: 8px;">
                <?php if ($searchQuery !== ''): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <?php if ($brandQuery !== ''): ?>
                    <input type="hidden" name="brand" value="<?= htmlspecialchars($brandQuery, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <?php if ($sizeQuery !== ''): ?>
                    <input type="hidden" name="size" value="<?= htmlspecialchars($sizeQuery, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <?php if ($categoryQuery !== ''): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($categoryQuery, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <label for="sort_select" style="font-size: 0.85rem; color: var(--text-secondary);">Sort by:</label>
                <select name="sort" id="sort_select" class="catalog-sort-select" onchange="document.getElementById('sort_form').submit();">
                    <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Newest Arrivals</option>
                    <option value="price_asc" <?= ($sort === 'price_asc') ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= ($sort === 'price_desc') ? 'selected' : '' ?>>Price: High to Low</option>
                </select>
            </form>
        </div>

        <!-- Product Grid -->
        <?php if (!empty($products)): ?>
            <div class="grid-products">
                <?php foreach ($products as $p): ?>
                    <?php require __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 64px 20px; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
                <div style="font-size: 3rem; margin-bottom: 16px;">🔍</div>
                <h2 style="font-size: 1.5rem; margin-bottom: 8px;">No Products Found</h2>
                <p style="margin-bottom: 24px;">We couldn't find any products matching your search criteria.</p>
                <a href="shop.php" class="btn btn-primary">Reset Filters & View All</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php 
require_once __DIR__ . '/includes/whatsapp-float.php';
require_once __DIR__ . '/includes/footer.php'; 
?>
