<?php
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id || $product_id <= 0) {
    set_flash('error', 'Invalid product ID.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name FROM products WHERE id = :id');
$stmt->execute(['id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: index.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token. Action aborted.');
        header("Location: images.php?id={$product_id}");
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (!isset($_FILES['new_images']) || empty($_FILES['new_images']['name'][0])) {
            set_flash('error', 'No images selected.');
        } else {
            $uploaded_count = 0;
            $errors = [];
            
            // Re-arrange files array
            $files = [];
            foreach ($_FILES['new_images'] as $key => $all) {
                foreach ($all as $i => $val) {
                    $files[$i][$key] = $val;
                }
            }

            foreach ($files as $file) {
                if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $result = upload_product_image($file, $product_id);
                
                if ($result['success']) {
                    $image_path = $result['path'];
                    
                    // Check if primary
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = :id');
                    $stmt->execute(['id' => $product_id]);
                    $count = (int)$stmt->fetchColumn();
                    $is_primary = ($count === 0) ? 1 : 0;
                    
                    // Get next sort order
                    $stmt = $pdo->prepare('SELECT MAX(sort_order) FROM product_images WHERE product_id = :id');
                    $stmt->execute(['id' => $product_id]);
                    $max_sort = $stmt->fetchColumn();
                    $sort_order = ($max_sort !== null && $max_sort !== false) ? ((int)$max_sort + 1) : 0;
                    
                    $alt_text = $product['name'] . ' - Image';
                    
                    $stmt = $pdo->prepare('INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order, created_at) VALUES (:pid, :path, :alt, :primary, :sort, NOW())');
                    $stmt->execute([
                        'pid' => $product_id,
                        'path' => $image_path,
                        'alt' => $alt_text,
                        'primary' => $is_primary,
                        'sort' => $sort_order
                    ]);
                    $uploaded_count++;
                } else {
                    $errors[] = htmlspecialchars($file['name'] ?? 'File', ENT_QUOTES, 'UTF-8') . ': ' . $result['error'];
                }
            }
            
            if ($uploaded_count > 0) {
                set_flash('success', "$uploaded_count image(s) uploaded successfully.");
            }
            if (!empty($errors)) {
                set_flash('error', implode(' | ', $errors));
            }
        }
        header("Location: images.php?id={$product_id}");
        exit;
    } elseif ($action === 'set_primary') {
        $image_id = filter_input(INPUT_POST, 'image_id', FILTER_VALIDATE_INT);
        if ($image_id) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = :pid');
            $stmt->execute(['pid' => $product_id]);
            
            $stmt = $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE id = :id AND product_id = :pid');
            $stmt->execute(['id' => $image_id, 'pid' => $product_id]);
            $pdo->commit();
            
            set_flash('success', 'Primary image updated.');
        }
        header("Location: images.php?id={$product_id}");
        exit;
    } elseif ($action === 'update_sort') {
        $image_id = filter_input(INPUT_POST, 'image_id', FILTER_VALIDATE_INT);
        $sort_order = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT);
        
        if ($image_id && $sort_order !== false) {
            $stmt = $pdo->prepare('UPDATE product_images SET sort_order = :sort WHERE id = :id AND product_id = :pid');
            $stmt->execute(['sort' => $sort_order, 'id' => $image_id, 'pid' => $product_id]);
            set_flash('success', 'Sort order updated.');
        }
        header("Location: images.php?id={$product_id}");
        exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order, id');
