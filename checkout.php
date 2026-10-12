<?php
/**
 * ATHLETICA — Cash on Delivery Checkout & Order Creation
 *
 * Handles customer details validation, atomic size claiming for unique items,
 * live database price and stock verification, PDO database transactions,
 * immutable order_items snapshot creation, and session cart management.
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/cart-helper.php';

if (!function_exists('generate_order_number')) {
    /**
     * Generates a collision-resistant unique order number in format: ATH-YYYYMMDD-NNNN
     *
     * @param PDO $pdo
     * @return string
     */
    function generate_order_number(PDO $pdo): string
    {
        $datePrefix = 'ATH-' . date('Ymd') . '-';
        for ($i = 0; $i < 10; $i++) {
            $randomSuffix = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $datePrefix . $randomSuffix;
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = :num LIMIT 1");
            $stmt->execute([':num' => $candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
        }
        return $datePrefix . substr((string)time(), -4);
    }
}


// --------------------------------------------------------------------------
// POST Request Handler: Order Processing & Transaction Execution
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Strict CSRF Verification
    if (!csrf_verify()) {
        set_flash('error', 'Security token expired or invalid. Please try again.');
        header('Location: checkout.php');
        exit;
    }

    // 2. Duplicate submission lock (within 4 seconds)
    $now = microtime(true);
    if (isset($_SESSION['checkout_lock']) && ($now - $_SESSION['checkout_lock']) < 4.0) {
        set_flash('error', 'Your order is currently processing. Please do not submit multiple times.');
        header('Location: checkout.php');
        exit;
    }
    $_SESSION['checkout_lock'] = $now;

    // 3. Collect & sanitize customer fields
    $customerName    = trim($_POST['customer_name'] ?? '');
    $customerPhone   = trim($_POST['customer_phone'] ?? '');
    $customerEmail   = trim($_POST['customer_email'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $orderNotes      = trim($_POST['order_notes'] ?? '');

    $errors = [];

    // Customer name: required, 2-100 chars
    if (mb_strlen($customerName) < 2 || mb_strlen($customerName) > 100) {
        $errors[] = 'Please enter your full name (2–100 characters).';
    }

    // Customer phone: clean spaces/hyphens, validate Pakistani mobile format or 10-15 digits
    $cleanPhone = preg_replace('/[\s\-]+/', '', $customerPhone);
    $isPkPhone = preg_match('/^((\+92)|(0092)|(92)|(0))?3[0-9]{9}$/', $cleanPhone);
    $isGeneralPhone = preg_match('/^\+?[0-9]{10,15}$/', $cleanPhone);

    if ($customerPhone === '' || (!$isPkPhone && !$isGeneralPhone)) {
        $errors[] = 'Please enter a valid Pakistani mobile / WhatsApp number (e.g. 03001234567 or +923001234567).';
    }

    // Customer email: optional, if present validate format & max 100 chars
    if ($customerEmail !== '') {
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($customerEmail) > 100) {
            $errors[] = 'Please enter a valid email address (or leave it blank).';
        }
    }

    // City: required, 2-100 chars
    if (mb_strlen($city) < 2 || mb_strlen($city) > 100) {
        $errors[] = 'Please enter a valid city name (2–100 characters).';
    }

    // Delivery address: required, 5-1000 chars
    if (mb_strlen($deliveryAddress) < 5 || mb_strlen($deliveryAddress) > 1000) {
        $errors[] = 'Please provide a complete delivery address (5–1000 characters).';
    }

    // Order notes: optional, max 1000 chars
    if ($orderNotes !== '' && mb_strlen($orderNotes) > 1000) {
        $errors[] = 'Order notes cannot exceed 1000 characters.';
    }

    // If validation fails, preserve inputs and redirect back
    if (!empty($errors)) {
        $_SESSION['old_checkout_input'] = [
            'customer_name'    => $customerName,
            'customer_phone'   => $customerPhone,
            'customer_email'   => $customerEmail,
            'city'             => $city,
            'delivery_address' => $deliveryAddress,
            'order_notes'      => $orderNotes,
        ];
        unset($_SESSION['checkout_lock']);
        set_flash('error', implode(' ', $errors));
        header('Location: checkout.php');
        exit;
    }

    // 4. Database Transaction
    try {
        $pdo->beginTransaction();

        $rawItems = cart_get_items();
        if (empty($rawItems)) {
            throw new Exception('Your shopping cart is empty.');
        }

        $prodStmt = $pdo->prepare("SELECT id, name, price, old_price, color, condition, product_type, status FROM products WHERE id = :id LIMIT 1");
        $sizeStmt = $pdo->prepare("SELECT id, product_id, size, is_available FROM product_sizes WHERE product_id = :pid AND LOWER(size) = LOWER(:size) LIMIT 1");
        $claimUniqueStmt = $pdo->prepare("UPDATE product_sizes SET is_available = 0 WHERE id = :size_id AND is_available = 1");

        $subtotalCents = 0;
        $orderItemsSnapshot = [];

        foreach ($rawItems as $cartKey => $raw) {
            $productId = (int)($raw['product_id'] ?? 0);
            $rawSize = (string)($raw['size'] ?? '');
            $quantity = (int)($raw['quantity'] ?? 1);

            $prodStmt->execute([':id' => $productId]);
            $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('One of the products in your cart is no longer available.');
            }

            if ($product['status'] !== 'active') {
                throw new Exception(sprintf('"%s" is currently not available for purchase.', $product['name']));
            }

            $sizeStmt->execute([':pid' => $productId, ':size' => $rawSize]);
            $sizeRecord = $sizeStmt->fetch(PDO::FETCH_ASSOC);

            if (!$sizeRecord) {
                throw new Exception(sprintf('Size "%s" for "%s" is no longer offered.', $rawSize, $product['name']));
            }

            $productType = $product['product_type'] ?? 'regular';

            if ($productType === 'unique') {
                // Atomic claim of single physical pair
                $claimUniqueStmt->execute([':size_id' => (int)$sizeRecord['id']]);
                if ($claimUniqueStmt->rowCount() !== 1) {
                    throw new Exception(sprintf('Sorry, unique pair "%s" (Size: %s) was just purchased by another customer.', $product['name'], $sizeRecord['size']));
                }
                $finalQty = 1;
            } else {
                // Regular product: verify availability and clamp quantity 1-5
                if ((int)$sizeRecord['is_available'] !== 1) {
                    throw new Exception(sprintf('Size "%s" for "%s" is currently out of stock.', $sizeRecord['size'], $product['name']));
                }
                if ($quantity < 1 || $quantity > 5) {
                    throw new Exception(sprintf('Invalid quantity (%d) for "%s". Allowed range is 1 to 5.', $quantity, $product['name']));
                }
                $finalQty = $quantity;
            }

            $unitPriceCents = money_to_cents($product['price']);
            $lineTotalCents = $unitPriceCents * $finalQty;
            $subtotalCents += $lineTotalCents;

            $orderItemsSnapshot[] = [
                'product_id'    => $product['id'],
                'product_name'  => $product['name'],
                'product_price' => money_format_decimal($unitPriceCents),
                'product_size'  => $sizeRecord['size'],
                'product_color' => $product['color'] ?? null,
                'quantity'      => $finalQty,
                'line_total'    => money_format_decimal($lineTotalCents),
            ];
        }

        // Fetch delivery fee
        $deliveryFeeCents = 0;
        try {
            $stStmt = $pdo->query("SELECT delivery_fee_default FROM site_settings LIMIT 1");
            if ($row = $stStmt->fetch(PDO::FETCH_ASSOC)) {
                $deliveryFeeCents = money_to_cents($row['delivery_fee_default'] ?? 0);
            }
        } catch (PDOException $e) {
            $deliveryFeeCents = 0;
        }

        $grandTotalCents = $subtotalCents + $deliveryFeeCents;

        $subtotalDecimal    = money_format_decimal($subtotalCents);
        $deliveryFeeDecimal = money_format_decimal($deliveryFeeCents);
        $totalAmountDecimal = money_format_decimal($grandTotalCents);

        $orderNumber = generate_order_number($pdo);

        $orderStmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, customer_name, customer_phone, customer_email,
                city, delivery_address, subtotal, delivery_fee, total_amount,
                status, admin_notes
            ) VALUES (
                :order_number, :name, :phone, :email,
                :city, :address, :subtotal, :delivery_fee, :total_amount,
                'pending', :notes
            )
        ");
        $orderStmt->execute([
            ':order_number' => $orderNumber,
            ':name'         => $customerName,
            ':phone'        => $customerPhone,
            ':email'        => $customerEmail !== '' ? $customerEmail : null,
            ':city'         => $city,
            ':address'      => $deliveryAddress,
            ':subtotal'     => $subtotalDecimal,
            ':delivery_fee' => $deliveryFeeDecimal,
            ':total_amount' => $totalAmountDecimal,
            ':notes'        => !empty($orderNotes) ? $orderNotes : null,
        ]);

        $orderId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, product_price,
                product_size, product_color, quantity, line_total
            ) VALUES (
                :order_id, :product_id, :product_name, :product_price,
                :product_size, :product_color, :quantity, :line_total
            )
        ");

        foreach ($orderItemsSnapshot as $snapshot) {
            $itemStmt->execute([
                ':order_id'      => $orderId,
                ':product_id'    => $snapshot['product_id'],
                ':product_name'  => $snapshot['product_name'],
                ':product_price' => $snapshot['product_price'],
                ':product_size'  => $snapshot['product_size'],
                ':product_color' => $snapshot['product_color'],
                ':quantity'      => $snapshot['quantity'],
                ':line_total'    => $snapshot['line_total'],
            ]);
        }

        $pdo->commit();

        // Clear session cart ONLY after commit
        cart_clear();

        $_SESSION['last_order'] = [
            'id'           => $orderId,
            'order_number' => $orderNumber,
            'created_at'   => time(),
        ];

        unset($_SESSION['checkout_lock']);
        unset($_SESSION['old_checkout_input']);

        header('Location: order-success.php?order_number=' . urlencode($orderNumber));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        unset($_SESSION['checkout_lock']);
        set_flash('error', $e->getMessage());
        header('Location: checkout.php');
        exit;
    }
}

