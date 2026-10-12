<?php
/**
 * ATHLETICA — Admin: Create Flash Sale
 * Configure schedule and enroll active products with discounted sale prices.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// Fetch all active products
$prodStmt = $pdo->query("SELECT id, name, price, sku FROM products WHERE status = 'active' ORDER BY name ASC");
$allProducts = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$formData = [
    'title'     => '',
    'starts_at' => date('Y-m-d\TH:i', time()),
    'ends_at'   => date('Y-m-d\TH:i', time() + 86400 * 3), // +3 days
    'is_active' => 1,
    'products'  => [], // [product_id => sale_price]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid or expired security token. Please try again.';
    }

    $formData['title']     = trim($_POST['title'] ?? '');
    $formData['starts_at'] = trim($_POST['starts_at'] ?? '');
    $formData['ends_at']   = trim($_POST['ends_at'] ?? '');
    $formData['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    
    $selectedProductIds = $_POST['selected_products'] ?? [];
    $salePrices         = $_POST['sale_prices'] ?? [];

    if (empty($formData['title'])) {
        $errors[] = 'Campaign title is required.';
    }
    if (empty($formData['starts_at']) || empty($formData['ends_at'])) {
        $errors[] = 'Both start and end dates/times are required.';
    } elseif (strtotime($formData['ends_at']) <= strtotime($formData['starts_at'])) {
        $errors[] = 'End date/time must be strictly later than start date/time.';
    }

    if (empty($selectedProductIds)) {
        $errors[] = 'Please select at least one product to enroll in this flash sale.';
    }

    // Validate sale prices
    $validEnrolled = [];
    foreach ($selectedProductIds as $pId) {
        $pId = (int)$pId;
        $sPrice = (float)($salePrices[$pId] ?? 0);
        
        // Lookup original price
        $origPrice = 0;
        foreach ($allProducts as $ap) {
            if ((int)$ap['id'] === $pId) {
                $origPrice = (float)$ap['price'];
                break;
            }
        }

        if ($sPrice <= 0) {
            $errors[] = "Please provide a valid sale price (> 0) for all selected products.";
            break;
        } elseif ($sPrice >= $origPrice) {
            $errors[] = "Sale price (Rs. {$sPrice}) must be strictly less than the regular price (Rs. {$origPrice}).";
            break;
        }

        $validEnrolled[] = [
            'product_id' => $pId,
            'sale_price' => $sPrice,
        ];
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO flash_sales (title, starts_at, ends_at, is_active)
                VALUES (:title, :starts_at, :ends_at, :is_active)
            ");
            $stmt->execute([
                ':title'     => $formData['title'],
                ':starts_at' => $formData['starts_at'],
                ':ends_at'   => $formData['ends_at'],
                ':is_active' => $formData['is_active'],
            ]);
            $flashSaleId = (int)$pdo->lastInsertId();

            $fspStmt = $pdo->prepare("
                INSERT INTO flash_sale_products (flash_sale_id, product_id, sale_price, sort_order)
                VALUES (:fs_id, :p_id, :s_price, :sort)
            ");
            foreach ($validEnrolled as $idx => $item) {
                $fspStmt->execute([
                    ':fs_id'   => $flashSaleId,
                    ':p_id'    => $item['product_id'],
                    ':s_price' => $item['sale_price'],
                    ':sort'    => $idx,
                ]);
            }

            $pdo->commit();
            set_flash('success', 'Flash sale created and scheduled successfully.');
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Flash Sale — ATHLETICA Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f0f; color: #e0e0e0; min-height: 100vh; }
        .topbar { background: #1a1a1a; border-bottom: 1px solid #2a2a2a; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        .brand-group { display: flex; align-items: center; gap: 24px; }
        .brand { font-size: 18px; font-weight: 700; letter-spacing: 3px; color: #fff; text-decoration: none; }
        .nav-links { display: flex; gap: 16px; }
        .nav-link { color: #888; text-decoration: none; font-size: 13px; font-weight: 500; }
        .nav-link:hover, .nav-link.active { color: #fff; }
        .btn-logout { padding: 6px 14px; background: transparent; border: 1px solid #333; border-radius: 4px; color: #ccc; font-size: 12px; text-decoration: none; }
        
        .container { max-width: 900px; margin: 0 auto; padding: 32px 24px; }
        .card { background: #161616; border: 1px solid #262626; border-radius: 8px; padding: 28px; }
        h1 { font-size: 20px; font-weight: 600; color: #fff; margin-bottom: 20px; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #aaa; margin-bottom: 8px; }
        input[type="text"], input[type="datetime-local"], input[type="number"] {
            width: 100%; padding: 10px 14px; background: #101010; border: 1px solid #303030; border-radius: 4px; color: #fff; font-size: 14px;
        }
        input:focus { border-color: #60a5fa; outline: none; }
        
        .product-select-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .product-select-table th { background: #202020; padding: 10px 12px; font-size: 11px; text-transform: uppercase; color: #aaa; text-align: left; }
        .product-select-table td { padding: 10px 12px; border-bottom: 1px solid #242424; font-size: 13px; vertical-align: middle; }
        
        .alert-error { background: #450a0a; color: #fca5a5; border: 1px solid #dc2626; padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; }
        
        .btn-actions { display: flex; gap: 12px; margin-top: 28px; }
        .btn-submit { background: #fff; color: #000; padding: 10px 24px; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-cancel { background: transparent; color: #aaa; border: 1px solid #333; padding: 10px 20px; border-radius: 4px; font-size: 13px; text-decoration: none; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand-group">
            <a href="../dashboard.php" class="brand">ATHLETICA</a>
            <nav class="nav-links">
                <a href="../dashboard.php" class="nav-link">Dashboard</a>
                <a href="../slides/index.php" class="nav-link">Hero Slides</a>
                <a href="index.php" class="nav-link active">Flash Sales</a>
            </nav>
        </div>
        <div><a href="../logout.php" class="btn-logout">Logout</a></div>
    </header>

    <div class="container">
        <div class="card">
            <h1>Create Flash Sale Campaign</h1>

            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <?php foreach ($errors as $err): ?>
                        <div>• <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="create.php">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label>Campaign Title *</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($formData['title'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. WEEKEND SPEED DROP — 20% OFF">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Starts At *</label>
                        <input type="datetime-local" name="starts_at" required value="<?= htmlspecialchars($formData['starts_at'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label>Ends At *</label>
                        <input type="datetime-local" name="ends_at" required value="<?= htmlspecialchars($formData['ends_at'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" <?= $formData['is_active'] ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <label for="is_active" style="margin-bottom: 0; cursor: pointer;">Enable Campaign (Active when time window arrives)</label>
                </div>

                <div class="form-group" style="margin-top: 28px;">
                    <label style="color: #fff; font-size: 14px;">Select Products & Set Flash Sale Prices *</label>
                    <p style="font-size: 12px; color: #888; margin-bottom: 12px;">Check products to enroll and define their special promotional sale price during the campaign.</p>

                    <table class="product-select-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">Select</th>
                                <th>Product Name</th>
                                <th style="width: 120px;">Regular Price</th>
                                <th style="width: 160px;">Sale Price (Rs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allProducts as $p): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_products[]" value="<?= $p['id'] ?>" id="p_<?= $p['id'] ?>" style="width: 16px; height: 16px;">
                                    </td>
                                    <td>
                                        <label for="p_<?= $p['id'] ?>" style="margin-bottom: 0; font-weight: 500; cursor: pointer; color: #eee;">
                                            <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
                                            <span style="color: #666; font-size: 11px;">(<?= htmlspecialchars($p['sku'] ?? 'No SKU', ENT_QUOTES, 'UTF-8') ?>)</span>
                                        </label>
                                    </td>
                                    <td style="color: #bbb; font-weight: 600;">
                                        Rs. <?= number_format((float)$p['price'], 2) ?>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="1" name="sale_prices[<?= $p['id'] ?>]" placeholder="e.g. <?= (float)$p['price'] * 0.8 ?>" style="padding: 6px 10px; font-size: 13px;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="btn-actions">
                    <button type="submit" class="btn-submit">Publish Flash Sale</button>
                    <a href="index.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
