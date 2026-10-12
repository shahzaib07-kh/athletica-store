<?php
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
if (!$product_id && isset($_GET['id'])) {
    $product_id = $_GET['id'];
}

if (!$product_id || $product_id <= 0) {
    set_flash('error', 'Invalid product ID.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, slug FROM products WHERE id = :id");
$stmt->execute(['id' => $product_id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token. Action aborted.');
        header("Location: sizes.php?id={$product_id}");
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add_size') {
        $size_label = trim($_POST['size_label'] ?? '');
        $is_available = filter_input(INPUT_POST, 'is_available', FILTER_VALIDATE_INT) ?? 1;
        $sort_order = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT) ?? 0;

        if (empty($size_label)) {
            set_flash('error', 'Size label is required.');
        } elseif (strlen($size_label) > 30) {
            set_flash('error', 'Size label must be 30 characters or less.');
        } else {
            $stmt = $pdo->prepare("SELECT id FROM product_sizes WHERE product_id = :pid AND size = :size");
            $stmt->execute(['pid' => $product_id, 'size' => $size_label]);
            if ($stmt->fetch()) {
                set_flash('error', 'This size already exists for the product.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO product_sizes (product_id, size, is_available, sort_order, created_at) VALUES (:pid, :size, :avail, :sort, NOW())");
                $stmt->execute([
                    'pid' => $product_id,
                    'size' => $size_label,
                    'avail' => $is_available,
                    'sort' => $sort_order
                ]);
                set_flash('success', 'Size added successfully.');
            }
        }
        header("Location: sizes.php?id=$product_id");
        exit;
    } elseif ($action === 'toggle_availability') {
        $size_id = filter_input(INPUT_POST, 'size_id', FILTER_VALIDATE_INT);
        if ($size_id) {
            $stmt = $pdo->prepare("UPDATE product_sizes SET is_available = NOT is_available WHERE id = :id AND product_id = :pid");
            $stmt->execute(['id' => $size_id, 'pid' => $product_id]);
            set_flash('success', 'Availability toggled.');
        }
        header("Location: sizes.php?id=$product_id");
        exit;
    } elseif ($action === 'update_sort') {
        $size_id = filter_input(INPUT_POST, 'size_id', FILTER_VALIDATE_INT);
        $sort_order = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT);
        if ($size_id && $sort_order !== false) {
            $stmt = $pdo->prepare("UPDATE product_sizes SET sort_order = :sort WHERE id = :id AND product_id = :pid");
            $stmt->execute(['sort' => $sort_order, 'id' => $size_id, 'pid' => $product_id]);
            set_flash('success', 'Sort order updated.');
        }
        header("Location: sizes.php?id=$product_id");
        exit;
    } elseif ($action === 'delete_size') {
        $size_id = filter_input(INPUT_POST, 'size_id', FILTER_VALIDATE_INT);
        if ($size_id) {
            $stmt = $pdo->prepare("DELETE FROM product_sizes WHERE id = :id AND product_id = :pid");
            $stmt->execute(['id' => $size_id, 'pid' => $product_id]);
            set_flash('success', 'Size deleted.');
        }
        header("Location: sizes.php?id=$product_id");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM product_sizes WHERE product_id = :id ORDER BY sort_order, id");
$stmt->execute(['id' => $product_id]);
$sizes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sizes: <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> - ATHLETICA</title>
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
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .breadcrumb { margin-bottom: 20px; color: #888; font-size: 14px; }
        .breadcrumb a { color: #fff; text-decoration: none; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 24px; }
        .btn-back { color: #aaa; text-decoration: none; padding: 8px 16px; border: 1px solid #444; border-radius: 4px; font-size: 14px; }
        .btn-back:hover { background: #222; color: #fff; }
        
        .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; padding: 25px; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #2a2a2a; }
        th { color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        td { font-size: 14px; vertical-align: middle; }
        
        .badge-active { background: #064e3b; color: #34d399; padding: 4px 8px; border-radius: 12px; font-size: 12px; }
        .badge-inactive { background: #450a0a; color: #f87171; padding: 4px 8px; border-radius: 12px; font-size: 12px; }
        
        .btn { padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; color: #fff; }
        .btn-toggle { background: #374151; }
        .btn-toggle:hover { background: #4b5563; }
        .btn-update { background: #2563eb; }
        .btn-update:hover { background: #1d4ed8; }
        .btn-delete { background: #dc2626; }
        .btn-delete:hover { background: #b91c1c; }
        .btn-primary { background: #2563eb; padding: 10px 20px; font-weight: bold; }
        .btn-primary:hover { background: #1d4ed8; }
        
        input[type="number"], input[type="text"], select { 
            background: #0f0f0f; border: 1px solid #333; color: #fff; 
            padding: 8px 12px; border-radius: 4px; font-size: 14px; 
        }
        input[type="number"]:focus, input[type="text"]:focus, select:focus {
            outline: none; border-color: #2563eb;
        }
        
        .flex-form { display: flex; gap: 10px; align-items: center; }
        .add-form { display: flex; gap: 15px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; gap: 5px; flex-grow: 1; }
        .form-group label { font-size: 12px; color: #aaa; text-transform: uppercase; }
        
        .alert { padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #064e3b; color: #34d399; border: 1px solid #047857; }
        .alert-error { background: #450a0a; color: #f87171; border: 1px solid #991b1b; }
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
            <a href="index.php">Products</a> &rsaquo; <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> &rsaquo; Manage Sizes
        </div>
        
        <?php if ($msg = get_flash('success')): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($msg = get_flash('error')): ?>
            <div class="alert alert-error"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="header-actions">
            <h1>Manage Sizes: <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <a href="edit.php?id=<?= $product['id'] ?>" class="btn-back">Back to Edit Product</a>
        </div>

        <div class="card">
            <?php if (empty($sizes)): ?>
                <p style="color: #888; text-align: center; padding: 20px 0;">No sizes added yet for this product.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Size</th>
                            <th>Availability</th>
                            <th>Sort Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sizes as $index => $size): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($size['size'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td>
                                    <form method="POST" action="sizes.php?id=<?= $product['id'] ?>" class="flex-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_availability">
                                        <input type="hidden" name="size_id" value="<?= $size['id'] ?>">
                                        <span class="<?= $size['is_available'] ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= $size['is_available'] ? 'Available' : 'Unavailable' ?>
                                        </span>
                                        <button type="submit" class="btn btn-toggle" title="Toggle Availability">&#8644;</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="sizes.php?id=<?= $product['id'] ?>" class="flex-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update_sort">
                                        <input type="hidden" name="size_id" value="<?= $size['id'] ?>">
                                        <input type="number" name="sort_order" value="<?= htmlspecialchars($size['sort_order'], ENT_QUOTES, 'UTF-8') ?>" min="0" style="width: 60px;">
                                        <button type="submit" class="btn btn-update">Update</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="sizes.php?id=<?= $product['id'] ?>" onsubmit="return confirm('Delete this size?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_size">
                                        <input type="hidden" name="size_id" value="<?= $size['id'] ?>">
                                        <button type="submit" class="btn btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top: 0; margin-bottom: 12px;">Add New Size</h3>
            
            <!-- Standard EUR Footwear Quick-Add Presets -->
            <div style="margin-bottom: 20px; padding: 12px 16px; background: #111; border: 1px solid #222; border-radius: 6px;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #aaa; margin-bottom: 8px;">
                    Standard Footwear EUR Presets (Click to pre-fill):
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php 
                    $eurPresets = ['EUR 39', 'EUR 40', 'EUR 41', 'EUR 42', 'EUR 43', 'EUR 44', 'EUR 45', 'EUR 46'];
                    foreach ($eurPresets as $pSort => $eur): 
                    ?>
                        <button type="button" 
                                onclick="document.getElementById('size_label_input').value = '<?= $eur ?>'; document.getElementById('sort_order_input').value = '<?= $pSort + 1 ?>';"
                                style="background: #1e1e1e; border: 1px solid #333; color: #eee; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s;"
                                onmouseover="this.style.borderColor='#60a5fa'; this.style.color='#fff';"
                                onmouseout="this.style.borderColor='#333'; this.style.color='#eee';">
                            <?= $eur ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="POST" action="sizes.php?id=<?= $product['id'] ?>" class="add-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_size">
                
                <div class="form-group" style="flex-grow: 2;">
                    <label>Size Label</label>
                    <input type="text" id="size_label_input" name="size_label" required placeholder="e.g. EUR 43, M, 32W" maxlength="30">
                </div>
                
                <div class="form-group">
                    <label>Availability</label>
                    <select name="is_available">
                        <option value="1" selected>Available</option>
                        <option value="0">Unavailable</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" id="sort_order_input" name="sort_order" value="0" min="0">
                </div>
                
                <button type="submit" class="btn btn-primary">Add Size</button>
            </form>
        </div>
    </div>
</body>
</html>
