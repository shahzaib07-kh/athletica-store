<?php
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// Handle POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token. Action aborted.');
        header('Location: index.php');
        exit;
    }
    
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $confirm_delete = filter_input(INPUT_POST, 'confirm_delete', FILTER_VALIDATE_INT);
    
    if (!$product_id || $product_id <= 0) {
        set_flash('error', 'Invalid product ID.');
        header('Location: index.php');
        exit;
    }
    
    if ($confirm_delete !== 1) {
        set_flash('error', 'You must confirm the deletion.');
        header("Location: delete.php?id=$product_id");
        exit;
    }
    
    // Load product to confirm it exists
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = :id");
    $stmt->execute(['id' => $product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        set_flash('error', 'Product not found.');
        header('Location: index.php');
        exit;
    }
    
    // Delete physical images
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = :id");
    $stmt->execute(['id' => $product_id]);
    $images = $stmt->fetchAll();
    
    foreach ($images as $img) {
        $fullPath = __DIR__ . '/../../' . ltrim($img['image_path'], '/');
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
        $pInfo = pathinfo($fullPath);
        $thumbVariant = $pInfo['dirname'] . '/' . $pInfo['filename'] . '-thumb.webp';
        if (file_exists($thumbVariant)) {
            @unlink($thumbVariant);
        }
    }
    
    // Delete product (CASCADE handles sizes and images DB records)
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute(['id' => $product_id]);
    
    set_flash('success', 'Product "' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . '" has been permanently deleted.');
    header('Location: index.php');
    exit;
}

// Handle GET Request
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    set_flash('error', 'Invalid product ID.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: index.php');
    exit;
}

// Category name
$stmt = $pdo->prepare("SELECT name FROM categories WHERE id = :category_id");
$stmt->execute(['category_id' => $product['category_id']]);
$category = $stmt->fetchColumn();

// Size count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM product_sizes WHERE product_id = :id");
$stmt->execute(['id' => $id]);
$size_count = $stmt->fetchColumn();

// Image count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = :id");
$stmt->execute(['id' => $id]);
$image_count = $stmt->fetchColumn();

// Order history count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :id");
$stmt->execute(['id' => $id]);
$order_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Product - ATHLETICA</title>
    <style>
        body { background: #0f0f0f; color: #ffffff; font-family: sans-serif; margin: 0; padding: 0; }
        .topbar { background: #1a1a1a; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #2a2a2a; }
        .brand-group { display: flex; align-items: center; gap: 30px; }
        .brand { color: #fff; text-decoration: none; font-size: 20px; font-weight: bold; letter-spacing: 2px; }
        .nav-links { display: flex; gap: 20px; }
        .nav-link { color: #888; text-decoration: none; text-transform: uppercase; font-size: 13px; font-weight: 600; }
        .nav-link.active, .nav-link:hover { color: #fff; }
        .admin-info { display: flex; align-items: center; gap: 15px; }
        .btn-logout { color: #888; text-decoration: none; font-size: 13px; border: 1px solid #333; padding: 6px 12px; border-radius: 4px; }
        .btn-logout:hover { color: #fff; border-color: #fff; }
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .breadcrumb { margin-bottom: 20px; color: #888; font-size: 14px; }
        .breadcrumb a { color: #fff; text-decoration: none; }
        .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; padding: 30px; }
        .product-title { font-size: 24px; font-weight: bold; margin: 0 0 10px 0; }
        .product-meta { color: #888; margin-bottom: 20px; display: flex; gap: 15px; font-size: 14px; }
        .warning-box { background: #2a0a0a; border: 1px solid #6b1d1d; border-radius: 6px; padding: 20px; margin: 20px 0; }
        .warning-box h3 { color: #f87171; margin-top: 0; margin-bottom: 10px; }
        .warning-box p { margin: 0; color: #ffb3b3; line-height: 1.5; }
        .details-section { margin-top: 20px; }
        .details-section h4 { margin: 0 0 10px 0; color: #ccc; }
        .details-list { list-style: none; padding: 0; margin: 0 0 20px 0; }
        .details-list li { margin-bottom: 8px; color: #aaa; display: flex; align-items: center; gap: 10px; }
        .icon-cross { color: #f87171; font-weight: bold; }
        .icon-check { color: #4ade80; font-weight: bold; }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; align-items: center; }
        .btn-cancel { color: #888; text-decoration: none; padding: 12px 24px; font-size: 13px; font-weight: 600; text-transform: uppercase; }
        .btn-cancel:hover { color: #fff; }
        .btn-delete { background: #dc2626; color: #ffffff; padding: 12px 24px; border-radius: 4px; font-size: 13px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; border: none; cursor: pointer; }
        .btn-delete:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand-group">
            <a href="../dashboard.php" class="brand">ATHLETICA</a>
            <nav class="nav-links">
                <a href="../dashboard.php" class="nav-link">Dashboard</a>
                <a href="../categories/index.php" class="nav-link">Categories</a>
                <a href="../brands/index.php" class="nav-link">Brands</a>
                <a href="index.php" class="nav-link active">Products</a>
            </nav>
        </div>

        <div class="admin-info">
            <span><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </header>

    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Products</a> &rsaquo; Delete: <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        
        <?php if ($msg = get_flash('error')): ?>
            <div style="background: #2a0a0a; border: 1px solid #6b1d1d; color: #f87171; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h1 class="product-title"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="product-meta">
                <span>Category: <?= htmlspecialchars($category ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></span>
                <span>|</span>
                <span>Price: $<?= number_format($product['price'], 2) ?></span>
            </div>

            <div class="warning-box">
                <h3>You are about to permanently delete this product.</h3>
                <p>This action CANNOT be undone.</p>
            </div>

            <div class="details-section">
                <h4>WHAT WILL BE DELETED:</h4>
                <ul class="details-list">
                    <li><span class="icon-cross">&#10006;</span> The product record</li>
                    <li><span class="icon-cross">&#10006;</span> <?= $size_count ?> size record(s)</li>
                    <li><span class="icon-cross">&#10006;</span> <?= $image_count ?> image file(s) and database records</li>
                </ul>

                <h4>WHAT WILL BE PRESERVED:</h4>
                <ul class="details-list">
                    <?php if ($order_count > 0): ?>
                        <li><span class="icon-check">&#10004;</span> <?= $order_count ?> order history record(s) will remain intact</li>
                        <li><span class="icon-check">&#10004;</span> Customer order details are preserved through snapshot fields</li>
                    <?php else: ?>
                        <li><span class="icon-check">&#10004;</span> No order history exists for this product</li>
                    <?php endif; ?>
                </ul>
            </div>

            <form method="POST" action="delete.php">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <div style="margin: 25px 0;">
                    <label style="color:#f87171; display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="checkbox" name="confirm_delete" value="1" required style="width:18px;height:18px;">
                        I understand this action is permanent and cannot be undone
                    </label>
                </div>
                <div class="form-actions">
                    <a href="index.php" class="btn-cancel">Cancel &mdash; Go Back</a>
                    <button type="submit" class="btn-delete">Permanently Delete Product</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
