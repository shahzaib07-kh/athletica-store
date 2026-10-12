<?php
/**
 * ATHLETICA — Storefront Homepage
 * FOCUS | POWER | PERFORMANCE
 */

$pageTitle = 'ATHLETICA — Premium Imported Footwear & Athletic Gear';
$pageDescription = 'Discover genuine imported sneakers, running shoes, caps, shirts, and athletic wear. Fast Cash on Delivery across Pakistan.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// 1. Fetch Featured Products (with primary image and available sizes)
try {
    $featuredQuery = "
        SELECT p.*, 
               c.name AS category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
               (SELECT GROUP_CONCAT(ps.size ORDER BY ps.sort_order SEPARATOR ', ')
                FROM product_sizes ps 
                WHERE ps.product_id = p.id AND ps.is_available = 1) AS available_sizes
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active' AND p.is_featured = 1
        ORDER BY p.created_at DESC
        LIMIT 8
    ";
    $featuredProducts = $pdo->query($featuredQuery)->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $featuredProducts = [];
}

// 2. Fetch New Arrivals (Latest active products)
try {
    $latestQuery = "
        SELECT p.*, 
               c.name AS category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
               (SELECT GROUP_CONCAT(ps.size ORDER BY ps.sort_order SEPARATOR ', ')
                FROM product_sizes ps 
                WHERE ps.product_id = p.id AND ps.is_available = 1) AS available_sizes
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 8
    ";
    $latestProducts = $pdo->query($latestQuery)->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $latestProducts = [];
}

