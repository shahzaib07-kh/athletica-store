<?php
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// Fetch categories for filter dropdown
$stmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order");
$categories = $stmt->fetchAll();

// Fetch brands for filter dropdown
$stmt = $pdo->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
$brands = $stmt->fetchAll();

// Handle filters
$whereClauses = [];
$params = [];

$categoryId = $_GET['category'] ?? '';
if ($categoryId !== '') {
    $whereClauses[] = "p.category_id = :category_id";
    $params['category_id'] = $categoryId;
}

$brandId = $_GET['brand'] ?? '';
if ($brandId !== '') {
    $whereClauses[] = "p.brand_id = :brand_id";
    $params['brand_id'] = $brandId;
}

$status = $_GET['status'] ?? '';
if (in_array($status, ['active', 'inactive', 'out_of_stock'])) {
    $whereClauses[] = "p.status = :status";
    $params['status'] = $status;
}

$search = $_GET['search'] ?? '';
if ($search !== '') {
    $whereClauses[] = "p.name LIKE :search";
    $params['search'] = '%' . $search . '%';
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(' AND ', $whereClauses);
}

// Main query
$sql = "SELECT p.*, 
       c.name AS category_name,
       b.name AS brand_name,
       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
       (SELECT GROUP_CONCAT(CONCAT(ps.size, IF(ps.is_available, '', ' ✗')) ORDER BY ps.sort_order SEPARATOR ', ') 
        FROM product_sizes ps WHERE ps.product_id = p.id) AS sizes_display
