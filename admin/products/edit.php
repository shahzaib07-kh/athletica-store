<?php
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    set_flash('error', 'Invalid product ID.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
$stmt->execute(['id' => $id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: index.php');
    exit;
}

// Fetch categories with slug for client-side category awareness
$stmt = $pdo->query('SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order');
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query('SELECT id, name FROM brands WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM product_sizes WHERE product_id = :id');
$stmt->execute(['id' => $id]);
$sizes_count = $stmt->fetchColumn();

// Fetch existing active sizes for checkbox pill pre-selection
$stmt = $pdo->prepare('SELECT size FROM product_sizes WHERE product_id = :id AND is_available = 1');
$stmt->execute(['id' => $id]);
$existingActiveSizes = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = :id AND is_primary = 1 LIMIT 1');
$stmt->execute(['id' => $id]);
$primary_image = $stmt->fetchColumn();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid or expired security token. Please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $brand_id = filter_input(INPUT_POST, 'brand_id', FILTER_VALIDATE_INT) ?: null;
    $product_type = trim($_POST['product_type'] ?? 'regular');
    $sku = trim($_POST['sku'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $condition_notes = trim($_POST['condition_notes'] ?? '');
    $condition_notes = $condition_notes === '' ? null : $condition_notes;
    $features = trim($_POST['features'] ?? '');
    $features = $features === '' ? null : $features;
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $old_price = trim($_POST['old_price'] ?? '');
    $old_price = $old_price === '' ? null : filter_var($old_price, FILTER_VALIDATE_FLOAT);
    $color = trim($_POST['color'] ?? '');
    $condition = $_POST['condition'] ?? 'new';
    $status = $_POST['status'] ?? 'active';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $advance_payment_percentage = (isset($_POST['advance_payment_percentage']) && $_POST['advance_payment_percentage'] !== '') ? filter_var($_POST['advance_payment_percentage'], FILTER_VALIDATE_FLOAT) : null;
    $featured_sort_order = (isset($_POST['featured_sort_order']) && $_POST['featured_sort_order'] !== '') ? (int)$_POST['featured_sort_order'] : null;
    $is_trending = isset($_POST['is_trending']) ? 1 : 0;
    $is_bestseller = isset($_POST['is_bestseller']) ? 1 : 0;
    $is_imported = isset($_POST['is_imported']) ? 1 : 0;
    $origin_country = trim($_POST['origin_country'] ?? '');
    $origin_country = $origin_country === '' ? null : $origin_country;
    $footwear_sizes = $_POST['footwear_sizes'] ?? [];

    if ($featured_sort_order !== null && $featured_sort_order <= 0) {
        $errors[] = 'Featured sort order must be a positive integer (e.g. 1, 2, 3...).';
    }

    if ($advance_payment_percentage !== null && ($advance_payment_percentage === false || $advance_payment_percentage < 0 || $advance_payment_percentage > 100)) {
        $errors[] = 'Advance payment percentage must be a number between 0 and 100.';
    }

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (mb_strlen($name) > 255) {
        $errors[] = 'Name must be 255 characters or less.';
    }

    if ($slug === '') {
        $slug = slugify($name);
    }
    
    $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = :slug AND id != :id');
    $stmt->execute(['slug' => $slug, 'id' => $id]);
    if ($stmt->fetch()) {
        $errors[] = 'Slug must be unique.';
    }

    if (!$category_id) {
        $errors[] = 'Category is required.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id');
        $stmt->execute(['id' => $category_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'Invalid category selected.';
        }
    }

    if ($brand_id !== null) {
        $stmt = $pdo->prepare('SELECT id FROM brands WHERE id = :id');
        $stmt->execute(['id' => $brand_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'Invalid brand selected.';
        }
    }

    if (!in_array($product_type, ['regular', 'unique'], true)) {
        $errors[] = 'Invalid product type.';
    }

    if ($sku !== '') {
        $stmt = $pdo->prepare('SELECT id FROM products WHERE sku = :sku AND id != :id');
        $stmt->execute(['sku' => $sku, 'id' => $id]);
        if ($stmt->fetch()) {
            $errors[] = 'SKU must be unique.';
        }
    } else {
        $sku = null;
    }

    if ($price === false || $price <= 0) {
        $errors[] = 'Price must be greater than 0.';
    }

    if ($old_price !== null && ($old_price === false || $old_price <= 0)) {
        $errors[] = 'Old price must be greater than 0.';
    }

    $allowed_conditions = ['new', 'used', 'refurbished'];
    if (!in_array($condition, $allowed_conditions, true)) {
        $errors[] = 'Invalid condition.';
    }

    $allowed_statuses = ['active', 'inactive', 'out_of_stock'];
    if (!in_array($status, $allowed_statuses, true)) {
        $errors[] = 'Invalid status.';
    }

    // Process footwear sizes checkboxes if submitted
    $sizesColumnVal = $product['sizes'] ?? '';
    if (isset($_POST['has_footwear_picker'])) {
        $cleanSizesArr = [];
        if (is_array($footwear_sizes) && !empty($footwear_sizes)) {
            foreach ($footwear_sizes as $fSort => $fVal) {
                $clean = preg_replace('/^(EU\s*|EUR\s*)/i', '', trim($fVal));
                if ($clean !== '') {
                    $cleanSizesArr[] = $clean;
                }
            }
        }
        $sizesColumnVal = implode(',', array_unique($cleanSizesArr));
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE products SET category_id = :category_id, brand_id = :brand_id, name = :name, slug = :slug, sku = :sku, description = :description, condition_notes = :condition_notes, features = :features, price = :price, old_price = :old_price, color = :color, `condition` = :condition, product_type = :product_type, status = :status, is_featured = :is_featured, advance_payment_percentage = :advance_payment_percentage, featured_sort_order = :featured_sort_order, is_trending = :is_trending, is_bestseller = :is_bestseller, is_imported = :is_imported, origin_country = :origin_country, sizes = :sizes WHERE id = :id');
        $stmt->execute([
            'category_id'                => $category_id,
            'brand_id'                   => $brand_id,
            'name'                       => $name,
            'slug'                       => $slug,
            'sku'                        => $sku,
            'description'                => $description,
            'condition_notes'            => $condition_notes,
            'features'                   => $features,
            'price'                      => $price,
            'old_price'                  => $old_price,
            'color'                      => $color,
            'condition'                  => $condition,
            'product_type'               => $product_type,
            'status'                     => $status,
            'is_featured'                => $is_featured,
            'advance_payment_percentage' => $advance_payment_percentage,
            'featured_sort_order'        => $featured_sort_order,
            'is_trending'                => $is_trending,
            'is_bestseller'              => $is_bestseller,
            'is_imported'                => $is_imported,
            'origin_country'             => $origin_country,
            'sizes'                      => $sizesColumnVal,
            'id'                         => $id
        ]);

        // If footwear picker was active and submitted, sync into product_sizes table as well
        if (isset($_POST['has_footwear_picker']) && is_array($footwear_sizes)) {
            // Delete existing EUR sizes and re-insert checked ones
            $delStmt = $pdo->prepare("DELETE FROM product_sizes WHERE product_id = :id AND (size LIKE 'EUR %' OR size LIKE 'EU %' OR size REGEXP '^[0-9]+$')");
            $delStmt->execute(['id' => $id]);

            $insStmt = $pdo->prepare('INSERT INTO product_sizes (product_id, size, is_available, sort_order, created_at) VALUES (:pid, :size, 1, :sort, NOW())');
            foreach ($footwear_sizes as $sIdx => $fVal) {
                $clean = preg_replace('/^(EU\s*|EUR\s*)/i', '', trim($fVal));
                if ($clean !== '') {
                    $insStmt->execute([
                        'pid' => $id,
                        'size' => 'EUR ' . $clean,
                        'sort' => $sIdx + 1
                    ]);
                }
            }
        }
        
        set_flash('success', 'Product updated successfully.');
        header('Location: index.php');
        exit;
    }
} else {
    // Populate form with existing product data on GET
    $name = $product['name'];
    $slug = $product['slug'];
    $category_id = $product['category_id'];
    $brand_id = $product['brand_id'];
    $product_type = $product['product_type'] ?? 'regular';
    $sku = $product['sku'];
    $description = $product['description'];
    $condition_notes = $product['condition_notes'] ?? '';
    $features = $product['features'] ?? '';
    $price = $product['price'];
    $old_price = $product['old_price'];
    $color = $product['color'];
    $condition = $product['condition'];
    $status = $product['status'];
    $is_featured = (int)$product['is_featured'];
    $advance_payment_percentage = $product['advance_payment_percentage'] !== null ? (float)$product['advance_payment_percentage'] : null;
    $featured_sort_order = $product['featured_sort_order'] !== null ? (int)$product['featured_sort_order'] : null;
    $is_trending = (int)($product['is_trending'] ?? 0);
    $is_bestseller = (int)($product['is_bestseller'] ?? 0);
    $is_imported = (int)($product['is_imported'] ?? 0);
    $origin_country = $product['origin_country'] ?? '';
    $sizesColumnVal = $product['sizes'] ?? '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - ATHLETICA</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background-color: #0f0f0f; color: #fff; }
        .topbar { background: #1a1a1a; border-bottom: 1px solid #2a2a2a; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .brand-group { display: flex; align-items: center; gap: 2rem; }
        .brand { font-size: 1.25rem; font-weight: bold; color: #fff; text-decoration: none; letter-spacing: 1px; }
        .nav-links { display: flex; gap: 1rem; }
        .nav-link { color: #888; text-decoration: none; }
        .nav-link:hover, .nav-link.active { color: #fff; }
        .admin-info { display: flex; align-items: center; gap: 1rem; }
        .btn-logout { color: #888; text-decoration: none; font-size: 0.875rem; }
        .btn-logout:hover { color: #fff; }
        
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .breadcrumb { margin-bottom: 1.5rem; color: #888; }
        .breadcrumb a { color: #888; text-decoration: none; }
        .breadcrumb a:hover { color: #fff; }
        
        .card { background: #161616; border: 1px solid #262626; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; }
        
        .form-group { margin-bottom: 1rem; }
        .form-row { display: flex; gap: 1rem; }
        .form-row .form-group { flex: 1; }
        
        label { display: block; margin-bottom: 0.5rem; color: #ccc; font-size: 0.875rem; }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%; padding: 0.5rem; background: #101010; border: 1px solid #303030; color: #fff; border-radius: 4px; box-sizing: border-box;
        }
        input[type="text"]:focus, input[type="number"]:focus, select:focus, textarea:focus {
            outline: none; border-color: #555;
        }
        textarea { resize: vertical; min-height: 100px; }
        
        .checkbox-group { display: flex; align-items: center; gap: 0.5rem; }
        
        .actions { margin-top: 1.5rem; display: flex; gap: 1rem; }
        .btn { padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.875rem; text-decoration: none; text-align: center; }
        .btn-primary { background: #fff; color: #000; border: none; font-weight: bold; }
        .btn-primary:hover { background: #e0e0e0; }
        .btn-secondary { background: transparent; color: #fff; border: 1px solid #333; }
        .btn-secondary:hover { background: #1a1a1a; }
        
        .error-list { background: rgba(255, 0, 0, 0.1); border: 1px solid rgba(255, 0, 0, 0.2); padding: 1rem; border-radius: 4px; color: #ff6b6b; margin-bottom: 1.5rem; }
        .error-list ul { margin: 0; padding-left: 1.5rem; }
        
        .flash { padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; }
        .flash-success { background: rgba(0, 255, 0, 0.1); border: 1px solid rgba(0, 255, 0, 0.2); color: #4ade80; }
        .flash-error { background: rgba(255, 0, 0, 0.1); border: 1px solid rgba(255, 0, 0, 0.2); color: #ff6b6b; }
        
        .quick-links { display: flex; gap: 2rem; align-items: center; }
        .quick-link-item { display: flex; align-items: center; gap: 1rem; }
        .thumbnail { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; background: #101010; border: 1px solid #303030; }
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
        <a href="index.php">Products</a> &rsaquo; Edit: <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
    </div>

    <?php if ($flash = get_flash('success')): ?>
        <div class="flash flash-success"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flash = get_flash('error')): ?>
        <div class="flash flash-error"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error-list">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="edit.php?id=<?= $id ?>">
            <?= csrf_field() ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="slug">Slug (Leave blank to auto-generate)</label>
                    <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" data-slug="<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="brand_id">Brand</label>
                    <select id="brand_id" name="brand_id">
                        <option value="">-- Select Brand (Optional) --</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $brand_id == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="product_type">Product Model / Type *</label>
                    <select id="product_type" name="product_type" required>
                        <option value="regular" <?= $product_type === 'regular' ? 'selected' : '' ?>>Regular (Multi-Size Catalog Item)</option>
                        <option value="unique" <?= $product_type === 'unique' ? 'selected' : '' ?>>Unique (Single Imported Physical Pair)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sku">SKU</label>
                    <input type="text" id="sku" name="sku" value="<?= htmlspecialchars((string)$sku, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?= htmlspecialchars((string)$description, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="form-group">
                <label for="condition_notes">Condition Notes (Specific Details for Used / Refurbished Items)</label>
                <textarea id="condition_notes" name="condition_notes" rows="3" placeholder="e.g. Minimal outsole wear, clean upper, original laces, replacement box."><?= htmlspecialchars((string)($condition_notes ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <small style="color: #888; font-size: 0.8rem; display: block; margin-top: 4px;">Displayed in Product Details when condition is Used or Refurbished.</small>
            </div>

            <div class="form-group">
                <label for="features">Key Features & Highlights (One per line)</label>
                <textarea id="features" name="features" rows="4" placeholder="e.g.&#10;Breathable engineered mesh upper&#10;PureGEL cushioning technology&#10;Durable AHARPLUS rubber outsole"><?= htmlspecialchars((string)($features ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <small style="color: #888; font-size: 0.8rem; display: block; margin-top: 4px;">Rendered as bulleted highlight points under Product Details.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="price">Price *</label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" value="<?= htmlspecialchars((string)$price, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="old_price">Old Price</label>
                    <input type="number" id="old_price" name="old_price" step="0.01" min="0.01" value="<?= htmlspecialchars((string)$old_price, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="color">Color</label>
                    <input type="text" id="color" name="color" value="<?= htmlspecialchars((string)$color, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="condition">Condition</label>
                    <select id="condition" name="condition">
                        <option value="new" <?= $condition === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="used" <?= $condition === 'used' ? 'selected' : '' ?>>Used</option>
                        <option value="refurbished" <?= $condition === 'refurbished' ? 'selected' : '' ?>>Refurbished</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="out_of_stock" <?= $status === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
            </div>


            <div class="form-group">
                <label>Homepage Badges & Merchandise Tags</label>
                <div style="display: flex; flex-wrap: wrap; gap: 1.25rem; margin-top: 0.5rem;">
                    <label class="checkbox-group" style="margin: 0; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= !empty($is_featured) ? 'checked' : '' ?>>
                        <span style="font-size: 0.9rem;">★ Featured (Homepage Tab)</span>
                    </label>
                    <label class="checkbox-group" style="margin: 0; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="is_trending" name="is_trending" value="1" <?= !empty($is_trending) ? 'checked' : '' ?>>
                        <span style="font-size: 0.9rem;">🔥 Trending / New Arrival ("NEW" badge)</span>
                    </label>
                    <label class="checkbox-group" style="margin: 0; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="is_bestseller" name="is_bestseller" value="1" <?= !empty($is_bestseller) ? 'checked' : '' ?>>
                        <span style="font-size: 0.9rem;">⚡ Best Seller</span>
                    </label>
                    <label class="checkbox-group" style="margin: 0; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="is_imported" name="is_imported" value="1" <?= !empty($is_imported) ? 'checked' : '' ?>>
                        <span style="font-size: 0.9rem;">🌐 Imported</span>
                    </label>
                </div>
                <div class="form-row" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label for="featured_sort_order" style="display: block; margin-bottom: 0.35rem; color: #eee; font-size: 0.9rem; font-weight: 600;">Featured Sort Order</label>
                        <input type="number" id="featured_sort_order" name="featured_sort_order" class="form-control" style="max-width: 220px; padding: 0.5rem; background: #111; border: 1px solid #333; color: #fff; border-radius: 4px;" value="<?= htmlspecialchars((string)($featured_sort_order ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. 1" min="1" step="1">
                        <p class="form-note" style="margin-top: 0.25rem; font-size: 0.8rem; color: #888;">1 = first, 2 = second, 3 = third... Lower number appears first in Featured Footwear.</p>
                    </div>
                    <div class="form-group">
                        <label for="advance_payment_percentage" style="display: block; margin-bottom: 0.35rem; color: #eee; font-size: 0.9rem; font-weight: 600;">Advance Payment Percentage</label>
                        <select id="advance_payment_percentage" name="advance_payment_percentage" class="form-control" style="max-width: 220px; padding: 0.5rem; background: #111; border: 1px solid #333; color: #fff; border-radius: 4px;">
                            <?php 
                            $currAdv = isset($advance_payment_percentage) && $advance_payment_percentage !== null ? (float)$advance_payment_percentage : 5.0;
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
                        <input type="text" id="origin_country" name="origin_country" class="form-control" style="max-width: 220px; padding: 0.5rem; background: #111; border: 1px solid #333; color: #fff; border-radius: 4px;" value="<?= htmlspecialchars((string)($origin_country ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Vietnam, Indonesia, China">
                        <p class="form-note" style="margin-top: 0.25rem; font-size: 0.8rem; color: #888;">Manufacturing origin country. Displays as 'Made in [Country] • Original' on product page.</p>
                    </div>
                </div>
            </div>

            <!-- FOOTWEAR MULTI-SIZE CHECKBOX PILL GRID -->
            <input type="hidden" name="has_footwear_picker" value="1">
            <div id="footwear-size-picker" style="margin-top: 1.5rem; margin-bottom: 1rem; padding: 16px; background: #121212; border: 1px solid #282828; border-radius: 8px;">
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
                    $currentSizesList = array_map('trim', explode(',', (string)$sizesColumnVal));
                    foreach ($euPillList as $pill): 
                        $isChecked = in_array($pill, $currentSizesList, true) ||
                                     in_array('EUR ' . $pill, $existingActiveSizes, true) ||
                                     in_array('EU ' . $pill, $existingActiveSizes, true) ||
                                     in_array($pill, $existingActiveSizes, true);
                    ?>
                        <label class="eu-pill-label" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 8px; border: 1px solid #333; border-radius: 6px; background: #1a1a1a; cursor: pointer; user-select: none; transition: all 0.2s;">
                            <input type="checkbox" name="footwear_sizes[]" value="<?= $pill ?>" <?= $isChecked ? 'checked' : '' ?> class="eu-checkbox" style="margin-bottom: 4px;" onchange="updatePillStyle(this)">
                            <span style="font-size: 10px; color: #888; font-weight: 600; text-transform: uppercase;">EU</span>
                            <span style="font-size: 15px; color: #fff; font-weight: 800;"><?= $pill ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="actions" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem;">Quick Links</h3>
        <div class="quick-links">
            <div class="quick-link-item">
                <a href="sizes.php?id=<?= $id ?>" class="btn btn-secondary">Manage Advanced Sizes (<?= $sizes_count ?> sizes)</a>
            </div>
            <div class="quick-link-item">
                <a href="images.php?id=<?= $id ?>" class="btn btn-secondary">Manage Images</a>
                <?php if ($primary_image): ?>
                    <img src="../../<?= htmlspecialchars($primary_image, ENT_QUOTES, 'UTF-8') ?>" alt="Primary Image" class="thumbnail">
                <?php endif; ?>
            </div>
        </div>
    </div>
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
