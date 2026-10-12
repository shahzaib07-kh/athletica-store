<?php
/**
 * ATHLETICA — Shopping Cart View Page
 *
 * Displays live hydrated cart items, unit prices, real-time stock/availability
 * warnings, quantity adjustments, item removals, order summary calculation,
 * and proceed-to-checkout validation.
 */

$pageTitle = 'Shopping Cart';
$pageDescription = 'Review your selected athletic items, sizes, quantities, and proceed to Cash on Delivery checkout.';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/cart-helper.php';

// Hydrate cart with real-time database details
$cart = cart_hydrate($pdo);

// Flash messages
$flashSuccess = get_flash('success');
$flashError = get_flash('error');
$flashInfo = get_flash('info');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
/* Page-Specific Cart Styles */
.cart-layout-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 32px;
    align-items: start;
}

.cart-items-card, .cart-summary-card {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    overflow: hidden;
}

.cart-item-row {
    display: flex;
    gap: 20px;
    padding: 24px;
    border-bottom: 1px solid var(--border-subtle);
    align-items: center;
    transition: background var(--transition-fast);
}

.cart-item-row:last-child {
    border-bottom: none;
}

.cart-item-row.is-unavailable {
    background: rgba(229, 62, 62, 0.04);
}

.cart-thumb-wrap {
    width: 84px;
    height: 84px;
    border-radius: var(--radius-sm);
    background: #111111;
    border: 1px solid var(--border-subtle);
    overflow: hidden;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cart-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cart-item-main {
    flex: 1;
    min-width: 0;
}

.cart-item-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    margin-bottom: 6px;
}

.cart-item-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.3;
    margin-bottom: 6px;
}

.cart-item-title a:hover {
    color: var(--accent-red);
}

.cart-item-prices {
    display: flex;
    align-items: baseline;
    gap: 10px;
    margin-top: 4px;
}

.cart-item-controls {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
    flex-shrink: 0;
}

.cart-qty-select {
    background: var(--bg-surface);
    border: 1px solid var(--border-medium);
    border-radius: var(--radius-sm);
    color: #ffffff;
    padding: 6px 12px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
}

.cart-qty-select:focus {
    border-color: #ffffff;
    outline: none;
}

.btn-remove-link {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 0.8rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 0;
    transition: color var(--transition-fast);
}

.btn-remove-link:hover {
    color: var(--accent-red);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.summary-row.total-row {
    border-top: 1px solid var(--border-subtle);
    margin-top: 8px;
    padding-top: 18px;
    color: #ffffff;
    font-size: 1.1rem;
    font-weight: 700;
}

@media (max-width: 960px) {
    .cart-layout-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
}

@media (max-width: 640px) {
    .cart-item-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
        padding: 16px;
    }
    .cart-item-controls {
        width: 100%;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid var(--border-subtle);
        padding-top: 12px;
    }
}
</style>

