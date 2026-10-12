<?php
/**
 * ATHLETICA — Category Management: List Categories
 *
 * Displays all categories from the MySQL database with product counts,
 * status badges, and action buttons for Create, Edit, Toggle Status, and Delete.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// Fetch all categories with their associated product counts
$sql = 'SELECT c.id, c.name, c.slug, c.description, c.image, c.sort_order, c.is_active, c.created_at,
               COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id
        ORDER BY c.sort_order ASC, c.id ASC';

$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll();

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories — ATHLETICA Admin</title>
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
            transition: border-color 0.2s, color 0.2s;
        }

        .btn-logout:hover {
            border-color: #e74c3c;
            color: #e74c3c;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .header-row h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            color: #000000;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: #e0e0e0;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .alert-success {
            background: #0a200e;
            border: 1px solid #165624;
            color: #4ade80;
        }

        .alert-error {
            background: #240c0c;
            border: 1px solid #6b1d1d;
            color: #f87171;
        }

        .card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 8px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #1f1f1f;
            color: #888;
            font-weight: 600;
            font-size: 12px;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 14px 16px;
            border-bottom: 1px solid #2a2a2a;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #222;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #1a1a1a;
        }

        .cat-name {
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 2px;
        }

        .cat-slug {
            font-size: 12px;
            color: #777;
            font-family: monospace;
        }

        .cat-desc {
            color: #999;
            font-size: 13px;
            max-width: 260px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-active {
            background: #0a200e;
            color: #4ade80;
            border: 1px solid #165624;
        }

        .badge-inactive {
            background: #262626;
            color: #888888;
            border: 1px solid #383838;
        }

        .badge-count {
            background: #1f1f1f;
            color: #aaa;
            border: 1px solid #333;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        .actions-cell {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 4px;
            text-decoration: none;
            border: 1px solid #333;
            background: #202020;
            color: #ccc;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-action:hover {
            border-color: #666;
            color: #fff;
        }

        .btn-toggle {
            background: transparent;
        }

        .btn-toggle:hover {
            border-color: #f59e0b;
            color: #f59e0b;
        }

        .btn-delete {
            background: transparent;
        }

        .btn-delete:hover {
            border-color: #ef4444;
            color: #ef4444;
        }

        .empty-state {
            padding: 60px 24px;
            text-align: center;
            color: #666;
        }

        .empty-state p {
            font-size: 15px;
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
                <a href="index.php" class="nav-link active">Shop Categories</a>
                <a href="../brands/index.php" class="nav-link">Brands</a>
                <a href="../products/index.php" class="nav-link">Products</a>
                <a href="../orders/index.php" class="nav-link">Orders</a>
            </nav>
        </div>

        <div class="admin-info">
            <span><?= htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </header>

    <main class="container">
        <div class="header-row">
            <div>
                <h1>Category Management</h1>
            </div>
            <a href="create.php" class="btn-primary">+ Add Category</a>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <p>No categories found in the database.</p>
                    <a href="create.php" class="btn-primary">Create First Category</a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 70px;">Thumbnail</th>
                            <th style="width: 60px;">Order</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($cat['image'])): ?>
                                        <img src="../../<?= htmlspecialchars($cat['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #333; background: #222;">
                                    <?php else: ?>
                                        <div style="width: 48px; height: 48px; border-radius: 6px; border: 1px dashed #444; display: flex; align-items: center; justify-content: center; color: #666; font-size: 10px; font-weight: 700; background: #161616;">
                                            NO IMG
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $cat['sort_order'] ?></td>
                                <td>
                                    <div class="cat-name"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="cat-slug"><?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td>
                                    <div class="cat-desc" title="<?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <?= !empty($cat['description']) ? htmlspecialchars($cat['description'], ENT_QUOTES, 'UTF-8') : '<span style="color:#555;">—</span>' ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-count"><?= (int) $cat['product_count'] ?></span>
                                </td>
                                <td>
                                    <?php if ($cat['is_active']): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #888; font-size: 13px;">
                                    <?= date('M d, Y', strtotime($cat['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="actions-cell" style="justify-content: flex-end;">
                                        <a href="edit.php?id=<?= (int) $cat['id'] ?>" class="btn-action">Edit</a>

                                        <!-- Toggle Status Form (POST + CSRF) -->
                                        <form method="POST" action="toggle-status.php" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                            <button type="submit" class="btn-action btn-toggle" title="Click to change visibility status">
                                                <?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <!-- Delete Form (POST + CSRF) -->
                                        <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete the category \'<?= htmlspecialchars(addslashes($cat['name']), ENT_QUOTES, 'UTF-8') ?>\'?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                            <button type="submit" class="btn-action btn-delete">
                                                Delete
                                            </button>
                                        </form>
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
