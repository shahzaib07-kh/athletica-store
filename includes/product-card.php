<?php
/**
 * ATHLETICA — Reusable Product Card Component Partial
 * Expects $p (product associative array) to be defined in scope
 */

if (!isset($p) || empty($p)) {
    return;
}

$hasDiscount = (!empty($p['old_price']) && (float)$p['old_price'] > (float)$p['price']);
$discountPercent = $hasDiscount ? round((((float)$p['old_price'] - (float)$p['price']) / (float)$p['old_price']) * 100) : 0;
$productImgPath = !empty($p['primary_image']) ? $p['primary_image'] : 'assets/images/placeholder-product.jpg';
$isOutOfStock = ($p['status'] === 'out_of_stock');

// Parse size list string
$sizeList = !empty($p['available_sizes']) ? explode(',', $p['available_sizes']) : [];
?>

<div class="product-card">
    <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="product-card-image-wrap" aria-label="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>">
        <!-- Badges -->
        <div class="product-card-badges">
            <?php if ($isOutOfStock): ?>
                <span class="badge badge-out-of-stock">Out of Stock</span>
            <?php elseif ($hasDiscount): ?>
                <span class="badge badge-sale">-<?= (int)$discountPercent ?>%</span>
            <?php elseif (!empty($p['is_featured'])): ?>
                <span class="badge badge-featured">Featured</span>
            <?php endif; ?>
        </div>

        <!-- Product Image -->
        <img src="<?= htmlspecialchars($productImgPath, ENT_QUOTES, 'UTF-8') ?>" 
             alt="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>" 
             class="product-card-img" 
             loading="lazy" 
             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'300\' height=\'300\' viewBox=\'0 0 300 300\'%3E%3Crect fill=\'%23161616\' width=\'300\' height=\'300\'/%3E%3Ctext fill=\'%23555555\' font-family=\'sans-serif\' font-size=\'14\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\'%3EATHLETICA%3C/text%3E%3C/svg%3E';">
    </a>

    <div class="product-card-body">
        <span class="product-card-category"><?= htmlspecialchars($p['category_name'] ?? 'Footwear', ENT_QUOTES, 'UTF-8') ?></span>
        
        <h3 class="product-card-title">
            <a href="product.php?slug=<?= urlencode($p['slug']) ?>">
                <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        </h3>

        <!-- Available Sizes Preview -->
        <div class="product-card-sizes">
            <?php if (!empty($sizeList)): ?>
                <?php foreach (array_slice($sizeList, 0, 4) as $s): ?>
                    <span class="size-pill-mini"><?= htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
                <?php if (count($sizeList) > 4): ?>
                    <span class="size-pill-mini">+<?= count($sizeList) - 4 ?></span>
                <?php endif; ?>
            <?php else: ?>
                <span class="size-pill-mini text-muted">Check Sizes</span>
            <?php endif; ?>
        </div>

        <!-- Price Row -->
        <div class="product-card-price-row">
            <span class="price-current"><?= htmlspecialchars($currencySymbol ?? 'Rs.', ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$p['price']) ?></span>
            <?php if ($hasDiscount): ?>
                <span class="price-old"><?= htmlspecialchars($currencySymbol ?? 'Rs.', ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$p['old_price']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