$stmt->execute(['id' => $product_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Images: <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> - ATHLETICA</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #0f0f0f; color: #fff; }
        a { color: #fff; text-decoration: none; }
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; background: #161616; border-bottom: 1px solid #262626; }
        .brand-group { display: flex; align-items: center; gap: 24px; }
        .brand { font-size: 20px; font-weight: bold; letter-spacing: 1px; }
        .nav-links { display: flex; gap: 16px; }
        .nav-link { color: #a1a1aa; font-size: 14px; }
        .nav-link.active { color: #fff; }
        .admin-info { display: flex; align-items: center; gap: 16px; font-size: 14px; }
        .btn-logout { background: #262626; padding: 6px 12px; border-radius: 4px; font-size: 13px; }
        .btn-logout:hover { background: #333; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .breadcrumb { font-size: 14px; color: #a1a1aa; margin-bottom: 8px; }
        h1 { margin: 0; font-size: 24px; font-weight: 500; }
        
        .btn { background: #fff; color: #000; padding: 8px 16px; border-radius: 4px; font-size: 14px; border: none; cursor: pointer; }
        .btn:hover { background: #e5e5e5; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .btn-secondary { background: #262626; color: #fff; border: 1px solid #333; }
        .btn-secondary:hover { background: #333; }
        .btn-danger { background: #7f1d1d; color: #fff; border: 1px solid #991b1b; }
        .btn-danger:hover { background: #991b1b; }
        
        .alert { padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #064e3b; color: #34d399; border: 1px solid #065f46; }
        .alert-error { background: #7f1d1d; color: #f87171; border: 1px solid #991b1b; }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
            margin: 20px 0;
        }
        .image-card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 8px;
            padding: 14px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .image-preview-wrap {
            width: 100%;
            height: 200px;
            background: #0d0d0d;
            border-radius: 6px;
            border: 1px solid #222;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 10px;
            padding: 6px;
        }
        .image-card img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 4px;
        }
        .placeholder {
            width: 100%;
            height: 180px;
            background: #262626;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            color: #a1a1aa;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .badge-primary {
            background: #064e3b;
            color: #34d399;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            display: inline-block;
            margin-bottom: 8px;
        }
        .meta-info {
            font-size: 11px;
            color: #71717a;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .image-actions {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .image-actions form {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        input[type="number"] {
            background: #0f0f0f;
            border: 1px solid #333;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            width: 60px;
            font-size: 12px;
        }
        
        .upload-card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 8px;
            padding: 24px;
            margin-top: 32px;
        }
        .upload-card h2 { margin-top: 0; font-size: 18px; margin-bottom: 8px; }
        .upload-form { display: flex; flex-direction: column; gap: 16px; align-items: flex-start; }
        input[type="file"] { background: #0f0f0f; border: 1px solid #333; padding: 8px; border-radius: 4px; width: 100%; box-sizing: border-box; }
        .hint { color: #a1a1aa; font-size: 13px; margin: 0; line-height: 1.5; }
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
        Products › <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> › Manage Images
    </div>
    <div class="header-actions">
        <h1>Manage Images: <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-secondary">Back to Product</a>
    </div>

    <?php if ($msg = get_flash('success')): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($msg = get_flash('error')): ?>
        <div class="alert alert-error"><?= $msg ?></div>
    <?php endif; ?>

    <div class="image-grid">
        <?php foreach ($images as $img): ?>
            <div class="image-card">
                <div>
                    <?php
                    $img_path = __DIR__ . '/../../' . $img['image_path'];
                    $hasFile = (file_exists($img_path) && !is_dir($img_path));
                    $dimText = '';
                    $sizeText = '';
                    if ($hasFile) {
                        $sizeBytes = filesize($img_path);
                        $sizeText = $sizeBytes > 1048576 
                            ? number_format($sizeBytes / 1048576, 2) . ' MB'
                            : number_format($sizeBytes / 1024, 0) . ' KB';
                        $dims = @getimagesize($img_path);
                        if ($dims) {
                            $dimText = "{$dims[0]}×{$dims[1]}px";
                        }
                    }
                    ?>

                    <div class="image-preview-wrap">
                        <?php if ($hasFile): ?>
                            <img src="../../<?= htmlspecialchars($img['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Product image">
                        <?php else: ?>
                            <div class="placeholder">File Missing</div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($img['is_primary']): ?>
                        <div><span class="badge-primary">PRIMARY IMAGE</span></div>
                    <?php endif; ?>
                    
                    <div class="meta-info">
                        <?php if ($dimText || $sizeText): ?>
                            <div style="color: #e4e4e7; font-weight: 600;"><?= $dimText ?> &bull; <?= $sizeText ?></div>
                        <?php endif; ?>
                        <div style="word-break: break-all; margin-top: 3px; font-size: 10px;"><?= htmlspecialchars($img['image_path'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                
                <div class="image-actions">
                    <?php if (!$img['is_primary']): ?>
                        <form method="POST" action="images.php?id=<?= $product['id'] ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_primary">
                            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">Set as Primary</button>
                        </form>
                    <?php endif; ?>
                    
                    <form method="POST" action="images.php?id=<?= $product['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_sort">
                        <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                        <input type="number" name="sort_order" value="<?= htmlspecialchars($img['sort_order'], ENT_QUOTES, 'UTF-8') ?>" min="0">
                        <button type="submit" class="btn btn-secondary btn-sm">Update Sort</button>
                    </form>

                    <form method="POST" action="delete-image.php" onsubmit="return confirm('Delete this image?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="width: 100%;">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($images)): ?>
            <div style="grid-column: 1 / -1; color: #a1a1aa; text-align: center; padding: 40px; background: #161616; border-radius: 8px; border: 1px dashed #333;">
                No images uploaded yet for this product.
            </div>
        <?php endif; ?>
    </div>

    <div class="upload-card">
        <h2>Upload Real Product Images</h2>
        <p class="hint" style="margin-bottom: 12px;">
            Supports high-resolution <strong>JPG, JPEG, PNG, and WebP</strong> up to <strong>10MB</strong> each.<br>
            Uploaded photos are automatically oriented, web-optimized, and scaled proportionally without cropping or distortion.
        </p>
        <form method="POST" action="images.php?id=<?= $product['id'] ?>" enctype="multipart/form-data" class="upload-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload">
            <input type="file" name="new_images[]" multiple accept="image/jpeg,image/png,image/webp">
            <button type="submit" class="btn" style="padding: 10px 24px; font-weight: 600;">Upload & Optimize Images</button>
        </form>
    </div>
</div>

</body>
</html>
