<?php
/**
 * ATHLETICA — Admin Order Management: List Orders
 *
 * Displays all customer Cash on Delivery orders with summary KPI cards,
 * status and search filters, customer details, item snapshots preview,
 * and quick navigation to detailed order management.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// --------------------------------------------------------------------------
// 1. Fetch KPI Metrics
// --------------------------------------------------------------------------
$kpi = [
    'total'     => 0,
    'pending'   => 0,
    'confirmed' => 0,
    'shipped'   => 0,
    'delivered' => 0,
    'cancelled' => 0,
    'returned'  => 0,
];

try {
    $kpiStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) AS shipped,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS returned
        FROM orders
    ");
    $kpiData = $kpiStmt->fetch(PDO::FETCH_ASSOC);
    if ($kpiData) {
        $kpi = array_map(function($v) { return (int)($v ?? 0); }, $kpiData);
    }
} catch (PDOException $e) {
    // Fallback if table empty
}

// --------------------------------------------------------------------------
// 2. Filter & Search Query Building
// --------------------------------------------------------------------------
$filterStatus = trim($_GET['status'] ?? '');
$search       = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($filterStatus !== '' && $filterStatus !== 'all') {
    $where[] = 'o.status = :status';
    $params[':status'] = $filterStatus;
}

if ($search !== '') {
    $where[] = '(o.order_number LIKE :s OR o.customer_name LIKE :s OR o.customer_phone LIKE :s OR o.city LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT o.*,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS total_items,
           (SELECT GROUP_CONCAT(CONCAT(product_name, ' (', IFNULL(product_size, 'N/A'), ')') SEPARATOR ', ') FROM order_items WHERE order_id = o.id) AS items_preview
    FROM orders o
    {$whereClause}
    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — ATHLETICA Admin</title>
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
        }

        /* KPI Cards Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .kpi-card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 8px;
            padding: 18px 20px;
            transition: border-color 0.2s;
        }

        .kpi-card:hover {
            border-color: #383838;
        }

        .kpi-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 6px;
        }

        .kpi-value {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
        }

        /* Filters Bar */
        .filter-bar {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
            justify-content: space-between;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            flex: 1;
        }

        .form-control {
            background: #101010;
            border: 1px solid #303030;
            border-radius: 4px;
            color: #ffffff;
            padding: 8px 12px;
            font-size: 13px;
        }

        .form-control:focus {
            border-color: #ffffff;
            outline: none;
        }

        .btn-filter {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-filter:hover { background: #1d4ed8; }

        .btn-reset {
            color: #888;
            font-size: 13px;
            text-decoration: underline;
            margin-left: 4px;
        }

        /* Table Card */
        .table-card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 8px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #1a1a1a;
            color: #888;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #262626;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #202020;
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #1a1a1a; }

        .order-link {
            color: #ffffff;
            font-weight: 700;
            text-decoration: none;
            letter-spacing: 0.5px;
        }

        .order-link:hover {
            color: #3b82f6;
            text-decoration: underline;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
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

        .btn-view {
            display: inline-block;
            padding: 6px 12px;
            background: #262626;
            border: 1px solid #363636;
            color: #ffffff;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #333333;
            border-color: #ffffff;
        }

        .badge-advance-confirmed {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid #047857;
            white-space: nowrap;
        }

        .badge-advance-cancelled {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid #b91c1c;
            white-space: nowrap;
        }

        .badge-advance-pending {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            background: rgba(107, 114, 128, 0.15);
            color: #9ca3af;
            border: 1px solid #4b5563;
            white-space: nowrap;
        }

        .btn-delete-order {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 10px;
            background: rgba(220, 38, 38, 0.12);
            border: 1px solid rgba(220, 38, 38, 0.35);
            color: #f87171;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-delete-order:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
        }

        .alert {
            padding: 12px 18px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .alert-success { background: rgba(16, 185, 129, 0.12); border: 1px solid #10b981; color: #34d399; }
        .alert-error   { background: rgba(239, 68, 68, 0.12); border: 1px solid #ef4444; color: #f87171; }
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
    <!-- Page Title -->
    <div class="page-header">
        <div>
            <h1>Orders Management</h1>
            <p style="color: #888; font-size: 13px; margin-top: 4px;">Track, verify, and fulfill nationwide Cash on Delivery shipments.</p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success">✓ <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- KPI Metrics -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-title">Total Orders</div>
            <div class="kpi-value"><?= (int)$kpi['total'] ?></div>
        </div>
        <div class="kpi-card" style="border-left: 3px solid #f59e0b;">
            <div class="kpi-title">Pending Verification</div>
            <div class="kpi-value" style="color: #fbbf24;"><?= (int)$kpi['pending'] ?></div>
        </div>
        <div class="kpi-card" style="border-left: 3px solid #3b82f6;">
            <div class="kpi-title">Confirmed & Packing</div>
            <div class="kpi-value" style="color: #60a5fa;"><?= (int)$kpi['confirmed'] ?></div>
        </div>
        <div class="kpi-card" style="border-left: 3px solid #8b5cf6;">
            <div class="kpi-title">In Transit (Shipped)</div>
            <div class="kpi-value" style="color: #a78bfa;"><?= (int)$kpi['shipped'] ?></div>
        </div>
        <div class="kpi-card" style="border-left: 3px solid #10b981;">
            <div class="kpi-title">Delivered</div>
            <div class="kpi-value" style="color: #34d399;"><?= (int)$kpi['delivered'] ?></div>
        </div>
        <div class="kpi-card" style="border-left: 3px solid #ef4444;">
            <div class="kpi-title">Cancelled / Returned</div>
            <div class="kpi-value" style="color: #f87171;"><?= (int)($kpi['cancelled'] + $kpi['returned']) ?></div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="filter-bar">
        <form method="GET" action="index.php" class="filter-form">
            <select name="status" class="form-control" style="width: 170px;">
                <option value="all" <?= ($filterStatus === '' || $filterStatus === 'all') ? 'selected' : '' ?>>All Statuses</option>
                <option value="pending" <?= ($filterStatus === 'pending') ? 'selected' : '' ?>>Pending Verification</option>
                <option value="confirmed" <?= ($filterStatus === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
                <option value="shipped" <?= ($filterStatus === 'shipped') ? 'selected' : '' ?>>Shipped</option>
                <option value="delivered" <?= ($filterStatus === 'delivered') ? 'selected' : '' ?>>Delivered</option>
                <option value="cancelled" <?= ($filterStatus === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                <option value="returned" <?= ($filterStatus === 'returned') ? 'selected' : '' ?>>Returned</option>
            </select>

            <input type="text" 
                   name="search" 
                   class="form-control" 
                   placeholder="Search Order #, Name, Phone, City..." 
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" 
                   style="min-width: 260px; flex: 1;">

            <button type="submit" class="btn-filter">Filter Orders</button>
            <?php if ($filterStatus !== '' || $search !== ''): ?>
                <a href="index.php" class="btn-reset">Reset</a>
            <?php endif; ?>
        </form>

        <span style="font-size: 13px; color: #888;">
            Showing <strong><?= count($orders) ?></strong> order(s)
        </span>
    </div>

    <!-- Orders Table -->
    <div class="table-card">
        <?php if (empty($orders)): ?>
            <div style="padding: 48px; text-align: center; color: #888;">
                <div style="font-size: 28px; margin-bottom: 8px;">📦</div>
                <div style="font-size: 15px; font-weight: 600; color: #ccc;">No orders found</div>
                <div style="font-size: 13px; margin-top: 4px;">No customer orders match the selected filters.</div>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date & Time</th>
                        <th>Customer</th>
                        <th>Items Preview</th>
                        <th>Total Amount</th>
                        <th>Advance Intent</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <?php 
                            $cleanPhone = preg_replace('/[^\d+]/', '', $o['customer_phone']);
                            $statusClass = 'status-' . strtolower($o['status']);
                        ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?= (int)$o['id'] ?>" class="order-link">
                                    <?= htmlspecialchars($o['order_number'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td>
                                <div style="color: #ffffff; font-weight: 500;">
                                    <?= date('d M Y', strtotime($o['created_at'])) ?>
                                </div>
                                <div style="color: #888; font-size: 11px;">
                                    <?= date('h:i A', strtotime($o['created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #ffffff;">
                                    <?= htmlspecialchars($o['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div style="font-size: 12px; color: #888; display: flex; align-items: center; gap: 6px;">
                                    <span><?= htmlspecialchars($o['customer_phone'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <a href="https://wa.me/<?= urlencode($cleanPhone) ?>" target="_blank" title="WhatsApp Customer" style="color: #4ade80; text-decoration: none; font-size: 11px;">
                                        💬
                                    </a>
                                </div>
                                <div style="font-size: 11px; color: #666;">
                                    <?= htmlspecialchars($o['city'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>
                            <td>
                                <div style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #ccc;" title="<?= htmlspecialchars($o['items_preview'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <strong style="color: #ffffff;"><?= (int)$o['total_items'] ?> item(s):</strong> 
                                    <?= htmlspecialchars($o['items_preview'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight: 800; color: #ffffff;">
                                    Rs. <?= number_format($o['total_amount'], 2) ?>
                                </span>
                                <div style="font-size: 11px; color: #888;">COD</div>
                            </td>
                            <td>
                                <?php if (($o['advance_status'] ?? '') === 'confirmed'): ?>
                                    <span class="badge-advance-confirmed">✓ Advance Confirmed</span>
                                <?php elseif (($o['advance_status'] ?? '') === 'cancelled'): ?>
                                    <span class="badge-advance-cancelled">✕ Advance Cancelled</span>
                                <?php else: ?>
                                    <span class="badge-advance-pending">⚠ Advance Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= htmlspecialchars(ucfirst($o['status']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="view.php?id=<?= (int)$o['id'] ?>" class="btn-view" style="margin-right: 6px;">
                                    Manage &rarr;
                                </a>
                                <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete order #<?= htmlspecialchars($o['order_number'], ENT_QUOTES, 'UTF-8') ?>? This action cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                    <button type="submit" class="btn-delete-order" title="Delete Order">🗑️ Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