// --------------------------------------------------------------------------
// GET Request Handler: Render Checkout View
// --------------------------------------------------------------------------
$cart = cart_hydrate($pdo);

if ($cart['is_empty']) {
    set_flash('error', 'Your shopping cart is empty.');
    header('Location: cart.php');
    exit;
}

if ($cart['has_unavailable_items']) {
    set_flash('error', 'Some items in your cart are no longer available. Please review your cart before proceeding.');
    header('Location: cart.php');
    exit;
}

$oldInput = $_SESSION['old_checkout_input'] ?? [];
unset($_SESSION['old_checkout_input']);

$pageTitle = 'Checkout';
$pageDescription = 'Complete your Cash on Delivery order with ATHLETICA.';

// Flash messages
$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
$flashInfo    = get_flash('info');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
.checkout-layout-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 32px;
    align-items: start;
}

.checkout-card {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: 24px;
}

.checkout-section-title {
    font-size: 1.15rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border-subtle);
    padding-bottom: 14px;
    color: #ffffff;
}

.form-group {
    margin-bottom: 18px;
}

.form-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-label .required {
    color: var(--accent-red);
}

.form-control {
    width: 100%;
    background: var(--bg-surface);
    border: 1px solid var(--border-medium);
    border-radius: var(--radius-sm);
    color: #ffffff;
    padding: 12px 14px;
    font-size: 0.95rem;
    transition: border-color var(--transition-fast);
}

