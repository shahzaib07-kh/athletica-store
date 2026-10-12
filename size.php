<?php
/**
 * ATHLETICA — Shop by Size Catalog Page
 * Browse products having available stock in a specific size
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$requestedSize = trim($_GET['size'] ?? '');
if ($requestedSize === '') {
    header('Location: shop.php');
    exit;
}

$pageTitle = 'Shop Size: ' . $requestedSize . ' — ATHLETICA';
$pageDescription = 'Browse all in-stock athletic footwear and apparel available in size ' . $requestedSize . '. Cash on Delivery across Pakistan.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// Fetch distinct sizes for the top pill bar
try {
    $sizeListQuery = "
        SELECT DISTINCT ps.size
        FROM product_sizes ps
        JOIN products p ON p.id = ps.product_id
        WHERE ps.is_available = 1 AND p.status = 'active'
        ORDER BY ps.sort_order ASC, ps.size ASC
    ";
    $allAvailableSizes = $pdo->query($sizeListQuery)->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (PDOException $e) {
    $allAvailableSizes = [];
}

// Fetch products with the requested size
$sql = "
    SELECT DISTINCT 
           p.*, 
           c.name AS category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
           (SELECT GROUP_CONCAT(ps2.size ORDER BY ps2.sort_order SEPARATOR ', ')
            FROM product_sizes ps2 
            WHERE ps2.product_id = p.id AND ps2.is_available = 1) AS available_sizes
    FROM products p
    JOIN categories c ON c.id = p.category_id
    JOIN product_sizes ps ON ps.product_id = p.id
    WHERE p.status = 'active' 
      AND ps.size = :size 
      AND ps.is_available = 1
    ORDER BY p.created_at DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':size' => $requestedSize]);
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
            <span style="color: #ffffff;">Size: <?= htmlspecialchars($requestedSize, ENT_QUOTES, 'UTF-8') ?></span>
        </nav>

        <!-- Section Header -->
        <div class="section-header">
            <div>
                <h1 class="section-title">Size: <?= htmlspecialchars($requestedSize, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="section-subtitle">Showing <?= count($products) ?> available items in size <?= htmlspecialchars($requestedSize, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <!-- Size Quick Switcher -->
        <?php if (!empty($allAvailableSizes)): ?>
        <div style="margin-bottom: 28px;">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px;">Select Other Sizes:</div>
            <div class="size-selector-bar">
                <a href="shop.php" class="size-filter-btn">All</a>
                <?php foreach ($allAvailableSizes as $sVal): ?>
                    <a href="size.php?size=<?= urlencode($sVal) ?>" 
                       class="size-filter-btn <?= ($sVal === $requestedSize) ? 'active' : '' ?>">
                        <?= htmlspecialchars($sVal, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Product Grid -->
        <?php if (!empty($products)): ?>
            <div class="grid-products">
                <?php foreach ($products as $p): ?>
                    <?php require __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 64px 20px; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
                <h2 style="font-size: 1.5rem; margin-bottom: 8px;">No In-Stock Items for Size "<?= htmlspecialchars($requestedSize, ENT_QUOTES, 'UTF-8') ?>"</h2>
                <p style="margin-bottom: 24px;">Try selecting another size or view our full collection.</p>
                <a href="shop.php" class="btn btn-primary">Browse All Products</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php 
require_once __DIR__ . '/includes/whatsapp-float.php';
require_once __DIR__ . '/includes/footer.php'; 
?>
