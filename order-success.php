<?php
/**
 * ATHLETICA — Order Confirmation / Success Page
 *
 * Displays the confirmed order details, immutable order_items snapshot rows,
 * customer delivery information, FREE delivery status, nationwide Cash on Delivery instructions,
 * and prominent pre-filled customer WhatsApp order sharing CTA.
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$orderNumber = trim($_GET['order_number'] ?? '');

if ($orderNumber === '') {
    header('Location: index.php');
    exit;
}

// Fetch order record from database
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = :num LIMIT 1");
$orderStmt->execute([':num' => $orderNumber]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: index.php');
    exit;
}

// Fetch historical order item snapshots
$itemsStmt = $pdo->prepare("
    SELECT oi.*, 
           (SELECT image_path FROM product_images WHERE product_id = oi.product_id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS primary_image
    FROM order_items oi
    WHERE oi.order_id = :order_id
    ORDER BY oi.id ASC
");
$itemsStmt->execute([':order_id' => (int)$order['id']]);
$orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pageTitle = 'Order Confirmed — ' . $order['order_number'];
$pageDescription = 'Thank you for your order. Your Cash on Delivery order has been received by ATHLETICA.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// Prepare WhatsApp Customer Deep Link
$businessWaRaw = $siteSettings['whatsapp_number'] ?? '+923001234567';
$normalizedBusinessWa = normalize_whatsapp_number($businessWaRaw);
$customerWaMessage = build_customer_order_whatsapp_message($order, $orderItems);
$customerWaUrl = 'https://wa.me/' . $normalizedBusinessWa . '?text=' . rawurlencode($customerWaMessage);
?>

<style>
.success-layout-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 32px;
    align-items: start;
}

.success-card {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: 24px;
    margin-bottom: 24px;
}

.success-header-banner {
    text-align: center;
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.08) 0%, rgba(10, 10, 10, 0) 100%);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: var(--radius-md);
    padding: 36px 20px;
    margin-bottom: 32px;
}

.order-badge-highlight {
    display: inline-block;
    background: var(--bg-surface);
    border: 1px solid var(--border-medium);
    border-radius: var(--radius-sm);
    padding: 8px 16px;
    font-size: 1.15rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: 1px;
    margin-top: 10px;
}

.btn-whatsapp-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: #15803d;
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 800;
    padding: 14px 28px;
    border-radius: var(--radius-sm);
    text-decoration: none;
    transition: background 0.2s, transform 0.1s;
    box-shadow: 0 4px 14px rgba(21, 128, 61, 0.35);
    margin-top: 20px;
}

.btn-whatsapp-cta:hover {
    background: #166534;
    color: #ffffff;
    transform: translateY(-1px);
}

.info-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}

.info-value {
    font-size: 0.95rem;
    color: #ffffff;
    margin-bottom: 12px;
    line-height: 1.4;
}

.snapshot-table {
    width: 100%;
    border-collapse: collapse;
}

.snapshot-table th {
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 8px;
    border-bottom: 1px solid var(--border-subtle);
}

.snapshot-table td {
    padding: 14px 8px;
    border-bottom: 1px solid var(--border-subtle);
    font-size: 0.9rem;
    color: #ffffff;
    vertical-align: middle;
}

.snapshot-table tr:last-child td {
    border-bottom: none;
}

@media (max-width: 960px) {
    .success-layout-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}
</style>

<main class="section-padding">
    <div class="container">
        <!-- Order Confirmed Banner -->
        <div class="success-header-banner">
            <div style="font-size: 3rem; color: #10b981; line-height: 1; margin-bottom: 12px;">✓</div>
            <h1 style="font-size: 1.85rem; font-weight: 900; text-transform: uppercase; letter-spacing: -0.02em; margin-bottom: 8px;">
                Order Placed Successfully!
            </h1>
            <p style="font-size: 0.95rem; color: var(--text-secondary); max-width: 520px; margin: 0 auto 12px; line-height: 1.5;">
                Thank you for shopping with ATHLETICA. Your Cash on Delivery order has been received and registered in our system.
            </p>
            <div>
                <span class="order-badge-highlight">
                    <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <!-- Primary WhatsApp CTA -->
            <div>
                <a href="<?= htmlspecialchars($customerWaUrl, ENT_QUOTES, 'UTF-8') ?>" 
                   target="_blank" 
                   rel="noopener noreferrer" 
                   class="btn-whatsapp-cta">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="display:inline-block; vertical-align:middle;">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    <span>Send Order Details to WhatsApp</span>
                </a>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px;">
                    Optional: Share order details with our support team on WhatsApp for priority order verification.
                </p>
            </div>
        </div>

        <div class="success-layout-grid">
            <!-- Left Column: Ordered Items Snapshots -->
            <div>
                <div class="success-card">
                    <h2 style="font-size: 1.1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">
                        Ordered Items (Snapshot)
                    </h2>

                    <div style="overflow-x: auto;">
                        <table class="snapshot-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Size</th>
                                    <th>Price</th>
                                    <th style="text-align: center;">Qty</th>
                                    <th style="text-align: right;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color: #ffffff;">
                                                <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <?php if (!empty($item['product_color'])): ?>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                    Color: <?= htmlspecialchars($item['product_color'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="background: var(--bg-surface); border: 1px solid var(--border-medium); padding: 2px 6px; border-radius: 3px; font-size: 0.75rem; font-weight: 700;">
                                                <?= htmlspecialchars($item['product_size'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td>Rs. <?= number_format($item['product_price'], 2) ?></td>
                                        <td style="text-align: center; font-weight: 700;"><?= (int)$item['quantity'] ?></td>
                                        <td style="text-align: right; font-weight: 800;">Rs. <?= number_format($item['line_total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Dispatch Notice Card -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 20px; display: flex; align-items: flex-start; gap: 14px;">
                    <span style="font-size: 1.5rem;">📦</span>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5;">
                        <strong style="color: #ffffff;">What happens next?</strong><br>
                        Our dispatch representative will contact you on WhatsApp / phone at <strong><?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?></strong> to confirm your address before shipping.
                    </div>
                </div>
            </div>

            <!-- Right Column: Customer & Payment Summary -->
            <div>
                <!-- Customer Details -->
                <div class="success-card">
                    <h2 style="font-size: 1.1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">
                        Delivery Details
                    </h2>

                    <div class="info-label">Customer Name</div>
                    <div class="info-value"><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="info-label">Phone / WhatsApp</div>
                    <div class="info-value"><?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?></div>

                    <?php if (!empty($order['customer_email'])): ?>
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?= htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <div class="info-label">City</div>
                    <div class="info-value"><?= htmlspecialchars($order['city'], ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="info-label">Complete Shipping Address</div>
                    <div class="info-value"><?= nl2br(htmlspecialchars($order['delivery_address'], ENT_QUOTES, 'UTF-8')) ?></div>

                    <div class="info-label">Payment Method</div>
                    <div class="info-value" style="color: #4ade80; font-weight: 700;">
                        💵 Cash on Delivery (COD)
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="success-card">
                    <h2 style="font-size: 1.1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">
                        Payment Summary
                    </h2>

                    <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.9rem; color: var(--text-secondary);">
                        <span>Subtotal</span>
                        <span style="color: #ffffff; font-weight: 700;">Rs. <?= number_format($order['subtotal'], 2) ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.9rem; color: var(--text-secondary);">
                        <span>Delivery Fee</span>
                        <span style="color: #4ade80; font-weight: 700;">FREE (Rs. 0.00)</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; padding: 14px 0 0 0; margin-top: 10px; border-top: 1px solid var(--border-subtle); font-size: 1.15rem; font-weight: 900; color: #ffffff;">
                        <span>Amount Due at Delivery</span>
                        <span>Rs. <?= number_format($order['total_amount'], 2) ?></span>
                    </div>

                    <div style="margin-top: 24px;">
                        <a href="shop.php" class="btn btn-primary btn-block" style="text-align: center; padding: 14px;">
                            Continue Shopping &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