// 3. Fetch Distinct Available Sizes for "Shop by Size" Widget
try {
    $sizeQuery = "
        SELECT DISTINCT ps.size
        FROM product_sizes ps
        JOIN products p ON p.id = ps.product_id
        WHERE ps.is_available = 1 AND p.status = 'active'
        ORDER BY ps.sort_order ASC, ps.size ASC
        LIMIT 14
    ";
    $availableSizes = $pdo->query($sizeQuery)->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (PDOException $e) {
    $availableSizes = [];
}

// 4. Fetch Category Showcase with product counts
try {
    $catShowcaseQuery = "
        SELECT c.*, 
               COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.sort_order ASC, c.name ASC
        LIMIT 6
    ";
    $showcaseCategories = $pdo->query($catShowcaseQuery)->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $showcaseCategories = [];
}
?>

<main>
    <!-- 1. Hero Showcase Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-slide">
                <div class="hero-content">
                    <span class="hero-tag">⚡ AUTHENTIC IMPORTED GEAR</span>
                    <h1 class="hero-title">POWER YOUR PERFORMANCE</h1>
                    <p class="hero-subtitle">
                        Handpicked genuine footwear, limited edition sneakers, and pro athletic apparel. Single-pair exclusives and multi-size essentials with Cash on Delivery nationwide.
                    </p>
                    <div class="hero-cta-group">
                        <a href="shop.php" class="btn btn-primary">Shop All Catalog &rarr;</a>
                        <a href="https://wa.me/<?= urlencode($cleanWhatsapp) ?>?text=<?= urlencode('Hello ATHLETICA, I want to see the latest collection.') ?>" 
                           class="btn btn-whatsapp" target="_blank" rel="noopener noreferrer">
                            💬 Order via WhatsApp
                        </a>
                    </div>
                </div>
                <div class="hero-visual">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 500 350' width='500' height='350'%3E%3Cpath d='M80,240 C140,240 180,220 220,180 C260,140 320,120 420,130 C450,133 460,160 440,190 C400,250 320,290 140,290 C90,290 60,280 50,260 C40,240 60,240 80,240 Z' fill='%23e53e3e' opacity='0.85'/%3E%3Cpath d='M100,230 C200,230 300,180 430,170 C410,210 340,260 130,260 Z' fill='%23ffffff' opacity='0.9'/%3E%3Ctext x='50%25' y='50%25' fill='%23111111' font-family='sans-serif' font-weight='900' font-size='26' text-anchor='middle'%3EATHLETICA PERFORMANCE%3C/text%3E%3C/svg%3E" 
                         alt="ATHLETICA Athletic Footwear">
                </div>
            </div>
        </div>
    </section>

    <!-- 2. Trust Badges Bar -->
    <section class="trust-badges-bar">
        <div class="container">
            <div class="trust-badges-grid">
                <div class="trust-badge-item">
                    <div class="trust-badge-icon">📦</div>
                    <div>
                        <div class="trust-badge-title">Cash on Delivery</div>
                        <div class="trust-badge-desc">Pay safely upon delivery at your doorstep</div>
                    </div>
                </div>
                <div class="trust-badge-item">
                    <div class="trust-badge-icon">👟</div>
                    <div>
                        <div class="trust-badge-title">100% Genuine Imported</div>
                        <div class="trust-badge-desc">Authentic brands, verified original pairs</div>
                    </div>
                </div>
                <div class="trust-badge-item">
                    <div class="trust-badge-icon">⚡</div>
                    <div>
                        <div class="trust-badge-title">Fast Nationwide Shipping</div>
                        <div class="trust-badge-desc">Swift dispatch across all cities of Pakistan</div>
                    </div>
                </div>
                <div class="trust-badge-item">
                    <div class="trust-badge-icon">💬</div>
                    <div>
                        <div class="trust-badge-title">Direct WhatsApp Orders</div>
                        <div class="trust-badge-desc">Instant confirmation & personalized support</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Category Showcase Grid -->
    <?php if (!empty($showcaseCategories)): ?>
    <section class="section-padding">
        <div class="container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Explore Categories</h2>
                    <p class="section-subtitle">Find your favorite footwear, apparel, and athletic essentials</p>
                </div>
                <a href="shop.php" class="view-all-link">All Categories &rarr;</a>
            </div>

            <div class="grid-categories">
                <?php foreach ($showcaseCategories as $cat): ?>
                    <a href="category.php?slug=<?= urlencode($cat['slug']) ?>" class="category-card">
                        <?php if (!empty($cat['image'])): ?>
                            <img src="<?= htmlspecialchars($cat['image'], ENT_QUOTES, 'UTF-8') ?>" 
                                 alt="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>" 
                                 class="category-card-bg" loading="lazy">
                        <?php else: ?>
                            <div class="category-card-bg" style="background: #1e1e1e;"></div>
                        <?php endif; ?>
                        <div class="category-card-overlay">
                            <h3 class="category-card-name"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <span class="category-card-count"><?= (int)$cat['product_count'] ?> Products</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- 4. Shop by Size Selector Widget -->
    <?php if (!empty($availableSizes)): ?>
    <section class="section-padding" style="background: var(--bg-secondary); border-top: 1px solid var(--border-subtle); border-bottom: 1px solid var(--border-subtle);">
        <div class="container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Shop by Size</h2>
                    <p class="section-subtitle">Quickly find in-stock pairs and gear in your exact size</p>
                </div>
            </div>

            <div class="size-selector-bar">
                <a href="shop.php" class="size-filter-btn">All Sizes</a>
                <?php foreach ($availableSizes as $sizeVal): ?>
                    <a href="size.php?size=<?= urlencode($sizeVal) ?>" class="size-filter-btn">
                        <?= htmlspecialchars($sizeVal, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- 5. Featured Products Showcase -->
    <?php if (!empty($featuredProducts)): ?>
    <section class="section-padding">
        <div class="container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Featured Gear</h2>
                    <p class="section-subtitle">Hand-selected imported highlights and top-performing models</p>
                </div>
                <a href="shop.php" class="view-all-link">View Collection &rarr;</a>
            </div>

            <div class="grid-products">
                <?php foreach ($featuredProducts as $p): ?>
                    <?php require __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- 6. New Arrivals Showcase -->
    <?php if (!empty($latestProducts)): ?>
    <section class="section-padding" style="background: var(--bg-secondary); border-top: 1px solid var(--border-subtle);">
        <div class="container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">New Arrivals</h2>
                    <p class="section-subtitle">Freshly imported items added to the ATHLETICA catalog</p>
                </div>
                <a href="shop.php" class="view-all-link">Explore All &rarr;</a>
            </div>

            <div class="grid-products">
                <?php foreach ($latestProducts as $p): ?>
                    <?php require __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- 7. WhatsApp Direct Inquiry Banner -->
    <section class="container">
        <div class="whatsapp-cta-banner">
            <div class="whatsapp-banner-text">
                <h3>Looking for a Specific Size or Model?</h3>
                <p>Chat directly with our team on WhatsApp for size advice, live item pictures, or custom inquiries.</p>
            </div>
            <a href="https://wa.me/<?= urlencode($cleanWhatsapp) ?>?text=<?= urlencode('Hello ATHLETICA, I am looking for a specific product/size.') ?>" 
               class="btn btn-whatsapp" target="_blank" rel="noopener noreferrer" style="padding: 16px 32px; font-size: 1rem;">
                💬 Chat on WhatsApp
            </a>
        </div>
    </section>
</main>

<?php 
require_once __DIR__ . '/includes/whatsapp-float.php';
require_once __DIR__ . '/includes/footer.php'; 
?>
