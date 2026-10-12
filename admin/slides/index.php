<?php
/**
 * ATHLETICA — Admin: Hero Slides Management
 * Lists all hero carousel slides with drag/sort order, media preview, and status controls.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

try {
    $stmt = $pdo->query("SELECT * FROM hero_slides ORDER BY sort_order ASC, id ASC");
    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $slides = [];
}

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hero Slides — ATHLETICA Admin</title>
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
        .brand-group { display: flex; align-items: center; gap: 24px; }
        .brand { font-size: 18px; font-weight: 700; letter-spacing: 3px; color: #fff; text-decoration: none; }
        .nav-links { display: flex; gap: 16px; }
        .nav-link { color: #888; text-decoration: none; font-size: 13px; font-weight: 500; }
        .nav-link:hover, .nav-link.active { color: #fff; }
        .admin-info { display: flex; align-items: center; gap: 16px; font-size: 13px; color: #999; }
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
        
        .badge-active { background: #064e3b; color: #34d399; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-inactive { background: #374151; color: #9ca3af; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-media { background: #1e3a8a; color: #93c5fd; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        
        .media-thumb { width: 80px; height: 48px; object-fit: cover; border-radius: 4px; background: #000; border: 1px solid #333; }
        
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
                <a href="../categories/index.php" class="nav-link">Shop Categories</a>
                <a href="../brands/index.php" class="nav-link">Brands</a>
                <a href="../products/index.php" class="nav-link">Products</a>
                <a href="index.php" class="nav-link active">Hero Slides</a>
                <a href="../flash-sales/index.php" class="nav-link">Flash Sales</a>
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
                <h1>Hero Slides CMS</h1>
                <p style="font-size: 13px; color: #888; margin-top: 4px;">Manage high-impact homepage carousel imagery, videos, headlines, and call-to-actions.</p>
            </div>
            <a href="create.php" class="btn-primary">+ Add New Slide</a>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if (empty($slides)): ?>
                <div style="padding: 48px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 12px;">No hero slides configured yet.</p>
                    <p style="font-size: 13px;">The storefront will seamlessly display the default showcase until you add slides.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">Order</th>
                            <th style="width: 150px;">Media (Desktop & Mobile)</th>
                            <th>Headline & Details</th>
                            <th>Call to Action</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slides as $slide): ?>
                            <tr>
                                <td style="font-weight: 700; color: #aaa;"><?= (int)$slide['sort_order'] ?></td>
                                <td>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <!-- Desktop Landscape Asset -->
                                        <div style="text-align: center;">
                                            <?php if ($slide['media_type'] === 'video'): ?>
                                                <div style="width: 76px; height: 46px; background: #111; border-radius: 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px solid #333;">
                                                    <span style="font-size: 14px;">▶</span>
                                                    <span class="badge-media">VIDEO</span>
                                                </div>
                                            <?php else: ?>
                                                <img src="../../<?= htmlspecialchars($slide['media_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Desktop Slide" class="media-thumb" style="width: 76px; height: 46px; object-fit: cover;" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'76\' height=\'46\' viewBox=\'0 0 76 46\'><rect fill=\'%23222\' width=\'76\' height=\'46\'/><text fill=\'%23666\' x=\'50%\' y=\'55%\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-size=\'10\'>IMAGE</text></svg>'">
                                            <?php endif; ?>
                                            <div style="font-size: 10px; color: #aaa; margin-top: 3px; font-weight: 600;">Desktop</div>
                                        </div>

                                        <!-- Mobile Portrait Asset -->
                                        <div style="text-align: center;">
                                            <?php 
                                            $mobileSrc = !empty($slide['mobile_image']) ? $slide['mobile_image'] : null;
                                            if ($mobileSrc): 
                                            ?>
                                                <img src="../../<?= htmlspecialchars($mobileSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Mobile Slide" style="width: 31px; height: 46px; object-fit: cover; border-radius: 4px; border: 1px solid #38bdf8;" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'31\' height=\'46\' viewBox=\'0 0 31 46\'><rect fill=\'%23222\' width=\'31\' height=\'46\'/><text fill=\'%23666\' x=\'50%\' y=\'55%\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-size=\'8\'>M</text></svg>'">
                                                <div style="font-size: 10px; color: #38bdf8; margin-top: 3px; font-weight: 600;">Mobile</div>
                                            <?php else: ?>
                                                <div style="width: 31px; height: 46px; border-radius: 4px; border: 1px dashed #444; background: #141414; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2px;" title="No dedicated mobile asset. Falls back to desktop creative.">
                                                    <span style="font-size: 8px; color: #777; text-align: center; line-height: 1;">Auto Fallback</span>
                                                </div>
                                                <div style="font-size: 10px; color: #777; margin-top: 3px;">Auto</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #fff; font-size: 14px;"><?= htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($slide['subtitle'])): ?>
                                        <div style="color: #888; font-size: 12px; margin-top: 2px; max-width: 400px;"><?= htmlspecialchars($slide['subtitle'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($slide['cta_text'])): ?>
                                        <span style="color: #60a5fa; font-weight: 600; font-size: 12px;"><?= htmlspecialchars($slide['cta_text'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <div style="color: #666; font-size: 11px;"><?= htmlspecialchars($slide['cta_url'] ?? '#', ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php else: ?>
                                        <span style="color: #666; font-size: 12px;">No CTA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?= $slide['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= $slide['is_active'] ? 'Active' : 'Hidden' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="actions-cell" style="justify-content: flex-end;">
                                        <a href="edit.php?id=<?= $slide['id'] ?>" class="btn-sm btn-edit">Edit</a>
                                        <form method="POST" action="toggle-status.php" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="slide_id" value="<?= $slide['id'] ?>">
                                            <button type="submit" class="btn-sm btn-toggle"><?= $slide['is_active'] ? 'Hide' : 'Show' ?></button>
                                        </form>
                                        <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Permanently delete this slide?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="slide_id" value="<?= $slide['id'] ?>">
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
