<?php
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

$errors = [];
$formData = [
    'name' => '',
    'slug' => '',
    'category_id' => '',
    'brand_id' => '',
    'product_type' => 'regular',
    'sku' => '',
    'description' => '',
    'condition_notes' => '',
    'features' => '',
    'price' => '',
    'old_price' => '',
    'color' => '',
    'condition' => 'new',
    'status' => 'active',
    'is_featured' => 0,
    'featured_sort_order' => '',
    'is_trending' => 0,
    'is_bestseller' => 0,
    'is_imported' => 0,
    'origin_country' => '',
    'sizes' => ''
];
$submittedSizes = []; 

// Fetch categories for dropdown
$catStmt = $pdo->query("SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands for dropdown
$brandStmt = $pdo->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
$brands = $brandStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid or expired security token. Please try again.';
    }

    // Collect and trim all form inputs
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['slug'] = trim($_POST['slug'] ?? '');
    $formData['category_id'] = trim($_POST['category_id'] ?? '');
    $formData['brand_id'] = trim($_POST['brand_id'] ?? '');
    $formData['product_type'] = trim($_POST['product_type'] ?? 'regular');
    $formData['sku'] = trim($_POST['sku'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['condition_notes'] = trim($_POST['condition_notes'] ?? '');
    $formData['features'] = trim($_POST['features'] ?? '');
    $formData['price'] = trim($_POST['price'] ?? '');
    $formData['old_price'] = trim($_POST['old_price'] ?? '');
    $formData['color'] = trim($_POST['color'] ?? '');
    $formData['condition'] = trim($_POST['condition'] ?? 'new');
    $formData['status'] = trim($_POST['status'] ?? 'active');
    $formData['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
    $formData['advance_payment_percentage'] = (isset($_POST['advance_payment_percentage']) && $_POST['advance_payment_percentage'] !== '') ? (float)$_POST['advance_payment_percentage'] : null;
    $formData['featured_sort_order'] = (isset($_POST['featured_sort_order']) && $_POST['featured_sort_order'] !== '') ? (int)$_POST['featured_sort_order'] : null;
    $formData['is_trending'] = isset($_POST['is_trending']) ? 1 : 0;
    $formData['is_bestseller'] = isset($_POST['is_bestseller']) ? 1 : 0;
    $formData['is_imported'] = isset($_POST['is_imported']) ? 1 : 0;
    $formData['origin_country'] = trim($_POST['origin_country'] ?? '') !== '' ? trim($_POST['origin_country']) : null;

    // Support both footwear checkbox pill grid and dynamic size rows
    $footwearSizes = $_POST['footwear_sizes'] ?? [];
    $sizeLabels = $_POST['size_label'] ?? [];
    $sizeAvails = $_POST['size_available'] ?? [];
    $sizeSorts = $_POST['size_sort'] ?? [];

    // Validations
    if ($formData['name'] === '') {
        $errors[] = 'Product name is required.';
    } elseif (mb_strlen($formData['name']) > 255) {
        $errors[] = 'Product name cannot exceed 255 characters.';
    }

    if ($formData['slug'] === '') {
        $formData['slug'] = slugify($formData['name']);
    } else {
        $formData['slug'] = slugify($formData['slug']);
    }
    
    if ($formData['slug'] !== '') {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE slug = :slug");
        $stmt->execute([':slug' => $formData['slug']]);
        if ($stmt->fetch()) {
            $errors[] = 'The slug (or name) is already in use by another product.';
        }
    }

    if ($formData['category_id'] === '') {
        $errors[] = 'Category is required.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = :id");
        $stmt->execute([':id' => $formData['category_id']]);
        if (!$stmt->fetch()) {
            $errors[] = 'Invalid category selected.';
        }
    }

    if ($formData['brand_id'] !== '') {
        $stmt = $pdo->prepare("SELECT id FROM brands WHERE id = :id");
        $stmt->execute([':id' => $formData['brand_id']]);
        if (!$stmt->fetch()) {
            $errors[] = 'Invalid brand selected.';
        }
    }

    if (!in_array($formData['product_type'], ['regular', 'unique'], true)) {
        $errors[] = 'Invalid product type.';
    }

    if ($formData['sku'] !== '') {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = :sku");
        $stmt->execute([':sku' => $formData['sku']]);
        if ($stmt->fetch()) {
            $errors[] = 'The SKU is already in use by another product.';
        }
    }

    if ($formData['price'] === '' || !is_numeric($formData['price']) || $formData['price'] <= 0) {
        $errors[] = 'Price must be a valid number greater than 0.';
    }

    if ($formData['old_price'] !== '' && (!is_numeric($formData['old_price']) || $formData['old_price'] <= 0)) {
        $errors[] = 'Old price must be a valid number greater than 0 if provided.';
    }

    if (!in_array($formData['condition'], ['new', 'used', 'refurbished'])) {
        $errors[] = 'Invalid condition selected.';
    }

    if (!in_array($formData['status'], ['active', 'inactive', 'out_of_stock'])) {
        $errors[] = 'Invalid status selected.';
    }

    if ($formData['featured_sort_order'] !== null && $formData['featured_sort_order'] <= 0) {
        $errors[] = 'Featured sort order must be a positive integer (e.g. 1, 2, 3...).';
    }

    if ($formData['advance_payment_percentage'] !== null && ($formData['advance_payment_percentage'] < 0 || $formData['advance_payment_percentage'] > 100)) {
        $errors[] = 'Advance payment percentage must be between 0 and 100.';
    }

    $validSizes = [];
    $seenSizeLabels = [];
    $allCleanSizes = [];

    // 1. Process Footwear Checkbox Pills first (if any ticked)
    if (!empty($footwearSizes) && is_array($footwearSizes)) {
        foreach ($footwearSizes as $fSort => $fSize) {
            $fSize = trim($fSize);
            if ($fSize !== '') {
                $cleanVal = preg_replace('/^(EU\s*|EUR\s*)/i', '', $fSize);
                $allCleanSizes[] = $cleanVal;
                $validSizes[] = [
                    'size' => 'EUR ' . $cleanVal,
                    'is_available' => 1,
                    'sort_order' => $fSort + 1
                ];
                $seenSizeLabels[] = mb_strtolower('EUR ' . $cleanVal);
                $seenSizeLabels[] = mb_strtolower($cleanVal);
            }
        }
    }

    // 2. Process custom size rows
    $count = count($sizeLabels);
    for ($i = 0; $i < $count; $i++) {
        $label = trim($sizeLabels[$i] ?? '');
        $avail = isset($sizeAvails[$i]) ? (int)$sizeAvails[$i] : 1;
        $sort = isset($sizeSorts[$i]) ? (int)$sizeSorts[$i] : ($i + 1);

        $submittedSizes[] = ['label' => $label, 'available' => $avail, 'sort' => $sort];

        if ($label !== '') {
            $lowerLabel = mb_strtolower($label);
            if (!in_array($lowerLabel, $seenSizeLabels)) {
                $seenSizeLabels[] = $lowerLabel;
                $clean = preg_replace('/^(EU\s*|EUR\s*)/i', '', $label);
                $allCleanSizes[] = $clean;
                $validSizes[] = [
                    'size' => $label,
                    'is_available' => $avail,
                    'sort_order' => $sort
                ];
            }
        }
    }

    // Comma-separated sizes string for products.sizes column
    $formData['sizes'] = implode(',', array_unique($allCleanSizes));

    if (empty($validSizes)) {
        $errors[] = 'At least one valid size must be provided (select an EU size pill or enter a custom size).';
    }

    if (empty($errors)) {
        $uploadedFiles = [];
        try {
            $pdo->beginTransaction();
            
            // INSERT product
            $stmt = $pdo->prepare('INSERT INTO products (category_id, brand_id, name, slug, sku, description, condition_notes, features, price, old_price, color, `condition`, product_type, status, is_featured, advance_payment_percentage, featured_sort_order, is_trending, is_bestseller, is_imported, origin_country, sizes, created_at, updated_at) VALUES (:category_id, :brand_id, :name, :slug, :sku, :description, :condition_notes, :features, :price, :old_price, :color, :condition, :product_type, :status, :is_featured, :advance_payment_percentage, :featured_sort_order, :is_trending, :is_bestseller, :is_imported, :origin_country, :sizes, NOW(), NOW())');
            $stmt->execute([
                ':category_id'                => $formData['category_id'],
                ':brand_id'                   => $formData['brand_id'] !== '' ? (int)$formData['brand_id'] : null,
                ':name'                       => $formData['name'],
                ':slug'                       => $formData['slug'],
                ':sku'                        => $formData['sku'] !== '' ? $formData['sku'] : null,
                ':description'                => $formData['description'] !== '' ? $formData['description'] : null,
                ':condition_notes'            => $formData['condition_notes'] !== '' ? $formData['condition_notes'] : null,
                ':features'                   => $formData['features'] !== '' ? $formData['features'] : null,
                ':price'                      => $formData['price'],
                ':old_price'                  => $formData['old_price'] !== '' ? $formData['old_price'] : null,
                ':color'                      => $formData['color'] !== '' ? $formData['color'] : null,
                ':condition'                  => $formData['condition'],
                ':product_type'               => $formData['product_type'],
                ':status'                     => $formData['status'],
                ':is_featured'                => $formData['is_featured'],
                ':advance_payment_percentage' => $formData['advance_payment_percentage'],
                ':featured_sort_order'        => $formData['featured_sort_order'],
                ':is_trending'                => $formData['is_trending'],
                ':is_bestseller'              => $formData['is_bestseller'],
                ':is_imported'                => $formData['is_imported'],
                ':origin_country'             => $formData['origin_country'],
                ':sizes'                      => $formData['sizes']
            ]);
            $productId = (int)$pdo->lastInsertId();
            
            // INSERT sizes
            $sizeStmt = $pdo->prepare('INSERT INTO product_sizes (product_id, size, is_available, sort_order, created_at) VALUES (:product_id, :size, :is_available, :sort_order, NOW())');
            foreach ($validSizes as $size) {
                $sizeStmt->execute([
                    ':product_id' => $productId,
                    ':size' => $size['size'],
                    ':is_available' => $size['is_available'],
                    ':sort_order' => $size['sort_order']
                ]);
            }

            
            // Process image uploads (if any)
            if (!empty($_FILES['product_images']['name'][0])) {
                $fileCount = count($_FILES['product_images']['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    $singleFile = [
                        'name'     => $_FILES['product_images']['name'][$i],
                        'type'     => $_FILES['product_images']['type'][$i],
                        'tmp_name' => $_FILES['product_images']['tmp_name'][$i],
                        'error'    => $_FILES['product_images']['error'][$i],
                        'size'     => $_FILES['product_images']['size'][$i],
                    ];
                    
                    if (function_exists('upload_product_image')) {
                        $uploadResult = upload_product_image($singleFile, $productId);
                        if ($uploadResult['success']) {
                            $uploadedFiles[] = $uploadResult['path'];
                            $imgStmt = $pdo->prepare('INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order, created_at) VALUES (:pid, :path, :alt, :primary, :sort, NOW())');
                            $imgStmt->execute([
                                ':pid'     => $productId,
                                ':path'    => $uploadResult['path'],
                                ':alt'     => $formData['name'] . ' - Image ' . ($i + 1),
                                ':primary' => ($i === 0) ? 1 : 0,
                                ':sort'    => $i,
                            ]);
                        } else {
                            $errors[] = 'Image ' . ($i + 1) . ': ' . $uploadResult['error'];
                        }
                    } else {
                        // For safety, fallback if upload_product_image doesn't exist
                        $errors[] = 'Image upload failed. Upload function is not defined.';
                    }
                }
            }
            
            if (!empty($errors)) {
                throw new Exception('Image upload errors');
            }
            
            $pdo->commit();
            set_flash('success', sprintf('Product "%s" created successfully.', $formData['name']));
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Clean up uploaded files
            foreach ($uploadedFiles as $path) {
                $fullPath = __DIR__ . '/../../' . ltrim($path, '/');
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
            if (empty($errors)) {
                $errors[] = 'Failed to create product. Please try again.';
            }
        }
    }
} else {
    // Initial 6 blank rows for sizes
    for ($i = 0; $i < 6; $i++) {
        $submittedSizes[] = ['label' => '', 'available' => 1, 'sort' => $i + 1];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - ATHLETICA</title>
    <style>
        :root {
            --bg-body: #0f0f0f;
            --bg-card: #161616;
            --bg-topbar: #1a1a1a;
            --bg-input: #101010;
            --border-color: #262626;
            --border-topbar: #2a2a2a;
            --border-input: #303030;
            --text-main: #f0f0f0;
            --text-muted: #888;
            --accent: #fff;
            --accent-hover: #e0e0e0;
            --danger: #dc3545;
            --success: #198754;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
        }

        a { text-decoration: none; color: inherit; }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
            height: 60px;
            background-color: var(--bg-topbar);
            border-bottom: 1px solid var(--border-topbar);
        }
        .brand-group {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        .brand {
            font-size: 1.25rem;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .nav-links {
            display: flex;
            gap: 1.5rem;
        }
        .nav-link {
            color: var(--text-muted);
            font-weight: 500;
        }
        .nav-link.active, .nav-link:hover {
            color: var(--text-main);
        }
        .admin-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .btn-logout {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .btn-logout:hover {
            color: var(--text-main);
        }

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .page-title {
            margin: 0;
            font-size: 1.5rem;
        }
        .breadcrumb {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--text-muted);
        }
        .breadcrumb a:hover {
            color: var(--text-main);
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            background-color: #2c0b0e;
            border: 1px solid #842029;
            color: #ea868f;
        }

        .alert-success {
            background-color: #0f291e;
            border: 1px solid #146c43;
            color: #75b798;
        }
        
        .alert ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card-title {
            margin-top: 0;
            margin-bottom: 1.25rem;
            font-size: 1.1rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .form-control {
            width: 100%;
            padding: 0.6rem 0.75rem;
            background-color: var(--bg-input);
            border: 1px solid var(--border-input);
            color: var(--text-main);
            border-radius: 4px;
            box-sizing: border-box;
            font-family: inherit;
        }
        .form-control:focus {
            outline: none;
            border-color: #555;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1em;
            padding-right: 2.5rem;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .checkbox-group label {
            margin: 0;
        }

        .form-note {
            display: block;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* Sizes Table */
        .size-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 0.75rem;
            align-items: center;
        }
        .size-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .btn {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            background-color: var(--bg-input);
            border: 1px solid var(--border-input);
            color: var(--text-main);
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
            transition: all 0.2s;
        }
        .btn:hover {
            background-color: var(--border-input);
        }
        .btn-primary {
            background-color: var(--accent);
            color: #000;
            border-color: var(--accent);
        }
        .btn-primary:hover {
            background-color: var(--accent-hover);
            border-color: var(--accent-hover);
        }
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
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
    <div class="page-header">
        <h1 class="page-title">Add Product</h1>
        <div class="breadcrumb">
            <a href="index.php">Products</a> &rsaquo; Add Product
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if ($flash = get_flash('success')): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="card">
            <h2 class="card-title">Product Details</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Product Name *</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" class="form-control" value="<?= htmlspecialchars($formData['slug'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="form-note">Optional. Auto-generated from name if empty.</span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" data-slug="<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= $formData['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="brand_id">Brand</label>
                    <select id="brand_id" name="brand_id" class="form-control">
                        <option value="">Select a brand (optional)</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= $brand['id'] ?>" <?= $formData['brand_id'] == $brand['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="product_type">Product Model / Type *</label>
                    <select id="product_type" name="product_type" class="form-control" required>
                        <option value="regular" <?= $formData['product_type'] === 'regular' ? 'selected' : '' ?>>Regular (Multi-Size Standard Inventory)</option>
                        <option value="unique" <?= $formData['product_type'] === 'unique' ? 'selected' : '' ?>>Unique (Single 1-of-1 Physical Pair)</option>
                    </select>
                    <span class="form-note">Select "Unique" for 1-of-1 imported physical pairs, locking cart quantities strictly to 1.</span>
                </div>
                <div class="form-group">
                    <label for="sku">SKU</label>
                    <input type="text" id="sku" name="sku" class="form-control" value="<?= htmlspecialchars($formData['sku'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?= htmlspecialchars($formData['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="form-group">
                <label for="condition_notes">Condition Notes (Specific Details for Used / Refurbished Items)</label>
                <textarea id="condition_notes" name="condition_notes" class="form-control" rows="3" placeholder="e.g. Minimal outsole wear, clean upper, original laces, replacement box."><?= htmlspecialchars($formData['condition_notes'], ENT_QUOTES, 'UTF-8') ?></textarea>
                <span class="form-note">Displayed in Product Details when condition is Used or Refurbished.</span>
            </div>

            <div class="form-group">
                <label for="features">Key Features & Highlights (One per line)</label>
                <textarea id="features" name="features" class="form-control" rows="4" placeholder="e.g.&#10;Breathable engineered mesh upper&#10;PureGEL cushioning technology&#10;Durable AHARPLUS rubber outsole"><?= htmlspecialchars($formData['features'], ENT_QUOTES, 'UTF-8') ?></textarea>
                <span class="form-note">Rendered as bulleted highlight points under Product Details.</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="price">Price (PKR) *</label>
                    <input type="number" id="price" name="price" class="form-control" step="0.01" min="0.01" value="<?= htmlspecialchars($formData['price'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="old_price">Original / Old Price (PKR)</label>
                    <input type="number" id="old_price" name="old_price" class="form-control" step="0.01" min="0.01" value="<?= htmlspecialchars($formData['old_price'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="color">Color</label>
                    <input type="text" id="color" name="color" class="form-control" value="<?= htmlspecialchars($formData['color'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="condition">Condition</label>
                    <select id="condition" name="condition" class="form-control">
                        <option value="new" <?= $formData['condition'] === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="used" <?= $formData['condition'] === 'used' ? 'selected' : '' ?>>Used</option>
                        <option value="refurbished" <?= $formData['condition'] === 'refurbished' ? 'selected' : '' ?>>Refurbished</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="out_of_stock" <?= $formData['status'] === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Homepage Badges & Merchandise Tags</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem;">
                        <label class="checkbox-group" style="margin: 0; cursor: pointer;">
                            <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= !empty($formData['is_featured']) ? 'checked' : '' ?>>
                            <span>★ Featured (Homepage Tab)</span>
                        </label>
                        <label class="checkbox-group" style="margin: 0; cursor: pointer;">
                            <input type="checkbox" id="is_trending" name="is_trending" value="1" <?= !empty($formData['is_trending']) ? 'checked' : '' ?>>
                            <span>🔥 Trending / New Arrival ("NEW" badge)</span>
                        </label>
                        <label class="checkbox-group" style="margin: 0; cursor: pointer;">
                            <input type="checkbox" id="is_bestseller" name="is_bestseller" value="1" <?= !empty($formData['is_bestseller']) ? 'checked' : '' ?>>
                            <span>⚡ Best Seller</span>
                        </label>
                        <label class="checkbox-group" style="margin: 0; cursor: pointer;">
                            <input type="checkbox" id="is_imported" name="is_imported" value="1" <?= !empty($formData['is_imported']) ? 'checked' : '' ?>>
                            <span>🌐 Imported</span>
                        </label>
                    </div>
                </div>
                <div class="form-row" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label for="featured_sort_order" style="display: block; margin-bottom: 0.35rem; color: #eee; font-size: 0.9rem; font-weight: 600;">Featured Sort Order</label>
                        <input type="number" id="featured_sort_order" name="featured_sort_order" class="form-control" style="max-width: 220px;" value="<?= htmlspecialchars((string)($formData['featured_sort_order'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. 1" min="1" step="1">
                        <p class="form-note" style="margin-top: 0.25rem; font-size: 0.8rem; color: #888;">1 = first, 2 = second, 3 = third... Lower number appears first in Featured Footwear.</p>
                    </div>
                    <div class="form-group">
                        <label for="advance_payment_percentage" style="display: block; margin-bottom: 0.35rem; color: #eee; font-size: 0.9rem; font-weight: 600;">Advance Payment Percentage</label>
                        <select id="advance_payment_percentage" name="advance_payment_percentage" class="form-control" style="max-width: 220px; padding: 0.5rem; background: #111; border: 1px solid #333; color: #fff; border-radius: 4px;">
                            <?php 
                            $currAdv = isset($formData['advance_payment_percentage']) && $formData['advance_payment_percentage'] !== null ? (float)$formData['advance_payment_percentage'] : 5.0;
                            $advOptions = [
                                0 => '0% (Full COD)',
                                5 => '5%',
                                6 => '6%',
                                7 => '7%',
                                8 => '8%',
                                9 => '9%',
                                10 => '10%',
                            ];
                            foreach ($advOptions as $pctVal => $pctLabel): 
                                $selected = (abs($currAdv - (float)$pctVal) < 0.01) ? 'selected' : '';
                            ?>
                                <option value="<?= $pctVal ?>" <?= $selected ?>><?= $pctLabel ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-note" style="margin-top: 0.25rem; font-size: 0.8rem; color: #888;">Select partial advance required for order dispatch (0% for full COD, or 5%–10%).</p>
                    </div>
                    <div class="form-group">
                        <label for="origin_country" style="display: block; margin-bottom: 0.35rem; color: #eee; font-size: 0.9rem; font-weight: 600;">Origin Country</label>
                        <input type="text" id="origin_country" name="origin_country" class="form-control" style="max-width: 220px;" value="<?= htmlspecialchars((string)($formData['origin_country'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Vietnam, Indonesia, China">
                        <p class="form-note" style="margin-top: 0.25rem; font-size: 0.8rem; color: #888;">Manufacturing origin country. Displays as 'Made in [Country] • Original' on product page.</p>
                    </div>
                </div>
            </div>
        </div>


        <div class="card">
            <h2 class="card-title">Sizes & Inventory</h2>
            <p class="form-note" style="margin-bottom: 1rem;">Tick footwear size pills below or enter custom sizes in the rows.</p>

            <!-- FOOTWEAR MULTI-SIZE CHECKBOX PILL GRID -->
            <div id="footwear-size-picker" style="margin-bottom: 1.5rem; padding: 16px; background: #121212; border: 1px solid #282828; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #fff;">
                            Footwear Size Selector (EU)
                        </span>
                        <span style="font-size: 11px; color: #888; display: block;">
                            For 1-of-1 imported shoes, check ONLY ONE size. For multi-size items, check all in-stock sizes.
                        </span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" onclick="selectAllEUSizes(true)" style="background: #222; border: 1px solid #444; color: #eee; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer;">
                            Select All EU (40-46)
                        </button>
                        <button type="button" onclick="selectAllEUSizes(false)" style="background: #1a1a1a; border: 1px solid #333; color: #aaa; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer;">
                            Clear Sizes
                        </button>
                    </div>
                </div>

                <!-- Checkbox Pills Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 10px;">
                    <?php 
                    $euPillList = ['40', '41', '42', '43', '44', '45', '46'];
                    $tickedPills = !empty($_POST['footwear_sizes']) && is_array($_POST['footwear_sizes']) ? $_POST['footwear_sizes'] : [];
                    foreach ($euPillList as $pill): 
                        $isChecked = in_array($pill, $tickedPills, true) || in_array('EUR ' . $pill, $tickedPills, true);
                    ?>
                        <label class="eu-pill-label" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 8px; border: 1px solid #333; border-radius: 6px; background: #1a1a1a; cursor: pointer; user-select: none; transition: all 0.2s;">
                            <input type="checkbox" name="footwear_sizes[]" value="<?= $pill ?>" <?= $isChecked ? 'checked' : '' ?> class="eu-checkbox" style="margin-bottom: 4px;" onchange="updatePillStyle(this)">
                            <span style="font-size: 10px; color: #888; font-weight: 600; text-transform: uppercase;">EU</span>
                            <span style="font-size: 15px; color: #fff; font-weight: 800;"><?= $pill ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Custom / Individual Size Rows -->
            <details style="margin-bottom: 1rem;">
                <summary style="font-size: 12px; font-weight: 700; color: #888; cursor: pointer; margin-bottom: 8px;">
                    ▶ Advanced: Custom Size Rows (Optional for letter sizes or special half-sizes)
                </summary>
            
            <div id="size-rows">
                <?php foreach ($submittedSizes as $i => $size): ?>
                    <div class="size-row">
                        <input type="text" name="size_label[]" class="form-control size-input" 
                               value="<?= htmlspecialchars($size['label'], ENT_QUOTES, 'UTF-8') ?>" 
                               placeholder="e.g. EUR <?= $i + 40 ?>">
                        <select name="size_available[]" class="form-control size-select">
                            <option value="1" <?= $size['available'] == 1 ? 'selected' : '' ?>>Available</option>
                            <option value="0" <?= $size['available'] == 0 ? 'selected' : '' ?>>Unavailable</option>
                        </select>
                        <input type="number" name="size_sort[]" class="form-control size-sort" 
                               value="<?= (int)$size['sort'] ?>" min="0">
                    </div>
                <?php endforeach; ?>
            </div>
            
            </div>
            
            <button type="button" class="btn btn-sm" onclick="addSizeRow()" style="margin-top: 1rem;">+ Add Size Row</button>
            </details>
        </div>

        <div class="card">
            <h2 class="card-title">Images</h2>
            <div class="form-group">
                <input type="file" name="product_images[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                <span class="form-note">Optional. High-resolution JPG, PNG, or WebP up to 10MB each. Uploaded photos are automatically auto-rotated, web-optimized, and scaled proportionally. First image will be set as primary.</span>
            </div>
        </div>

        <div class="form-actions">
            <a href="index.php" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Product</button>
        </div>
    </form>
</div>

<script>
function selectAllEUSizes(check) {
    const checkboxes = document.querySelectorAll('.eu-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = check;
        updatePillStyle(cb);
    });
}

function updatePillStyle(cb) {
    const label = cb.closest('.eu-pill-label');
    if (!label) return;
    if (cb.checked) {
        label.style.borderColor = '#ffffff';
        label.style.background = '#000000';
        label.style.boxShadow = '0 0 0 1px #ffffff';
    } else {
        label.style.borderColor = '#333333';
        label.style.background = '#1a1a1a';
        label.style.boxShadow = 'none';
    }
}

// Initialize pill styles on load
document.querySelectorAll('.eu-checkbox').forEach(cb => updatePillStyle(cb));

function addSizeRow(label = '', sort = null) {
    const container = document.getElementById('size-rows');
    const count = container.children.length + 1;
    const sortVal = sort !== null ? sort : count;
    const row = document.createElement('div');
    row.className = 'size-row';
    row.innerHTML = `
        <input type="text" name="size_label[]" class="form-control size-input" value="${label}" placeholder="e.g. EUR ${count + 39}">
        <select name="size_available[]" class="form-control size-select">
            <option value="1" selected>Available</option>
            <option value="0">Unavailable</option>
        </select>
        <input type="number" name="size_sort[]" class="form-control size-sort" value="${sortVal}" min="0">
    `;
    container.appendChild(row);
}

// Show/hide footwear size picker based on selected category
const categorySelect = document.getElementById('category_id');
function updateCategoryPickers() {
    const selectedOpt = categorySelect.options[categorySelect.selectedIndex];
    const slug = selectedOpt ? selectedOpt.getAttribute('data-slug') : '';
    const footwearPicker = document.getElementById('footwear-size-picker');
    if (footwearPicker) {
        if (!slug || slug === 'shoes' || slug === 'sliders' || slug === 'sneakers') {
            footwearPicker.style.display = 'block';
        } else {
            footwearPicker.style.display = 'none';
        }
    }
}
if (categorySelect) {
    categorySelect.addEventListener('change', updateCategoryPickers);
    updateCategoryPickers();
}
</script>

</body>
</html>
