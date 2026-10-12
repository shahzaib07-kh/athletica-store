<?php
/**
 * ATHLETICA — Admin Order Management: View & Manage Order
 *
 * Displays complete order details, immutable historical snapshots from order_items,
 * customer contact information with one-click WhatsApp access, state machine status controls,
 * internal administrative notes, and safe unique one-pair inventory release.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    set_flash('error', 'Invalid order ID.');
    header('Location: index.php');
    exit;
}

// 1. Fetch Order Record
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
$orderStmt->execute([':id' => $orderId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    set_flash('error', 'Order not found.');
    header('Location: index.php');
    exit;
}

// 2. Fetch Order Items Snapshots with Current Inventory Status
$itemsStmt = $pdo->prepare("
    SELECT oi.*, 
           p.product_type,
           p.name AS current_product_name,
           p.status AS current_product_status,
           ps.id AS current_size_id,
           ps.is_available AS current_size_availability,
           (SELECT image_path FROM product_images WHERE product_id = oi.product_id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS primary_image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    LEFT JOIN product_sizes ps ON ps.product_id = oi.product_id AND LOWER(ps.size) = LOWER(oi.product_size)
    WHERE oi.order_id = :order_id
    ORDER BY oi.id ASC
");
$itemsStmt->execute([':order_id' => $orderId]);
$orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$cleanPhone = normalize_whatsapp_number($order['customer_phone']);

// Prepare WhatsApp Link with Pre-filled Standardized Verification Message
$waMessage = build_admin_verification_whatsapp_message($order, $orderItems);
$waUrl = "https://wa.me/" . $cleanPhone . "?text=" . rawurlencode($waMessage);

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?> — ATHLETICA Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f0f0f;
            color: #e0e0e0;
            min-height: 100vh;
        }

        .topbar {
            background: #1a1a1a;
            border-bottom: 1px solid #2a2a2a;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar .brand-group {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .topbar .brand {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 3px;
            color: #ffffff;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 16px;
        }

        .nav-link {
            color: #888;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-link:hover, .nav-link.active {
            color: #ffffff;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
            color: #888;
        }

        .btn-logout {
            padding: 6px 14px;
            background: transparent;
            border: 1px solid #333;
            border-radius: 4px;
            color: #ccc;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            border-color: #e74c3c;
            color: #e74c3c;
        }

        .content {
            padding: 32px;
            max-width: 1320px;
            margin: 0 auto;
        }

        .breadcrumb {
            font-size: 13px;
            color: #888;
            margin-bottom: 16px;
        }

        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .order-layout-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 28px;
            align-items: start;
        }

        .admin-card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #ffffff;
            margin-bottom: 16px;
            border-bottom: 1px solid #262626;
            padding-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-pending   { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid #b45309; }
        .status-confirmed { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid #1d4ed8; }
        .status-shipped   { background: rgba(139, 92, 246, 0.15); color: #a78bfa; border: 1px solid #6d28d9; }
        .status-delivered { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid #047857; }
        .status-cancelled { background: rgba(107, 114, 128, 0.15); color: #9ca3af; border: 1px solid #4b5563; }
        .status-returned  { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid #b91c1c; }

        /* Snapshot Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .items-table th {
            background: #1a1a1a;
            color: #888;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #262626;
        }

        .items-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #202020;
            vertical-align: middle;
        }

        .items-table tr:last-child td { border-bottom: none; }

        .item-thumb {
            width: 48px;
            height: 48px;
            border-radius: 4px;
            background: #111;
            border: 1px solid #2a2a2a;
            object-fit: cover;
        }

        /* Detail Field Rows */
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            color: #aaa;
        }

        .detail-row strong {
            color: #ffffff;
        }

        .detail-row.total-row {
            border-top: 1px solid #262626;
            margin-top: 8px;
            padding-top: 14px;
            font-size: 16px;
            font-weight: 800;
            color: #ffffff;
        }

        .form-control {
            width: 100%;
            background: #101010;
            border: 1px solid #303030;
            border-radius: 4px;
            color: #ffffff;
            padding: 10px 12px;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .form-control:focus {
            border-color: #ffffff;
            outline: none;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 10px 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
        }

        .btn-primary:hover { background: #1d4ed8; }

        .btn-release {
            background: #059669;
            color: #ffffff;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: background 0.2s;
        }

        .btn-release:hover { background: #047857; }

        .btn-whatsapp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #15803d;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            width: 100%;
            transition: background 0.2s;
            margin-top: 12px;
        }

        .btn-whatsapp:hover { background: #166534; }

        .alert {
            padding: 12px 18px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .alert-success { background: rgba(16, 185, 129, 0.12); border: 1px solid #10b981; color: #34d399; }
        .alert-error   { background: rgba(239, 68, 68, 0.12); border: 1px solid #ef4444; color: #f87171; }

        @media (max-width: 960px) {
            .order-layout-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- Admin Topbar -->
<header class="topbar">
    <div class="brand-group">
        <a href="../dashboard.php" class="brand">ATHLETICA</a>
        <nav class="nav-links">
            <a href="../dashboard.php" class="nav-link">Dashboard</a>
            <a href="../categories/index.php" class="nav-link">Categories</a>
            <a href="../products/index.php" class="nav-link">Products</a>
            <a href="../brands/index.php" class="nav-link">Brands</a>
            <a href="index.php" class="nav-link active">Orders</a>
        </nav>
    </div>
    <div class="admin-info">
        <span><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</header>

<main class="content">
    <!-- Breadcrumbs -->
    <div class="breadcrumb">
        <a href="index.php">&larr; Back to Orders</a> / 
        <span>Order #<?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 style="font-size: 22px; font-weight: 800; color: #ffffff;">
                Order #<?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p style="font-size: 13px; color: #888; margin-top: 4px;">
                Placed on <?= date('d M Y \a\t h:i A', strtotime($order['created_at'])) ?>
            </p>
        </div>
        <div>
            <span class="status-badge status-<?= strtolower($order['status']) ?>">
                <?= htmlspecialchars(ucfirst($order['status']), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success">✓ <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="order-layout-grid">
        <!-- Left Column: Order Snapshot Items & Financials -->
        <div>
            <!-- Ordered Items Snapshot Card -->
            <div class="admin-card">
                <div class="card-title">
                    <span>Ordered Items (Historical Snapshot)</span>
                    <span style="font-size: 11px; color: #888;">Source of Truth</span>
                </div>

                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Price</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: right;">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <?php 
                                $isUnique = ($item['product_type'] === 'unique');
                                $imgSrc = !empty($item['primary_image']) ? '../../' . $item['primary_image'] : 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'48\' height=\'48\' viewBox=\'0 0 48 48\'%3E%3Crect fill=\'%23111111\' width=\'48\' height=\'48\'/%3E%3Ctext fill=\'%23555555\' font-size=\'8\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\'%3EATH%3C/text%3E%3C/svg%3E';
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" class="item-thumb">
                                        <div>
                                            <div style="font-weight: 700; color: #ffffff;">
                                                <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <?php if (!empty($item['product_color'])): ?>
                                                <div style="font-size: 11px; color: #888;">
                                                    Color: <?= htmlspecialchars($item['product_color'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isUnique): ?>
                                        <span style="background: #b45309; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 3px;">
                                            1-Pair Unique
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #1d4ed8; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 3px;">
                                            Multi-Size
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="background: #222; border: 1px solid #333; padding: 2px 6px; border-radius: 3px; font-weight: 700; font-size: 12px;">
                                        <?= htmlspecialchars($item['product_size'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>Rs. <?= number_format($item['product_price'], 2) ?></td>
                                <td style="text-align: center; font-weight: 700;"><?= (int)$item['quantity'] ?></td>
                                <td style="text-align: right; font-weight: 800; color: #ffffff;">
                                    Rs. <?= number_format($item['line_total'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Unique Inventory Management Card -->
            <div class="admin-card">
                <div class="card-title">
                    <span>Unique One-Pair Inventory Controls</span>
                </div>

                <?php 
                    $hasUniqueItems = false;
                    foreach ($orderItems as $item):
                        if ($item['product_type'] === 'unique'):
                            $hasUniqueItems = true;
                            $sizeAvailable = (int)($item['current_size_availability'] ?? 0);
                            $orderEligibleForRelease = in_array($order['status'], ['cancelled', 'returned']);
                            $orderDelivered = ($order['status'] === 'delivered');
                ?>
                    <div style="background: #111111; border: 1px solid #262626; border-radius: 6px; padding: 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: gap: 12px;">
                        <div>
                            <div style="font-weight: 700; color: #ffffff; font-size: 13px;">
                                <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?> (Size: <?= htmlspecialchars($item['product_size'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                            </div>
                            <div style="font-size: 12px; margin-top: 4px; color: #888;">
                                Current Storefront State: 
                                <?php if ($sizeAvailable === 1): ?>
                                    <span style="color: #34d399; font-weight: 700;">● Available in Catalog</span>
                                <?php else: ?>
                                    <span style="color: #f87171; font-weight: 700;">● Claimed / Out of Stock</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <?php if ($orderDelivered): ?>
                                <span style="font-size: 11px; color: #888; font-weight: 600;">🔒 Delivered (Sold)</span>
                            <?php elseif ($sizeAvailable === 1): ?>
                                <span style="color: #34d399; font-size: 12px; font-weight: 700;">✓ Released</span>
                            <?php elseif ($orderEligibleForRelease && !empty($item['current_size_id'])): ?>
                                <form method="POST" action="release-unique.php" onsubmit="return confirm('Confirm release of this unique physical pair back to live storefront catalog?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                                    <input type="hidden" name="size_id" value="<?= (int)$item['current_size_id'] ?>">
                                    <button type="submit" class="btn-release">
                                        Release to Catalog
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 11px; color: #666;">Locked to Active Order</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php 
                        endif;
                    endforeach; 
                ?>

                <?php if (!$hasUniqueItems): ?>
                    <p style="font-size: 13px; color: #888;">
                        This order contains regular multi-size products only. Stock availability is managed in Product Size settings.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Financials Summary Card -->
            <div class="admin-card">
                <div class="card-title">
                    <span>Payment & Financials</span>
                </div>

                <div class="detail-row">
                    <span>Subtotal</span>
                    <strong>Rs. <?= number_format($order['subtotal'], 2) ?></strong>
                </div>
                <div class="detail-row">
                    <span>Delivery Fee</span>
                    <strong style="color: #34d399;">FREE (Rs. <?= number_format($order['delivery_fee'], 2) ?>)</strong>
                </div>
                <div class="detail-row total-row">
                    <span>Amount Due at Delivery</span>
                    <strong style="color: #ffffff;">Rs. <?= number_format($order['total_amount'], 2) ?></strong>
                </div>
                <div class="detail-row" style="margin-top: 10px;">
                    <span>Payment Method</span>
                    <strong style="color: #60a5fa;">💵 Cash on Delivery (COD)</strong>
                </div>
            </div>
        </div>

        <!-- Right Column: Customer Info, Status Management, Admin Notes -->
        <div>
            <!-- Customer Shipping Information Card -->
            <div class="admin-card">
                <div class="card-title">
                    <span>Customer & Delivery</span>
                </div>

                <div class="detail-row">
                    <span>Customer Name</span>
                    <strong><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="detail-row">
                    <span>Phone / WhatsApp</span>
                    <strong><?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="detail-row">
                    <span>Email Address</span>
                    <strong><?= !empty($order['customer_email']) ? htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8') : 'Not provided' ?></strong>
                </div>

                <div class="detail-row">
                    <span>City</span>
                    <strong><?= htmlspecialchars($order['city'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div style="margin-top: 12px; font-size: 13px; color: #aaa;">
                    <div style="color: #888; font-size: 11px; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Complete Street Address</div>
                    <div style="background: #101010; border: 1px solid #262626; border-radius: 4px; padding: 10px; color: #ffffff; line-height: 1.4;">
                        <?= nl2br(htmlspecialchars($order['delivery_address'], ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                </div>

                <!-- Direct WhatsApp Verification Action -->
                <a href="<?= htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn-whatsapp">
                    <span>💬 Open WhatsApp Chat</span>
                </a>
            </div>

            <!-- Order Status Management Card -->
            <div class="admin-card">
                <div class="card-title">
                    <span>Update Order Status</span>
                </div>

                <form method="POST" action="update-status.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                    <label for="status" style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #888; margin-bottom: 6px;">
                        Order Status
                    </label>
                    <select name="status" id="status" class="form-control">
                        <option value="pending" <?= ($order['status'] === 'pending') ? 'selected' : '' ?>>Pending Verification</option>
                        <option value="confirmed" <?= ($order['status'] === 'confirmed') ? 'selected' : '' ?>>Confirmed & Packing</option>
                        <option value="shipped" <?= ($order['status'] === 'shipped') ? 'selected' : '' ?>>Shipped (In Transit)</option>
                        <option value="delivered" <?= ($order['status'] === 'delivered') ? 'selected' : '' ?>>Delivered (Completed)</option>
                        <option value="cancelled" <?= ($order['status'] === 'cancelled') ? 'selected' : '' ?>>Cancelled (Pre-Dispatch)</option>
                        <option value="returned" <?= ($order['status'] === 'returned') ? 'selected' : '' ?>>Returned (Failed Delivery)</option>
                    </select>

                    <button type="submit" class="btn-primary">
                        Update Status
                    </button>
                </form>
            </div>

            <!-- Internal Admin Notes Card -->
            <div class="admin-card">
                <div class="card-title">
                    <span>Internal Admin Notes</span>
                </div>

                <form method="POST" action="update-notes.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                    <textarea name="admin_notes" 
                              rows="6" 
                              class="form-control" 
                              placeholder="Add courier tracking numbers, customer verification notes, dispatch details..."><?= htmlspecialchars($order['admin_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

                    <button type="submit" class="btn-primary" style="background: #374151;">
                        Save Admin Notes
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

</body>
</html>