FROM products p
JOIN categories c ON c.id = p.category_id
LEFT JOIN brands b ON b.id = p.brand_id
$whereSql
ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$totalCount = count($products);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - ATHLETICA Admin</title>
    <style>
        :root {
            --bg-color: #0f0f0f;
            --card-bg: #1a1a1a;
            --border-color: #2a2a2a;
            --text-main: #f0f0f0;
            --text-muted: #a0a0a0;
            --accent: #4a90e2;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }
        .topbar {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .brand-group {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        .brand {
            color: var(--text-main);
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            letter-spacing: 2px;
        }
        .nav-links {
            display: flex;
            gap: 1.5rem;
        }
        .nav-link {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .nav-link:hover, .nav-link.active {
            color: var(--text-main);
        }
        .nav-link.active {
            font-weight: 600;
        }
        .admin-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .btn-logout {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-logout:hover {
            color: var(--danger);
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .btn {
            background-color: var(--accent);
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .filter-bar {
            background-color: var(--card-bg);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-bar input, .filter-bar select {
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 0.5rem;
            border-radius: 4px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .table th, .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .table th {
            font-weight: 600;
            color: var(--text-muted);
            background-color: rgba(255,255,255,0.02);
        }
        .table td {
            vertical-align: middle;
        }
        .table tr:last-child td {
            border-bottom: none;
        }
        .table a {
            color: var(--accent);
            text-decoration: none;
        }
        .table a:hover {
            text-decoration: underline;
        }
        .thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            background-color: var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-active { background-color: rgba(40, 167, 69, 0.2); color: var(--success); }
        .badge-inactive { background-color: rgba(160, 160, 160, 0.2); color: var(--text-muted); }
        .badge-out_of_stock { background-color: rgba(220, 53, 69, 0.2); color: var(--danger); }
        .actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .btn-action {
            background: none;
            border: none;
            color: var(--accent);
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
        }
        .btn-action:hover {
            color: #fff;
        }
        .flash {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        .flash-success {
            background-color: rgba(40, 167, 69, 0.2);
            color: var(--success);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        .flash-error {
            background-color: rgba(220, 53, 69, 0.2);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            background-color: var(--card-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
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
                <a href="../orders/index.php" class="nav-link">Orders</a>
            </nav>
        </div>
        <div class="admin-info">
            <span><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </header>

    <div class="container">
        <?php if ($flash = get_flash('success')): ?>
            <div class="flash flash-success"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flash = get_flash('error')): ?>
            <div class="flash flash-error"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="page-header">
            <div>
                <h1 style="margin: 0; font-size: 1.8rem;">Products</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--text-muted);">Total: <?= $totalCount ?> product(s)</p>
            </div>
            <a href="create.php" class="btn">Add Product</a>
        </div>

        <form method="GET" class="filter-bar">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="brand">
                <option value="">All Brands</option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $brandId == $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value="">All Statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="out_of_stock" <?= $status === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
            </select>

            <input type="text" name="search" placeholder="Search by name..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            
            <button type="submit" class="btn" style="background-color: var(--border-color); color: var(--text-main);">Filter</button>
            <?php if (!empty($_GET)): ?>
                <a href="index.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">Clear</a>
            <?php endif; ?>
        </form>

        <?php if ($totalCount > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th width="70">Image</th>
                        <th>Name</th>
                        <th>Brand & Category</th>
                        <th>Price</th>
                        <th>Sizes</th>
                        <th>Status</th>
                        <th>Featured</th>
                        <th width="200">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <?php if ($product['primary_image']): ?>
                                    <img src="../../<?= htmlspecialchars($product['primary_image'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="thumbnail">
                                <?php else: ?>
                                    <div class="thumbnail">No Img</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>
                                    <a href="edit.php?id=<?= $product['id'] ?>" style="font-weight: 600;">
                                        <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                    <?php if ($product['product_type'] === 'unique'): ?>
                                        <span style="background: #2d1a0f; color: #fb923c; padding: 1px 5px; border-radius: 3px;">1-Pair Unique</span>
                                    <?php else: ?>
                                        <span style="background: #112; color: #93c5fd; padding: 1px 5px; border-radius: 3px;">Multi-Size</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($product['brand_name'])): ?>
                                    <strong style="color: #fff;"><?= htmlspecialchars($product['brand_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                <?php endif; ?>
                                <span style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>Rs. <?= number_format($product['price'], 2) ?></td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?= htmlspecialchars($product['sizes_display'] ?? 'None', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge badge-<?= htmlspecialchars($product['status'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= ucfirst(str_replace('_', ' ', htmlspecialchars($product['status'], ENT_QUOTES, 'UTF-8'))) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 4px; align-items: center;">
                                    <?php if ($product['is_featured']): ?>
                                        <span title="Featured (Sort Order: <?= $product['featured_sort_order'] !== null ? (int)$product['featured_sort_order'] : 'Not set' ?>)" style="color: var(--warning); font-size: 1.1rem; display: inline-flex; align-items: center; gap: 3px;">
                                            ★<?php if ($product['featured_sort_order'] !== null): ?><span style="font-size: 10px; font-weight: 700; color: #fff; background: #262626; border: 1px solid #444; padding: 0 4px; border-radius: 3px;">#<?= (int)$product['featured_sort_order'] ?></span><?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($product['is_trending'])): ?>
                                        <span title="Trending / New Arrival" style="background: #e11d48; color: #fff; font-size: 10px; font-weight: 800; padding: 1px 4px; border-radius: 3px;">NEW</span>
                                    <?php endif; ?>
                                    <?php if (!empty($product['is_bestseller'])): ?>
                                        <span title="Best Seller" style="background: #f59e0b; color: #000; font-size: 10px; font-weight: 800; padding: 1px 4px; border-radius: 3px;">HOT</span>
                                    <?php endif; ?>
                                    <?php if (!$product['is_featured'] && empty($product['is_trending']) && empty($product['is_bestseller'])): ?>
                                        <span style="color: #444;">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="edit.php?id=<?= $product['id'] ?>">Edit</a> | 
                                    <a href="sizes.php?id=<?= $product['id'] ?>">Sizes</a> | 
                                    <a href="images.php?id=<?= $product['id'] ?>">Images</a> | 
                                    <form method="POST" action="toggle-featured.php" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                        <button type="submit" class="btn-action" title="Toggle Featured">★</button>
                                    </form> | 
                                    <a href="delete.php?id=<?= $product['id'] ?>" style="color: var(--danger);">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <h3>No products found</h3>
                <p>Try adjusting your filters or <a href="create.php" style="color: var(--accent);">add a new product</a>.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