<main class="section-padding">
    <div class="container">
        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb">
            <a href="index.php">Home</a>
            <span class="breadcrumb-separator">/</span>
            <span style="color: #ffffff;">Shopping Cart</span>
        </nav>

        <!-- Page Header -->
        <div class="section-header" style="margin-bottom: 24px;">
            <div>
                <h1 class="section-title">Shopping Cart</h1>
                <p class="section-subtitle">
                    <?= $cart['is_empty'] ? 'Your cart is currently empty' : count($cart['items']) . ' unique item(s) selected' ?>
                </p>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($flashSuccess): ?>
            <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid #10b981; color: #6ee7b7; padding: 14px 18px; border-radius: var(--radius-sm); margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 1.1rem; font-weight: 700;">✓</span>
                <span><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div style="background: rgba(229, 62, 62, 0.12); border: 1px solid #e53e3e; color: #fca5a5; padding: 14px 18px; border-radius: var(--radius-sm); margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 1.1rem;">⚠️</span>
                <span><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashInfo): ?>
            <div style="background: rgba(59, 130, 246, 0.12); border: 1px solid #3b82f6; color: #93c5fd; padding: 14px 18px; border-radius: var(--radius-sm); margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 1.1rem;">ℹ️</span>
                <span><?= htmlspecialchars($flashInfo, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if ($cart['is_empty']): ?>
            <!-- Empty Cart State -->
            <div style="text-align: center; padding: 72px 24px; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); max-width: 620px; margin: 20px auto;">
                <div style="font-size: 3.5rem; margin-bottom: 16px; line-height: 1;">🛒</div>
                <h2 style="font-size: 1.6rem; font-weight: 800; text-transform: uppercase; margin-bottom: 12px;">Your Shopping Cart is Empty</h2>
                <p style="font-size: 0.95rem; color: var(--text-secondary); max-width: 440px; margin: 0 auto 28px; line-height: 1.6;">
                    Explore our catalog of 100% original imported athletic footwear and sportswear.
                </p>
                <a href="shop.php" class="btn btn-primary" style="padding: 14px 32px; font-size: 0.95rem;">
                    Explore Collection
                </a>
            </div>
        <?php else: ?>
            <!-- Out-of-stock / Unavailable Warning Banner -->
            <?php if ($cart['has_unavailable_items']): ?>
                <div style="background: #231212; border: 1px solid #7f1d1d; border-radius: var(--radius-md); padding: 16px 20px; color: #fca5a5; margin-bottom: 24px; display: flex; align-items: center; gap: 14px;">
                    <span style="font-size: 1.4rem;">⚠️</span>
                    <div style="font-size: 0.9rem; font-weight: 600; line-height: 1.5;">
                        One or more items in your cart are currently out of stock or unavailable. Please remove them before proceeding to checkout.
                    </div>
                </div>
            <?php endif; ?>

            <!-- 2-Column Cart Grid -->
            <div class="cart-layout-grid">
                <!-- Left Column: Cart Items List -->
                <div class="cart-items-card">
                    <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary);">
                            Items in Cart (<?= (int)$cart['total_lines'] ?>)
                        </span>
                        <span style="font-size: 0.85rem; color: var(--text-muted);">
                            Total Quantity: <?= (int)$cart['total_quantity'] ?>
                        </span>
                    </div>

                    <div class="cart-items-body">
                        <?php foreach ($cart['items'] as $item): ?>
                            <?php 
                                $isAvailable = $item['is_available'];
                                $isUnique = ($item['product_type'] === 'unique');
                            ?>
                            <div class="cart-item-row <?= $isAvailable ? '' : 'is-unavailable' ?>">
                                <!-- Thumbnail -->
                                <div class="cart-thumb-wrap">
                                    <?php if (!empty($item['slug'])): ?>
                                        <a href="product.php?slug=<?= urlencode($item['slug']) ?>" aria-label="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <img src="<?= htmlspecialchars($item['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                                 alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>" 
                                                 class="cart-thumb-img"
                                                 loading="lazy"
                                                 onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\' viewBox=\'0 0 80 80\'%3E%3Crect fill=\'%23161616\' width=\'80\' height=\'80\'/%3E%3Ctext fill=\'%23555555\' font-family=\'sans-serif\' font-size=\'10\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\'%3EATHLETICA%3C/text%3E%3C/svg%3E';">
                                        </a>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars($item['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                             alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>" 
                                             class="cart-thumb-img">
                                    <?php endif; ?>
                                </div>

                                <!-- Item Info -->
                                <div class="cart-item-main">
                                    <div class="cart-item-badges">
                                        <?php if (!empty($item['brand_name'])): ?>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">
                                                <?= htmlspecialchars($item['brand_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <span style="color: var(--border-medium); font-size: 0.75rem;">•</span>
                                        <?php endif; ?>

                                        <!-- Size Badge -->
                                        <span class="badge" style="background: var(--bg-surface); border: 1px solid var(--border-medium); color: #ffffff; font-size: 0.7rem; padding: 2px 7px;">
                                            Size: <?= htmlspecialchars($item['size'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>

                                        <!-- Product Type Badge -->
                                        <?php if ($isUnique): ?>
                                            <span class="badge" style="background: #d97706; color: #ffffff; font-size: 0.65rem; padding: 2px 6px;">
                                                1-Pair Unique
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #2563eb; color: #ffffff; font-size: 0.65rem; padding: 2px 6px;">
                                                Multi-Size
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Product Title -->
                                    <h3 class="cart-item-title">
                                        <?php if (!empty($item['slug'])): ?>
                                            <a href="product.php?slug=<?= urlencode($item['slug']) ?>">
                                                <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </h3>

                                    <!-- Pricing / Stock Warnings -->
                                    <div class="cart-item-prices">
                                        <?php if ($isAvailable): ?>
                                            <span style="font-size: 0.95rem; font-weight: 700; color: #ffffff;">
                                                Rs. <?= number_format($item['unit_price'], 2) ?>
                                            </span>
                                            <?php if (!empty($item['old_price']) && $item['old_price'] > $item['unit_price']): ?>
                                                <span style="font-size: 0.8rem; color: var(--text-muted); text-decoration: line-through;">
                                                    Rs. <?= number_format($item['old_price'], 2) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="font-size: 0.85rem; color: #f87171; font-weight: 700;">
                                                Rs. <?= number_format($item['unit_price'], 2) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Unavailable Reason Badge -->
                                    <?php if (!$isAvailable): ?>
                                        <div style="display: inline-flex; align-items: center; gap: 6px; color: #f87171; font-size: 0.75rem; font-weight: 700; margin-top: 8px; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 10px; border-radius: var(--radius-sm);">
                                            <span>⛔ UNAVAILABLE / OUT OF STOCK: <?= htmlspecialchars($item['unavailable_reason'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Controls & Line Total -->
                                <div class="cart-item-controls">
                                    <?php if ($isAvailable): ?>
                                        <div style="font-size: 1.05rem; font-weight: 800; color: #ffffff;">
                                            Rs. <?= number_format($item['line_total'], 2) ?>
                                        </div>

                                        <?php if ($isUnique): ?>
                                            <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); background: var(--bg-surface); padding: 4px 8px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                                                Qty: 1 (Unique Single Pair)
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" action="cart-action.php" style="display: flex; align-items: center; gap: 6px;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_quantity">
                                                <input type="hidden" name="cart_key" value="<?= htmlspecialchars($item['cart_key'], ENT_QUOTES, 'UTF-8') ?>">
                                                <label for="qty_<?= htmlspecialchars($item['cart_key'], ENT_QUOTES, 'UTF-8') ?>" style="font-size: 0.75rem; color: var(--text-muted);">Qty:</label>
                                                <select name="quantity" 
                                                        id="qty_<?= htmlspecialchars($item['cart_key'], ENT_QUOTES, 'UTF-8') ?>" 
                                                        class="cart-qty-select" 
                                                        onchange="this.form.submit();">
                                                    <?php for ($q = 1; $q <= 5; $q++): ?>
                                                        <option value="<?= $q ?>" <?= ($item['quantity'] === $q) ? 'selected' : '' ?>>
                                                            <?= $q ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                                <noscript>
                                                    <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 0.75rem;">Update</button>
                                                </noscript>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div style="font-size: 1rem; color: var(--text-muted); font-weight: 700;">
                                            —
                                        </div>
                                    <?php endif; ?>

                                    <!-- Remove Item Form Button -->
                                    <form method="POST" action="cart-action.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="cart_key" value="<?= htmlspecialchars($item['cart_key'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn-remove-link">
                                            <span>✕ Remove</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right Column: Order Summary Card -->
                <div class="cart-summary-card" style="padding: 24px;">
                    <h2 style="font-size: 1.15rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px;">
                        Order Summary
                    </h2>

                    <div class="summary-row">
                        <span>Subtotal (<?= (int)$cart['total_quantity'] ?> <?= $cart['total_quantity'] === 1 ? 'item' : 'items' ?>)</span>
                        <span style="font-weight: 700; color: #ffffff;">Rs. <?= number_format($cart['subtotal'], 2) ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Delivery Fee</span>
                        <?php if ($cart['delivery_fee'] <= 0): ?>
                            <span><span style="color: #4ade80; font-weight: 700;">FREE</span> (Rs. 0.00)</span>
                        <?php else: ?>
                            <span style="font-weight: 700; color: #ffffff;">Rs. <?= number_format($cart['delivery_fee'], 2) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="summary-row total-row">
                        <span>Estimated Total</span>
                        <span style="font-size: 1.35rem; font-weight: 900; color: #ffffff;">
                            Rs. <?= number_format($cart['grand_total'], 2) ?>
                        </span>
                    </div>

                    <!-- COD Nationwide Info Box -->
                    <div style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 12px 14px; font-size: 0.8rem; color: var(--text-secondary); margin: 20px 0; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.2rem;">🚚</span>
                        <div>Cash on Delivery available nationwide across Pakistan. Inspect parcel before payment.</div>
                    </div>

                    <!-- Checkout & Shopping Action Buttons -->
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php if ($cart['has_unavailable_items']): ?>
                            <button type="button" class="btn btn-primary btn-block" disabled style="opacity: 0.45; cursor: not-allowed; padding: 14px; font-size: 1rem; text-transform: uppercase; font-weight: 700;">
                                Proceed to Checkout
                            </button>
                            <span style="color: #f87171; font-size: 0.8rem; display: block; text-align: center; margin-top: 4px;">
                                Remove unavailable items to enable checkout.
                            </span>
                        <?php else: ?>
                            <a href="checkout.php" class="btn btn-primary btn-block" style="padding: 14px; font-size: 1rem; text-align: center; text-transform: uppercase; font-weight: 700;">
                                Proceed to Checkout &rarr;
                            </a>
                        <?php endif; ?>

                        <a href="shop.php" class="btn btn-outline btn-block" style="text-align: center; margin-top: 4px;">
                            Continue Shopping
                        </a>
                    </div>

                    <!-- Clear Entire Cart Form -->
                    <form method="POST" action="cart-action.php" onsubmit="return confirm('Are you sure you want to clear your entire cart?');" style="margin-top: 20px; text-align: center;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" style="background: none; border: none; color: var(--text-muted); font-size: 0.8rem; text-decoration: underline; cursor: pointer;">
                            Clear entire cart
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