.form-control:focus {
    border-color: #ffffff;
    outline: none;
}

.form-control::placeholder {
    color: var(--text-muted);
}

.checkout-summary-item {
    display: flex;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-subtle);
    align-items: center;
}

.checkout-summary-item:last-child {
    border-bottom: none;
}

.checkout-thumb {
    width: 56px;
    height: 56px;
    border-radius: var(--radius-sm);
    background: #111111;
    border: 1px solid var(--border-subtle);
    overflow: hidden;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.checkout-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.summary-row.total-row {
    border-top: 1px solid var(--border-subtle);
    margin-top: 8px;
    padding-top: 16px;
    color: #ffffff;
    font-size: 1.15rem;
    font-weight: 800;
}

.payment-method-box {
    background: var(--bg-surface);
    border: 2px solid var(--accent-green);
    border-radius: var(--radius-sm);
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.trust-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px dashed var(--border-medium);
    border-radius: var(--radius-sm);
    padding: 14px;
    margin-top: 18px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.trust-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

@media (max-width: 960px) {
    .checkout-layout-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
}
</style>

<main class="section-padding">
    <div class="container">
        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb">
            <a href="index.php">Home</a>
            <span class="breadcrumb-separator">/</span>
            <a href="cart.php">Shopping Cart</a>
            <span class="breadcrumb-separator">/</span>
            <span style="color: #ffffff;">Checkout</span>
        </nav>

        <!-- Page Header -->
        <div class="section-header" style="margin-bottom: 24px;">
            <div>
                <h1 class="section-title">Cash on Delivery Checkout</h1>
                <p class="section-subtitle">Please enter your delivery information to place your order.</p>
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

        <!-- Checkout Form & Order Summary Grid -->
        <form method="POST" action="checkout.php" id="checkout_form">
            <?= csrf_field() ?>
            <div class="checkout-layout-grid">
                <!-- Left Column: Customer Details & Shipping -->
                <div class="checkout-card">
                    <h2 class="checkout-section-title">1. Customer Information</h2>
                    
                    <div class="form-group">
                        <label for="customer_name" class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" 
                               name="customer_name" 
                               id="customer_name" 
                               class="form-control" 
                               required 
                               placeholder="e.g. Muhammad Ali" 
                               value="<?= htmlspecialchars($oldInput['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               autocomplete="name">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="customer_phone" class="form-label">Phone / WhatsApp <span class="required">*</span></label>
                            <input type="tel" 
                                   name="customer_phone" 
                                   id="customer_phone" 
                                   class="form-control" 
                                   required 
                                   placeholder="e.g. 03001234567" 
                                   value="<?= htmlspecialchars($oldInput['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   autocomplete="tel">
                        </div>

                        <div class="form-group">
                            <label for="customer_email" class="form-label">Email Address (Optional)</label>
                            <input type="email" 
                                   name="customer_email" 
                                   id="customer_email" 
                                   class="form-control" 
                                   placeholder="e.g. ali@example.com" 
                                   value="<?= htmlspecialchars($oldInput['customer_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   autocomplete="email">
                        </div>
                    </div>

                    <h2 class="checkout-section-title" style="margin-top: 24px;">2. Delivery Address</h2>

                    <div class="form-group">
                        <label for="city" class="form-label">City <span class="required">*</span></label>
                        <input type="text" 
                               name="city" 
                               id="city" 
                               class="form-control" 
                               required 
                               placeholder="e.g. Lahore, Karachi, Islamabad" 
                               value="<?= htmlspecialchars($oldInput['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               autocomplete="address-level2">
                    </div>

                    <div class="form-group">
                        <label for="delivery_address" class="form-label">Complete Street Address <span class="required">*</span></label>
                        <textarea name="delivery_address" 
                                  id="delivery_address" 
                                  rows="3" 
                                  class="form-control" 
                                  required 
                                  placeholder="House/Apartment #, Street, Block, Area landmark..." 
                                  autocomplete="street-address"><?= htmlspecialchars($oldInput['delivery_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="order_notes" class="form-label">Special Instructions (Optional)</label>
                        <input type="text" 
                               name="order_notes" 
                               id="order_notes" 
                               class="form-control" 
                               placeholder="e.g. Call before delivery, deliver after 3 PM" 
                               value="<?= htmlspecialchars($oldInput['order_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <!-- Payment Method Section -->
                    <h2 class="checkout-section-title" style="margin-top: 24px;">3. Payment Method</h2>
                    <div class="payment-method-box">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <input type="radio" id="cod_payment" name="payment_method" value="cod" checked disabled style="accent-color: var(--accent-green); cursor: pointer;">
                            <label for="cod_payment" style="font-weight: 700; color: #ffffff; cursor: pointer;">
                                💵 Cash on Delivery (COD)
                            </label>
                        </div>
                        <span style="font-size: 0.8rem; font-weight: 700; color: #4ade80; background: #064e3b; padding: 4px 10px; border-radius: 3px;">
                            FREE DELIVERY
                        </span>
                    </div>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px;">
                        Pay cash to the courier representative upon inspecting your parcel at your doorstep. No advance payment required.
                    </p>
                </div>

                <!-- Right Column: Order Review & Submit -->
                <div class="checkout-card">
                    <h2 class="checkout-section-title">Order Summary (<?= (int)$cart['total_quantity'] ?> items)</h2>

                    <!-- Items Preview -->
                    <div style="max-height: 280px; overflow-y: auto; margin-bottom: 16px; padding-right: 4px;">
                        <?php foreach ($cart['items'] as $item): ?>
                            <div class="checkout-summary-item">
                                <div class="checkout-thumb">
                                    <img src="<?= htmlspecialchars($item['primary_image'], ENT_QUOTES, 'UTF-8') ?>" 
                                         alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                                         loading="lazy">
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 0.9rem; font-weight: 700; color: #ffffff; line-height: 1.25; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); display: flex; gap: 8px; flex-wrap: wrap;">
                                        <span>Size: <strong><?= htmlspecialchars($item['size'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                                        <span>•</span>
                                        <span>Qty: <strong><?= (int)$item['quantity'] ?></strong></span>
                                        <?php if (!empty($item['color'])): ?>
                                            <span>•</span>
                                            <span>Color: <?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="font-size: 0.9rem; font-weight: 700; color: #ffffff;">
                                    Rs. <?= number_format($item['line_total'], 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Totals Breakdown -->
                    <div class="summary-row">
                        <span>Subtotal (<?= (int)$cart['total_quantity'] ?> item<?= $cart['total_quantity'] > 1 ? 's' : '' ?>)</span>
                        <span style="font-weight: 700; color: #ffffff;">Rs. <?= number_format($cart['subtotal'], 2) ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Delivery Fee</span>
                        <span style="color: #4ade80; font-weight: 700;">
                            <?= ($cart['delivery_fee_cents'] === 0) ? 'FREE (Rs. 0.00)' : 'Rs. ' . number_format($cart['delivery_fee'], 2) ?>
                        </span>
                    </div>

                    <div class="summary-row total-row">
                        <span>Estimated Total (COD)</span>
                        <span style="font-size: 1.3rem; font-weight: 900; color: #ffffff;">
                            Rs. <?= number_format($cart['grand_total'], 2) ?>
                        </span>
                    </div>

                    <!-- Submit Button -->
                    <div style="margin-top: 24px;">
                        <button type="submit" class="btn btn-primary btn-block" style="padding: 16px; font-size: 1rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; width: 100%;">
                            Place Order (Cash on Delivery) &rarr;
                        </button>
                    </div>

                    <!-- Trust Notice Card -->
                    <div class="trust-card">
                        <div class="trust-item">
                            <span>🔒</span>
                            <span><strong>100% Original Products</strong> — Quality guaranteed.</span>
                        </div>
                        <div class="trust-item">
                            <span>🚚</span>
                            <span><strong>Fast Nationwide Delivery</strong> via trusted couriers (2–4 days).</span>
                        </div>
                        <div class="trust-item">
                            <span>📞</span>
                            <span><strong>Order Verification</strong> — We confirm on WhatsApp before shipping.</span>
                        </div>
                    </div>

                    <p style="font-size: 0.75rem; color: var(--text-muted); text-align: center; margin-top: 14px; line-height: 1.4;">
                        By placing your order, you agree to receive verification calls / WhatsApp messages for order dispatch.
                    </p>
                </div>
            </div>
        </form>
    </div>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
