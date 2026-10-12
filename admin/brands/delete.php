<?php
/**
 * ATHLETICA — Brand Management: Delete Brand (Safe Confirmation & Handler)
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// Handle POST request (actual deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid or expired security token.');
        header('Location: index.php');
        exit;
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        set_flash('error', 'Invalid brand ID.');
        header('Location: index.php');
        exit;
    }

    // Check if brand exists
    $stmt = $pdo->prepare('SELECT id, name, logo FROM brands WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $brand = $stmt->fetch();

    if (!$brand) {
        set_flash('error', 'Brand not found.');
        header('Location: index.php');
        exit;
    }

    // Safety check: ensure 0 products are assigned
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE brand_id = :id');
    $countStmt->execute([':id' => $id]);
    $productCount = (int)$countStmt->fetchColumn();

    if ($productCount > 0) {
        set_flash('error', sprintf(
            'Cannot delete brand "%s". It has %d associated product(s). Deactivate the brand instead.',
            $brand['name'],
            $productCount
        ));
        header('Location: index.php');
        exit;
    }

    // Delete logo file if exists
    if (!empty($brand['logo']) && file_exists(__DIR__ . '/../../' . $brand['logo'])) {
        @unlink(__DIR__ . '/../../' . $brand['logo']);
    }

    // Delete brand from database
    $deleteStmt = $pdo->prepare('DELETE FROM brands WHERE id = :id');
    $deleteStmt->execute([':id' => $id]);

    set_flash('success', sprintf('Brand "%s" has been permanently deleted.', $brand['name']));
    header('Location: index.php');
    exit;
}

// Handle GET request (confirmation screen)
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    set_flash('error', 'Invalid brand ID.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM brands WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$brand = $stmt->fetch();

if (!$brand) {
    set_flash('error', 'Brand not found.');
    header('Location: index.php');
    exit;
}

// Check product count
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE brand_id = :id');
$countStmt->execute([':id' => $id]);
$productCount = (int)$countStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Brand: <?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?> — ATHLETICA Admin</title>
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
        }

        .main {
            max-width: 600px;
            margin: 0 auto;
            padding: 48px 24px;
        }

        .breadcrumb {
            font-size: 12px;
            color: #666;
            margin-bottom: 16px;
        }

        .breadcrumb a {
            color: #888;
            text-decoration: none;
        }

        .confirm-card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 6px;
            padding: 32px;
        }

        .danger-badge {
            display: inline-block;
            background: #2d0f0f;
            color: #f87171;
            border: 1px solid #4d1b1b;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        h1 {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 12px;
        }

        .details-box {
            background: #111;
            border: 1px solid #222;
            border-radius: 4px;
            padding: 16px;
            margin: 20px 0;
            font-size: 13px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #1a1a1a;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
        }

        .detail-value {
            color: #ffffff;
            font-weight: 600;
        }

        .blocked-notice {
            background: #2d1a0f;
            border: 1px solid #4d2e1b;
            color: #fb923c;
            padding: 16px;
            border-radius: 4px;
            font-size: 13px;
            line-height: 1.5;
            margin: 20px 0;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-cancel {
            padding: 10px 20px;
            background: transparent;
            border: 1px solid #333;
            border-radius: 4px;
            color: #888;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-delete-confirm {
            padding: 10px 24px;
            background: #dc2626;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-delete-confirm:hover {
            background: #b91c1c;
        }

        .btn-deactivate {
            padding: 10px 20px;
            background: #2d1a0f;
            color: #fb923c;
            border: 1px solid #4d2e1b;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
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
            <a href="index.php" class="nav-link active">Brands</a>
            <a href="../products/index.php" class="nav-link">Products</a>
        </nav>
    </div>
    <div class="admin-info">
        <span><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</header>

<main class="main">
    <div class="breadcrumb">
        <a href="index.php">Brands</a> &rsaquo; Delete Brand
    </div>

    <div class="confirm-card">
        <span class="danger-badge">Permanent Deletion</span>
        <h1>Delete Brand</h1>

        <div class="details-box">
            <div class="detail-row">
                <span class="detail-label">Brand Name:</span>
                <span class="detail-value"><?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Slug:</span>
                <span class="detail-value" style="font-family: monospace;"><?= htmlspecialchars($brand['slug'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Associated Products:</span>
                <span class="detail-value" style="color: <?= $productCount > 0 ? '#f87171' : '#4ade80' ?>;">
                    <?= $productCount ?> product(s)
                </span>
            </div>
        </div>

        <?php if ($productCount > 0): ?>
            <div class="blocked-notice">
                <strong>Deletion Blocked:</strong> This brand cannot be deleted because it is currently assigned to
                <strong><?= $productCount ?> product(s)</strong>.<br><br>
                To remove this brand from customer view, you can edit it and set its status to <strong>Inactive</strong>.
            </div>
            <div class="form-actions">
                <a href="index.php" class="btn-cancel">Back to Brands</a>
                <a href="edit.php?id=<?= (int)$id ?>" class="btn-deactivate">Edit / Deactivate Brand</a>
            </div>
        <?php else: ?>
            <p style="font-size: 13px; color: #999; line-height: 1.6; margin-bottom: 20px;">
                Are you sure you want to permanently delete this brand? This action cannot be undone.
            </p>

            <form method="POST" action="delete.php">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$brand['id'] ?>">

                <div class="form-actions">
                    <a href="index.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-delete-confirm">Permanently Delete</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
