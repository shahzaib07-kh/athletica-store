<?php
/**
 * ATHLETICA — Brand Management: Edit Brand
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    set_flash('error', 'Invalid brand ID.');
    header('Location: index.php');
    exit;
}

// Fetch existing brand
$stmt = $pdo->prepare('SELECT * FROM brands WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$brand = $stmt->fetch();

if (!$brand) {
    set_flash('error', 'Brand not found.');
    header('Location: index.php');
    exit;
}

$errors = [];
$formData = [
    'name'        => $brand['name'],
    'slug'        => $brand['slug'],
    'description' => $brand['description'] ?? '',
    'sort_order'  => (int)$brand['sort_order'],
    'is_active'   => (int)$brand['is_active'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid or expired security token. Please submit the form again.';
    } else {
        $formData['name']        = trim($_POST['name'] ?? '');
        $formData['slug']        = trim($_POST['slug'] ?? '');
        $formData['description'] = trim($_POST['description'] ?? '');
        $formData['sort_order']  = (int)($_POST['sort_order'] ?? 0);
        $formData['is_active']   = isset($_POST['is_active']) ? 1 : 0;

        if ($formData['name'] === '') {
            $errors[] = 'Brand name is required.';
        } elseif (mb_strlen($formData['name']) > 100) {
            $errors[] = 'Brand name must not exceed 100 characters.';
        }

        if ($formData['slug'] === '' && $formData['name'] !== '') {
            $formData['slug'] = slugify($formData['name']);
        } else {
            $formData['slug'] = slugify($formData['slug']);
        }

        if ($formData['slug'] === '') {
            $errors[] = 'A valid slug could not be generated. Please enter one manually.';
        } elseif (mb_strlen($formData['slug']) > 120) {
            $errors[] = 'Slug must not exceed 120 characters.';
        } else {
            // Check slug uniqueness EXCLUDING current brand ID
            $checkStmt = $pdo->prepare('SELECT id FROM brands WHERE slug = :slug AND id != :id LIMIT 1');
            $checkStmt->execute([':slug' => $formData['slug'], ':id' => $id]);
            if ($checkStmt->fetch()) {
                $errors[] = sprintf('The slug "%s" is already in use by another brand.', $formData['slug']);
            }
        }

        // Handle logo replacement if uploaded
        $logoPath = $brand['logo'];
        if (empty($errors) && !empty($_FILES['logo']['name'])) {
            $uploadResult = upload_brand_logo($_FILES['logo'], $id);
            if ($uploadResult['success']) {
                // Delete old logo file if exists
                if ($logoPath && file_exists(__DIR__ . '/../../' . $logoPath)) {
                    @unlink(__DIR__ . '/../../' . $logoPath);
                }
                $logoPath = $uploadResult['path'];
            } else {
                $errors[] = 'Logo upload error: ' . $uploadResult['error'];
            }
        }

        // Update database if no errors
        if (empty($errors)) {
            $sql = 'UPDATE brands
                    SET name = :name, slug = :slug, logo = :logo, description = :description,
                        sort_order = :sort_order, is_active = :is_active
                    WHERE id = :id';
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name'        => $formData['name'],
                ':slug'        => $formData['slug'],
                ':logo'        => $logoPath,
                ':description' => $formData['description'] ?: null,
                ':sort_order'  => $formData['sort_order'],
                ':is_active'   => $formData['is_active'],
                ':id'          => $id,
            ]);

            set_flash('success', sprintf('Brand "%s" updated successfully.', $formData['name']));
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
    <title>Edit Brand: <?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?> — ATHLETICA Admin</title>
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
            max-width: 680px;
            margin: 0 auto;
            padding: 32px 24px;
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

        .page-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 24px;
        }

        .form-card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 6px;
            padding: 28px;
        }

        .alert-error {
            background: #2d0f0f;
            border: 1px solid #4d1b1b;
            color: #f87171;
            padding: 14px 18px;
            border-radius: 4px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .alert-error ul {
            margin-left: 16px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }

        .required::after {
            content: ' *';
            color: #e53e3e;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            background: #101010;
            border: 1px solid #303030;
            border-radius: 4px;
            color: #ffffff;
            font-size: 14px;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #ffffff;
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .form-hint {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #ffffff;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #222;
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

        .btn-submit {
            padding: 10px 24px;
            background: #ffffff;
            color: #0f0f0f;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
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
        <a href="index.php">Brands</a> &rsaquo; Edit: <?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?>
    </div>

    <div class="page-header">
        <h1>Edit Brand</h1>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="edit.php?id=<?= (int)$id ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name" class="required">Brand Name</label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8') ?>"
                       required autofocus maxlength="100">
            </div>

            <div class="form-group">
                <label for="slug">Slug (URL Key)</label>
                <input type="text" id="slug" name="slug" class="form-control"
                       value="<?= htmlspecialchars($formData['slug'], ENT_QUOTES, 'UTF-8') ?>"
                       maxlength="120">
                <p class="form-hint">Unique identifier for URLs (e.g. asics, hoka).</p>
            </div>

            <div class="form-group">
                <label for="logo">Brand Logo</label>
                <?php if (!empty($brand['logo'])): ?>
                    <div style="margin-bottom: 10px;">
                        <span style="font-size: 11px; color: #888;">Current Logo:</span>
                        <div style="background: #222; padding: 6px 12px; border-radius: 4px; display: inline-block; font-size: 12px; color: #aaa;">
                            <?= htmlspecialchars($brand['logo'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                <?php endif; ?>
                <input type="file" id="logo" name="logo" class="form-control" accept="image/jpeg,image/png,image/webp">
                <p class="form-hint">Upload a new file to replace current logo. Max 2MB. JPG, PNG, WebP.</p>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control"><?= htmlspecialchars($formData['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="form-group">
                <label for="sort_order">Sort Order</label>
                <input type="number" id="sort_order" name="sort_order" class="form-control"
                       value="<?= (int)$formData['sort_order'] ?>" min="0" max="65535" style="max-width: 120px;">
                <p class="form-hint">Lower numbers appear first on storefront and brand filters.</p>
            </div>

            <div class="form-group">
                <div class="form-checkbox">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                           <?= $formData['is_active'] ? 'checked' : '' ?>>
                    <label for="is_active" style="text-transform: none; font-size: 13px; color: #ccc; margin-bottom: 0;">
                        Active (visible on storefront and filters)
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Update Brand</button>
            </div>
        </form>
    </div>
</main>

</body>
</html>
