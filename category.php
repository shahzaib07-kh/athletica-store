<?php
/**
 * ATHLETICA — Category Catalog Page
 * Browse products in a specific category
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: shop.php');
    exit;
}

// Fetch category
$catStmt = $pdo->prepare("SELECT * FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1");
$catStmt->execute([':slug' => $slug]);
$currentCategory = $catStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentCategory) {
    header('Location: shop.php');
    exit;
}

$pageTitle = $currentCategory['name'] . ' — ATHLETICA';
$pageDescription = !empty($currentCategory['description']) 
    ? $currentCategory['description'] 
    : 'Shop genuine imported ' . $currentCategory['name'] . ' with Cash on Delivery nationwide.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$sort = $_GET['sort'] ?? 'newest';

// Build SQL
$sql = "
    SELECT p.*, 
           c.name AS category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
           (SELECT GROUP_CONCAT(ps.size ORDER BY ps.sort_order SEPARATOR ', ')
            FROM product_sizes ps 
            WHERE ps.product_id = p.id AND ps.is_available = 1) AS available_sizes
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'active' AND p.category_id = :cid
";

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
    $stmt->execute([':cid' => $currentCategory['id']]);
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
            <a href="shop.php">Shop</a>
            <span class="breadcrumb-separator">/</span>
            <span style="color: #ffffff;"><?= htmlspecialchars($currentCategory['name'], ENT_QUOTES, 'UTF-8') ?></span>
        </nav>

        <!-- Section Header -->
        <div class="section-header">
            <div>
                <h1 class="section-title"><?= htmlspecialchars($currentCategory['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if (!empty($currentCategory['description'])): ?>
                    <p class="section-subtitle"><?= htmlspecialchars($currentCategory['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <p class="section-subtitle">Showing <?= count($products) ?> available items</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Catalog Toolbar -->
        <div class="catalog-toolbar">
            <span style="font-size: 0.9rem; color: var(--text-secondary);">
                <strong><?= count($products) ?></strong> items available
            </span>

            <form action="category.php" method="GET" id="cat_sort_form" style="display: flex; align-items: center; gap: 8px;">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                <label for="cat_sort_select" style="font-size: 0.85rem; color: var(--text-secondary);">Sort by:</label>
                <select name="sort" id="cat_sort_select" class="catalog-sort-select" onchange="document.getElementById('cat_sort_form').submit();">
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
                <h2 style="font-size: 1.5rem; margin-bottom: 8px;">No Products in this Category</h2>
                <p style="margin-bottom: 24px;">Check back soon for new imports and restocks.</p>
                <a href="shop.php" class="btn btn-primary">Browse All Products</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php 
require_once __DIR__ . '/includes/whatsapp-float.php';
require_once __DIR__ . '/includes/footer.php'; 
?>
