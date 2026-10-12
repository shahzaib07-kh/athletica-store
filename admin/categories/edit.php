<?php
/**
 * ATHLETICA — Category Management: Edit Category
 *
 * Handles editing of existing categories with ID validation, slug uniqueness
 * verification (excluding self), data updates via prepared statements, and PRG pattern.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// 1. Validate Category ID from Query Parameter
$categoryId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$categoryId || $categoryId <= 0) {
    set_flash('error', 'Invalid category ID.');
    header('Location: index.php');
    exit;
}

// 2. Fetch Existing Category from Database
$stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $categoryId]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('error', 'Category not found.');
    header('Location: index.php');
    exit;
}

$errors = [];

// Form data initialized from database row
$formData = [
    'name'        => $category['name'],
    'slug'        => $category['slug'],
    'description' => $category['description'] ?? '',
    'image'       => $category['image'] ?? '',
    'sort_order'  => (int) $category['sort_order'],
    'is_active'   => (int) $category['is_active'],
];

// 3. Process Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF Token
    if (!csrf_verify()) {
        $errors[] = 'Invalid or expired security token. Please try again.';
    } else {
        // Collect & Trim Inputs
        $formData['name']        = trim($_POST['name'] ?? '');
        $formData['slug']        = trim($_POST['slug'] ?? '');
        $formData['description'] = trim($_POST['description'] ?? '');
        $formData['image']       = trim($_POST['image'] ?? '');
        $formData['sort_order']  = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
        $formData['is_active']   = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;

        // Process File Upload if provided
        if (!empty($_FILES['image_file']['name'])) {
            $uploadResult = upload_category_image($_FILES['image_file'], $categoryId);
            if ($uploadResult['success']) {
                $formData['image'] = $uploadResult['path'];
            } else {
                $errors[] = 'Image upload failed: ' . $uploadResult['error'];
            }
        }

        // Validate Inputs
        if ($formData['name'] === '') {
            $errors[] = 'Category name is required.';
        } elseif (mb_strlen($formData['name']) > 100) {
            $errors[] = 'Category name must not exceed 100 characters.';
        }

        // Clean / Generate Slug
        $slug = $formData['slug'] !== '' ? slugify($formData['slug']) : slugify($formData['name']);
        if ($slug === '') {
            $slug = 'category-' . $categoryId;
        }

        if (mb_strlen($slug) > 120) {
            $errors[] = 'Category slug must not exceed 120 characters.';
        }

        // Check for Duplicate Slug (excluding this category's own record)
        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug AND id != :id LIMIT 1');
            $stmt->execute([
                ':slug' => $slug,
                ':id'   => $categoryId,
            ]);
            if ($stmt->fetch()) {
                $errors[] = sprintf('The slug "%s" is already in use by another category. Please choose a different name or slug.', htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'));
            }
        }

        // Update Category Record
        if (empty($errors)) {
            $updateSql = 'UPDATE categories
                          SET name = :name,
                              slug = :slug,
                              description = :description,
                              image = :image,
                              sort_order = :sort_order,
                              is_active = :is_active
                          WHERE id = :id';
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([
                ':name'        => $formData['name'],
                ':slug'        => $slug,
                ':description' => $formData['description'] !== '' ? $formData['description'] : null,
                ':image'       => $formData['image'] !== '' ? $formData['image'] : null,
                ':sort_order'  => $formData['sort_order'],
                ':is_active'   => $formData['is_active'],
                ':id'          => $categoryId,
            ]);

            set_flash('success', sprintf('Category "%s" was updated successfully.', $formData['name']));
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category — ATHLETICA Admin</title>
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
            max-width: 700px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        .breadcrumb {
            font-size: 13px;
            color: #777;
            margin-bottom: 16px;
        }

        .breadcrumb a {
            color: #aaa;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: #fff;
        }

        .header-row {
            margin-bottom: 24px;
        }

        .header-row h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 8px;
            padding: 28px;
        }

        .alert-error {
            background: #240c0c;
            border: 1px solid #6b1d1d;
            color: #f87171;
            padding: 14px 18px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .alert-error ul {
            margin-left: 18px;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #bbb;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .form-group .hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            background: #101010;
            border: 1px solid #303030;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #555;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #ffffff;
            cursor: pointer;
        }

        .checkbox-group label {
            font-size: 14px;
            color: #ccc;
            cursor: pointer;
            margin-bottom: 0;
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #242424;
        }

        .btn-submit {
            background: #ffffff;
            color: #000000;
            padding: 11px 24px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #e0e0e0;
        }

        .btn-cancel {
            background: transparent;
            color: #888;
            padding: 11px 18px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid #333;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            color: #fff;
            border-color: #555;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand-group">
            <a href="../dashboard.php" class="brand">ATHLETICA</a>
            <nav class="nav-links">
                <a href="../dashboard.php" class="nav-link">Dashboard</a>
                <a href="index.php" class="nav-link active">Categories</a>
                <a href="../brands/index.php" class="nav-link">Brands</a>
                <a href="../products/index.php" class="nav-link">Products</a>
            </nav>
        </div>

        <div class="admin-info">
            <span><?= htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </header>

    <main class="container">
        <div class="breadcrumb">
            <a href="index.php">Categories</a> &rsaquo; Edit Category
        </div>

        <div class="header-row">
            <h1>Edit Category: <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <strong>Please correct the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="edit.php?id=<?= (int) $categoryId ?>" enctype="multipart/form-data" autocomplete="off">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="name">Category Name *</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-control"
                        placeholder="e.g. Running Shoes"
                        value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8') ?>"
                        required
                        autofocus
                    >
                    <div class="hint">The name as it will appear in menus and store navigation.</div>
                </div>

                <div class="form-group">
                    <label for="slug">URL Slug *</label>
                    <input
                        type="text"
                        id="slug"
                        name="slug"
                        class="form-control"
                        placeholder="e.g. running-shoes"
                        value="<?= htmlspecialchars($formData['slug'], ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                    <div class="hint">Unique URL path for this category.</div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-control"
                        placeholder="Brief description of products in this category..."
                    ><?= htmlspecialchars($formData['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="image_file">Upload / Replace Category Image</label>
                    <?php if (!empty($formData['image'])): ?>
                        <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 10px; padding: 10px; background: #222; border-radius: 6px; border: 1px solid #333;">
                            <img src="../../<?= htmlspecialchars($formData['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Category thumbnail" style="width: 70px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #444;">
                            <div style="font-size: 12px; color: #aaa;">
                                Current: <code style="color: #4ade80;"><?= htmlspecialchars($formData['image'], ENT_QUOTES, 'UTF-8') ?></code>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input
                        type="file"
                        id="image_file"
                        name="image_file"
                        class="form-control"
                        accept="image/jpeg,image/png,image/webp"
                        style="padding: 8px;"
                    >
                    <input
                        type="hidden"
                        id="image"
                        name="image"
                        value="<?= htmlspecialchars($formData['image'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                    <div class="hint">Upload a JPG, PNG, or WebP image (max 8MB). Automatically optimized.</div>
                </div>

                <div class="form-group">
                    <label for="sort_order">Display Sort Order</label>
                    <input
                        type="number"
                        id="sort_order"
                        name="sort_order"
                        class="form-control"
                        value="<?= (int) $formData['sort_order'] ?>"
                    >
                    <div class="hint">Lower numbers appear first in storefront navigation (e.g. 1, 2, 3).</div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            <?= $formData['is_active'] ? 'checked' : '' ?>
                        >
                        <label for="is_active">Active (Visible on storefront)</label>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="index.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-submit">Update Category</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
