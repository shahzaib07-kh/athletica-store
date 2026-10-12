<?php
/**
 * ATHLETICA — Product Details Page
 * Multi-image gallery, interactive size selector, Scenario A/B handling, WhatsApp order CTA
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($slug === '' && !$id) {
    header('Location: shop.php');
    exit;
}

// Fetch product with category info
if ($slug !== '') {
    $prodStmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.slug = :slug AND p.status != 'inactive'
        LIMIT 1
    ");
    $prodStmt->execute([':slug' => $slug]);
} else {
    $prodStmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.id = :id AND p.status != 'inactive'
        LIMIT 1
    ");
    $prodStmt->execute([':id' => $id]);
}

$product = $prodStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: shop.php');
    exit;
}

$productId = (int)$product['id'];

// Fetch all product images
$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = :pid ORDER BY is_primary DESC, sort_order ASC, id ASC");
$imgStmt->execute([':pid' => $productId]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch all sizes
$sizeStmt = $pdo->prepare("SELECT * FROM product_sizes WHERE product_id = :pid ORDER BY sort_order ASC, id ASC");
$sizeStmt->execute([':pid' => $productId]);
$sizes = $sizeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Calculate stock & discounts
$hasDiscount = (!empty($product['old_price']) && (float)$product['old_price'] > (float)$product['price']);
$discountPercent = $hasDiscount ? round((((float)$product['old_price'] - (float)$product['price']) / (float)$product['old_price']) * 100) : 0;
$isOutOfStock = ($product['status'] === 'out_of_stock');

// Check available sizes count
$availableSizesList = array_filter($sizes, function($s) { return (int)$s['is_available'] === 1; });
$availableCount = count($availableSizesList);
$isSinglePair = ($availableCount === 1 && count($sizes) === 1);
$defaultSize = !empty($availableSizesList) ? reset($availableSizesList)['size'] : '';

// Primary main image
$mainImage = !empty($images) ? $images[0]['image_path'] : 'assets/images/placeholder-product.jpg';

// Fetch Related Products (same category, excluding current)
$relStmt = $pdo->prepare("
    SELECT p.*, 
           c.name AS category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
           (SELECT GROUP_CONCAT(ps.size ORDER BY ps.sort_order SEPARATOR ', ')
            FROM product_sizes ps 
            WHERE ps.product_id = p.id AND ps.is_available = 1) AS available_sizes
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'active' AND p.category_id = :cid AND p.id != :pid
    ORDER BY p.created_at DESC
    LIMIT 4
");
$relStmt->execute([':cid' => $product['category_id'], ':pid' => $productId]);
$relatedProducts = $relStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pageTitle = $product['name'] . ' — ATHLETICA';
$pageDescription = !empty($product['description']) 
    ? mb_substr(strip_tags($product['description']), 0, 155) . '...' 
    : 'Buy ' . $product['name'] . ' at ATHLETICA. Cash on Delivery nationwide.';
$pageImage = $mainImage;

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="section-padding">
    <div class="container">
        <!-- Breadcrumbs -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb">
            <a href="index.php">Home</a>
            <span class="breadcrumb-separator">/</span>
            <a href="category.php?slug=<?= urlencode($product['category_slug']) ?>">
                <?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <span class="breadcrumb-separator">/</span>
            <span style="color: #ffffff;"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></span>
        </nav>

        <div class="product-detail-layout">
            <!-- Left Column: Gallery -->
            <div class="product-gallery">
                <div class="gallery-main-view">
                    <img id="gallery_main_image" 
                         src="<?= htmlspecialchars($mainImage, ENT_QUOTES, 'UTF-8') ?>" 
                         alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>" 
                         class="gallery-main-img"
                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'500\' height=\'500\' viewBox=\'0 0 500 500\'%3E%3Crect fill=\'%23161616\' width=\'500\' height=\'500\'/%3E%3Ctext fill=\'%23555555\' font-family=\'sans-serif\' font-size=\'20\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\'%3EATHLETICA%3C/text%3E%3C/svg%3E';">
                </div>

                <?php if (count($images) > 1): ?>
                    <div class="gallery-thumbnails">
                        <?php foreach ($images as $idx => $img): ?>
                            <button type="button" 
                                    class="gallery-thumb-btn <?= ($idx === 0) ? 'active' : '' ?>" 
                                    data-fullimage="<?= htmlspecialchars($img['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="View Image <?= $idx + 1 ?>">
                                <img src="<?= htmlspecialchars($img['image_path'], ENT_QUOTES, 'UTF-8') ?>" 
                                     alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>" 
                                     class="gallery-thumb-img">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Product Info & Size Selector -->
            <div class="product-info-col">
                <div class="product-meta-header">
                    <a href="category.php?slug=<?= urlencode($product['category_slug']) ?>" class="product-category-link">
                        <?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    
                    <h1 class="product-detail-title"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>

                    <div class="product-detail-pricing">
                        <span class="detail-price-current">
                            <?= htmlspecialchars($currencySymbol ?? 'Rs.', ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$product['price']) ?>
                        </span>
                        <?php if ($hasDiscount): ?>
                            <span class="detail-price-old">
                                <?= htmlspecialchars($currencySymbol ?? 'Rs.', ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$product['old_price']) ?>
                            </span>
                            <span class="detail-discount-badge">SAVE <?= (int)$discountPercent ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attributes Row -->
                <div style="display: flex; gap: 16px; font-size: 0.85rem; color: var(--text-secondary); flex-wrap: wrap;">
                    <?php if (!empty($product['condition'])): ?>
                        <div><strong>Condition:</strong> <span style="color: #fff; text-transform: capitalize;"><?= htmlspecialchars($product['condition'], ENT_QUOTES, 'UTF-8') ?> (Imported)</span></div>
                    <?php endif; ?>
                    <?php if (!empty($product['color'])): ?>
                        <div><strong>Color:</strong> <span style="color: #fff;"><?= htmlspecialchars($product['color'], ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($product['sku'])): ?>
                        <div><strong>SKU:</strong> <span style="color: #fff;"><?= htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php endif; ?>
                </div>

                <!-- Size Selection Block -->
                <div class="size-selection-block">
                    <div class="size-block-header">
                        <span class="size-block-title">Select Size:</span>
                        <?php if ($isSinglePair): ?>
                            <span style="font-size: 0.8rem; color: var(--accent-red); font-weight: 700;">★ UNIQUE ITEM (1 PAIR ONLY)</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($isOutOfStock || empty($availableSizesList)): ?>
                        <div style="padding: 12px; background: #201010; border: 1px solid #5a1e1e; border-radius: var(--radius-sm); color: #f87171; font-size: 0.9rem; font-weight: 600;">
                            Currently Out of Stock. Inquire on WhatsApp for restock updates.
                        </div>
                    <?php else: ?>
                        <!-- Interactive Size Grid -->
                        <div class="size-options-grid">
                            <?php foreach ($sizes as $s): ?>
                                <?php 
                                    $isAvail = ((int)$s['is_available'] === 1);
                                    $isPreselected = ($s['size'] === $defaultSize);
                                ?>
                                <button type="button" 
                                        class="size-option-btn <?= $isAvail ? '' : 'disabled' ?> <?= ($isAvail && $isPreselected) ? 'selected' : '' ?>"
                                        data-size="<?= htmlspecialchars($s['size'], ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $isAvail ? '' : 'disabled' ?>>
                                    <?= htmlspecialchars($s['size'], ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add to Cart & Order Form -->
                <form method="POST" action="cart-action.php" id="product_cart_form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <input type="hidden" name="size" id="selected_size_input" value="<?= htmlspecialchars($defaultSize, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" id="cart_form_action" value="add">
                    <input type="hidden" name="quantity" value="1">

                    <!-- Action Buttons -->
                    <div class="action-buttons-group">
                        <?php if (!$isOutOfStock && !empty($availableSizesList)): ?>
                            <div style="display: flex; gap: 12px; margin-bottom: 8px;">
                                <button type="submit" onclick="document.getElementById('cart_form_action').value='add'" class="btn btn-outline" style="flex: 1; padding: 14px; font-weight: 700; text-transform: uppercase;">
                                    🛒 Add to Cart
                                </button>
                                <button type="submit" onclick="document.getElementById('cart_form_action').value='buy_now'" class="btn btn-primary" style="flex: 1; padding: 14px; font-weight: 700; text-transform: uppercase;">
                                    ⚡ Buy Now
                                </button>
                            </div>

                            <!-- Direct WhatsApp Order Button -->
                            <?php 
                                $waBaseUrl = "https://wa.me/" . urlencode($cleanWhatsapp);
                                $initialMsg = "Hello ATHLETICA, I want to order \"" . $product['name'] . "\" (Size: " . $defaultSize . ") via Cash on Delivery.";
                            ?>
                            <a id="whatsapp_direct_order_btn" 
                               href="<?= $waBaseUrl ?>?text=<?= urlencode($initialMsg) ?>" 
                               data-baseurl="<?= $waBaseUrl ?>"
                               data-product="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                               class="btn btn-whatsapp btn-block" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               style="padding: 14px 20px; font-size: 0.95rem; justify-content: center;">
                                💬 Fast Order via WhatsApp
                            </a>
                        <?php else: ?>
                            <a href="https://wa.me/<?= urlencode($cleanWhatsapp) ?>?text=<?= urlencode('Hello ATHLETICA, when will ' . $product['name'] . ' be back in stock?') ?>" 
                               class="btn btn-whatsapp btn-block" target="_blank" rel="noopener noreferrer" style="justify-content: center; padding: 14px;">
                                💬 Notify Me on WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>
                </form>


                <!-- Trust Guarantees -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 18px; display: flex; flex-direction: column; gap: 10px; font-size: 0.85rem; margin-top: 8px;">
                    <div>🚚 <strong>Cash on Delivery:</strong> Inspect your parcel at delivery.</div>
                    <div>👟 <strong>100% Genuine:</strong> Authentic imported brand stock guaranteed.</div>
                    <div>⚡ <strong>Swift Dispatch:</strong> Orders confirmed and shipped within 24 hours.</div>
                </div>

                <!-- Description -->
                <?php if (!empty($product['description'])): ?>
                    <div style="margin-top: 16px; border-top: 1px solid var(--border-subtle); padding-top: 20px;">
                        <h3 style="font-size: 1.1rem; font-weight: 700; text-transform: uppercase; margin-bottom: 10px;">Product Description</h3>
                        <div style="color: var(--text-secondary); line-height: 1.7; font-size: 0.95rem;">
                            <?= nl2br(htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Products Section -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="section-padding" style="border-top: 1px solid var(--border-subtle); padding-bottom: 0;">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">You May Also Like</h2>
                        <p class="section-subtitle">More items from <?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <a href="category.php?slug=<?= urlencode($product['category_slug']) ?>" class="view-all-link">View All &rarr;</a>
                </div>

                <div class="grid-products">
                    <?php foreach ($relatedProducts as $p): ?>
                        <?php require __DIR__ . '/includes/product-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php 
require_once __DIR__ . '/includes/whatsapp-float.php';
require_once __DIR__ . '/includes/footer.php'; 
?>
