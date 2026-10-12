<?php
/**
 * ATHLETICA — Admin: Flash Sales Management
 * Lists all promotional flash sale campaigns with schedules, live status badges, and product counts.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

try {
    $sql = "
        SELECT fs.*,
               COUNT(fsp.id) AS product_count,
               CASE
                   WHEN fs.is_active = 0 THEN 'DISABLED'
                   WHEN NOW() < fs.starts_at THEN 'UPCOMING'
                   WHEN NOW() BETWEEN fs.starts_at AND fs.ends_at THEN 'LIVE NOW'
                   ELSE 'EXPIRED'
               END AS live_status
        FROM flash_sales fs
        LEFT JOIN flash_sale_products fsp ON fsp.flash_sale_id = fs.id
        GROUP BY fs.id
        ORDER BY fs.starts_at DESC
    ";
    $stmt = $pdo->query($sql);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sales = [];
}

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flash Sales — ATHLETICA Admin</title>
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
        
        .container { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        h1 { font-size: 22px; font-weight: 600; color: #fff; }
        .btn-primary { background: #fff; color: #000; padding: 10px 20px; border-radius: 4px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary:hover { background: #e5e5e5; }
        
        .alert { padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; }
        .alert-success { background: #064e3b; color: #6ee7b7; border: 1px solid #059669; }
        .alert-error { background: #450a0a; color: #fca5a5; border: 1px solid #dc2626; }
        
        .card { background: #161616; border: 1px solid #262626; border-radius: 8px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #1f1f1f; padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; border-bottom: 1px solid #262626; }
        td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #222; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #1a1a1a; }
        
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
        .badge-live { background: #064e3b; color: #34d399; border: 1px solid #059669; animation: pulse 2s infinite; }
        .badge-upcoming { background: #78350f; color: #fde047; border: 1px solid #b45309; }
        .badge-expired { background: #262626; color: #888; border: 1px solid #3a3a3a; }
        .badge-disabled { background: #450a0a; color: #fca5a5; border: 1px solid #b91c1c; }
        
        .actions-cell { display: flex; gap: 8px; align-items: center; }
        .btn-sm { padding: 4px 10px; font-size: 11px; border-radius: 4px; text-decoration: none; font-weight: 600; cursor: pointer; }
        .btn-edit { background: #262626; color: #fff; border: 1px solid #3a3a3a; }
        .btn-edit:hover { background: #333; }
        .btn-toggle { background: transparent; color: #60a5fa; border: 1px solid #1d4ed8; }
        .btn-toggle:hover { background: #1e3a8a; }
        .btn-del { background: transparent; color: #f87171; border: 1px solid #991b1b; }
        .btn-del:hover { background: #450a0a; }
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
                <a href="../products/index.php" class="nav-link">Products</a>
                <a href="../slides/index.php" class="nav-link">Hero Slides</a>
                <a href="index.php" class="nav-link active">Flash Sales</a>
                <a href="../orders/index.php" class="nav-link">Orders</a>
            </nav>
        </div>
        <div class="admin-info">
            <span><?= htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </header>

    <div class="container">
        <div class="header-actions">
            <div>
                <h1>Flash Sales Promotions</h1>
                <p style="font-size: 13px; color: #888; margin-top: 4px;">Configure limited-time flash discount events with authoritative server-side pricing and live homepage countdowns.</p>
            </div>
            <a href="create.php" class="btn-primary">+ Create Flash Sale</a>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if (empty($sales)): ?>
                <div style="padding: 48px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 12px;">No flash sale campaigns created yet.</p>
                    <p style="font-size: 13px;">Create a campaign to showcase time-limited deals on the homepage with an automated countdown.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Campaign Title</th>
                            <th>Schedule (Start & End)</th>
                            <th>Products Enrolled</th>
                            <th>Master Switch</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $s): ?>
                            <tr>
                                <td>
                                    <?php if ($s['live_status'] === 'LIVE NOW'): ?>
                                        <span class="badge badge-live">● LIVE NOW</span>
                                    <?php elseif ($s['live_status'] === 'UPCOMING'): ?>
                                        <span class="badge badge-upcoming">UPCOMING</span>
                                    <?php elseif ($s['live_status'] === 'DISABLED'): ?>
                                        <span class="badge badge-disabled">DISABLED</span>
                                    <?php else: ?>
                                        <span class="badge badge-expired">EXPIRED</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #fff; font-size: 14px;"><?= htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 12px; color: #ddd;">
                                        <?= date('M d, Y H:i', strtotime($s['starts_at'])) ?> &rarr; <?= date('M d, Y H:i', strtotime($s['ends_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: #60a5fa;"><?= (int)$s['product_count'] ?> products</span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: <?= $s['is_active'] ? '#34d399' : '#888' ?>;">
                                        <?= $s['is_active'] ? 'Enabled' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="actions-cell" style="justify-content: flex-end;">
                                        <a href="edit.php?id=<?= $s['id'] ?>" class="btn-sm btn-edit">Edit</a>
                                        <form method="POST" action="toggle-status.php" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sale_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn-sm btn-toggle"><?= $s['is_active'] ? 'Disable' : 'Enable' ?></button>
                                        </form>
                                        <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Permanently delete this flash sale?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sale_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn-sm btn-del">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
