<?php
/**
 * ATHLETICA — Brand Management: List Brands
 *
 * Displays all brands from the MySQL database with product counts,
 * status badges, and action buttons for Create, Edit, Toggle Status, and Delete.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// Fetch all brands with their associated product counts
$sql = 'SELECT b.id, b.name, b.slug, b.logo, b.description, b.sort_order, b.is_active, b.created_at,
               COUNT(p.id) AS product_count
        FROM brands b
        LEFT JOIN products p ON p.brand_id = b.id
        GROUP BY b.id
        ORDER BY b.sort_order ASC, b.id ASC';

$stmt = $pdo->query($sql);
$brands = $stmt->fetchAll();

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brands — ATHLETICA Admin</title>
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

        .topbar .admin-info {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
            color: #999;
        }

        .btn-logout {
            padding: 6px 14px;
            background: transparent;
            border: 1px solid #333;
            border-radius: 4px;
            color: #ccc;
            font-size: 12px;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            border-color: #e53e3e;
            color: #e53e3e;
        }

        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #ffffff;
            color: #0f0f0f;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            transition: opacity 0.2s;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 4px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .alert-success {
            background: #0f2d1a;
            border: 1px solid #1b4d2e;
            color: #4ade80;
        }

        .alert-error {
            background: #2d0f0f;
            border: 1px solid #4d1b1b;
            color: #f87171;
        }

        .table-card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 6px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #1c1c1c;
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-size: 11px;
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #262626;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #1e1e1e;
            color: #ccc;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #191919;
        }

        .brand-name {
            font-weight: 600;
            color: #ffffff;
            display: block;
        }

        .brand-slug {
            font-size: 12px;
            color: #666;
            font-family: monospace;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-active {
            background: #0f2d1a;
            color: #4ade80;
            border: 1px solid #1b4d2e;
        }

        .badge-inactive {
            background: #2d1a0f;
            color: #fb923c;
            border: 1px solid #4d2e1b;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-action {
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid transparent;
            cursor: pointer;
            background: transparent;
        }

        .btn-edit {
            color: #60a5fa;
            border-color: #1e3a5f;
            background: #0f1e2d;
        }

        .btn-edit:hover {
            border-color: #60a5fa;
        }

        .btn-toggle {
            color: #fb923c;
            border-color: #4d2e1b;
            background: #2d1a0f;
        }

        .btn-toggle:hover {
            border-color: #fb923c;
        }

        .btn-delete {
            color: #f87171;
            border-color: #4d1b1b;
            background: #2d0f0f;
        }

        .btn-delete:hover {
            border-color: #f87171;
        }

        .empty-state {
            padding: 48px 24px;
            text-align: center;
            color: #666;
        }

        .empty-state p {
            margin-bottom: 16px;
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="brand-group">
        <a href="../dashboard.php" class="brand">ATHLETICA</a>
        <nav class="nav-links">
            <a href="../dashboard.php" class="nav-link">Dashboard</a>
            <a href="../categories/index.php" class="nav-link">Categories</a>
            <a href="../products/index.php" class="nav-link">Products</a>
            <a href="index.php" class="nav-link active">Brands</a>
            <a href="../orders/index.php" class="nav-link">Orders</a>
        </nav>
    </div>
    <div class="admin-info">
        <span><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</header>

<main class="main">
    <div class="page-header">
        <h1>Brands</h1>
        <a href="create.php" class="btn-primary">+ Add Brand</a>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="table-card">
        <?php if (empty($brands)): ?>
            <div class="empty-state">
                <p>No brands found in the catalog.</p>
                <a href="create.php" class="btn-primary">+ Create First Brand</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">Sort</th>
                        <th>Brand</th>
                        <th>Description</th>
                        <th style="width: 90px; text-align: center;">Products</th>
                        <th style="width: 100px; text-align: center;">Status</th>
                        <th style="width: 220px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($brands as $brand): ?>
                        <tr>
                            <td style="color: #666; font-family: monospace;"><?= (int)$brand['sort_order'] ?></td>
                            <td>
                                <span class="brand-name"><?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="brand-slug"><?= htmlspecialchars($brand['slug'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <?php if ($brand['description']): ?>
                                    <?= htmlspecialchars(mb_strimwidth($brand['description'], 0, 60, '...'), ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <span style="color: #444;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; font-weight: 600;">
                                <?= (int)$brand['product_count'] ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($brand['is_active']): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="edit.php?id=<?= (int)$brand['id'] ?>" class="btn-action btn-edit">Edit</a>
                                    
                                    <form method="POST" action="toggle-status.php" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$brand['id'] ?>">
                                        <button type="submit" class="btn-action btn-toggle">
                                            <?= $brand['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <a href="delete.php?id=<?= (int)$brand['id'] ?>" class="btn-action btn-delete">Delete</a>
                                </div>
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
