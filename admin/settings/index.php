<?php
/**
 * ATHLETICA — Admin: Site Settings Management
 * Centralized settings manager for Business Information, Hours, Social Links, and Footer.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Security token expired. Please try again.');
        header('Location: index.php');
        exit;
    }

    $siteName     = trim($_POST['site_name'] ?? 'ATHLETICA');
    $tagline      = trim($_POST['tagline'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $googleMapUrl = trim($_POST['google_map_url'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $whatsapp     = trim($_POST['whatsapp_number'] ?? '');
    $footerText   = trim($_POST['footer_text'] ?? '');

    $facebookUrl  = trim($_POST['facebook_url'] ?? '');
    $instagramUrl = trim($_POST['instagram_url'] ?? '');
    $tiktokUrl    = trim($_POST['tiktok_url'] ?? '');
    $youtubeUrl   = trim($_POST['youtube_url'] ?? '');

    // Business Hours array
    $hours = [
        'monday'    => trim($_POST['hours_monday'] ?? '10:00 AM - 10:00 PM'),
        'tuesday'   => trim($_POST['hours_tuesday'] ?? '10:00 AM - 10:00 PM'),
        'wednesday' => trim($_POST['hours_wednesday'] ?? '10:00 AM - 10:00 PM'),
        'thursday'  => trim($_POST['hours_thursday'] ?? '10:00 AM - 10:00 PM'),
        'friday'    => trim($_POST['hours_friday'] ?? '02:00 PM - 10:00 PM'),
        'saturday'  => trim($_POST['hours_saturday'] ?? '10:00 AM - 10:00 PM'),
        'sunday'    => trim($_POST['hours_sunday'] ?? 'Closed'),
    ];
    $hoursJson = json_encode($hours, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    try {
        // Handle desktop_image upload
        $curSettings = $pdo->query("SELECT desktop_image FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
        $desktopImage = $curSettings['desktop_image'] ?? null;

        // Fetch primary active slide ID for deterministic slide synchronization
        $primarySlide = $pdo->query("SELECT id, media_path, mobile_image FROM hero_slides WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $primarySlideId = $primarySlide ? (int)$primarySlide['id'] : null;

        if (!empty($_FILES['desktop_image']['name']) && $_FILES['desktop_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upDesktop = upload_hero_media($_FILES['desktop_image']);
            if ($upDesktop['success']) {
                $desktopImage = $upDesktop['path'];
                if ($primarySlideId) {
                    $pdo->prepare("UPDATE hero_slides SET media_path = :media_path WHERE id = :id")->execute([
                        ':media_path' => $desktopImage,
                        ':id'         => $primarySlideId,
                    ]);
                }
            }
        }

        if (!empty($_FILES['mobile_image']['name']) && $_FILES['mobile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upMobile = upload_hero_media($_FILES['mobile_image']);
            if ($upMobile['success']) {
                $mobileImage = $upMobile['path'];
                if ($primarySlideId) {
                    $pdo->prepare("UPDATE hero_slides SET mobile_image = :mobile_image WHERE id = :id")->execute([
                        ':mobile_image' => $mobileImage,
                        ':id'           => $primarySlideId,
                    ]);
                }
            }
        }

        $stmt = $pdo->prepare("
            UPDATE site_settings SET
                site_name         = :site_name,
                tagline           = :tagline,
                desktop_image     = :desktop_image,
                address           = :address,
                google_map_url    = :google_map_url,
                phone             = :phone,
                email             = :email,
                whatsapp_number   = :whatsapp_number,
                facebook_url      = :facebook_url,
                instagram_url     = :instagram_url,
                tiktok_url        = :tiktok_url,
                youtube_url       = :youtube_url,
                footer_text       = :footer_text,
                business_hours    = :business_hours,
                updated_at        = NOW()
            WHERE id = 1
        ");

        $stmt->execute([
            ':site_name'         => $siteName,
            ':tagline'           => $tagline ?: null,
            ':desktop_image'     => $desktopImage,
            ':address'           => $address ?: null,
            ':google_map_url'    => $googleMapUrl ?: null,
            ':phone'             => $phone ?: null,
            ':email'             => $email ?: null,
            ':whatsapp_number'   => $whatsapp ?: null,
            ':facebook_url'      => $facebookUrl ?: null,
            ':instagram_url'     => $instagramUrl ?: null,
            ':tiktok_url'        => $tiktokUrl ?: null,
            ':youtube_url'       => $youtubeUrl ?: null,
            ':footer_text'       => $footerText ?: null,
            ':business_hours'    => $hoursJson,
        ]);

        set_flash('success', 'Site settings saved successfully.');
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        set_flash('error', 'Database error: ' . $e->getMessage());
        header('Location: index.php');
        exit;
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT * FROM site_settings WHERE id = 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $settings = [
        'site_name' => 'ATHLETICA',
        'tagline' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'whatsapp_number' => '',
        'facebook_url' => '',
        'instagram_url' => '',
        'tiktok_url' => '',
        'youtube_url' => '',
        'footer_text' => '© ' . date('Y') . ' ATHLETICA. All rights reserved.',
        'business_hours' => null,
    ];
}

$businessHours = [];
if (!empty($settings['business_hours'])) {
    $decoded = json_decode((string)$settings['business_hours'], true);
    if (is_array($decoded)) {
        $businessHours = $decoded;
    }
}
$days = [
    'monday'    => 'Monday',
    'tuesday'   => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday'  => 'Thursday',
    'friday'    => 'Friday',
    'saturday'  => 'Saturday',
    'sunday'    => 'Sunday',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings — ATHLETICA Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f0f; color: #e0e0e0; min-height: 100vh; }
        .topbar { background: #1a1a1a; border-bottom: 1px solid #2a2a2a; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        .brand-group { display: flex; align-items: center; gap: 24px; }
        .brand { font-size: 18px; font-weight: 700; letter-spacing: 3px; color: #fff; text-decoration: none; }
        .nav-links { display: flex; gap: 16px; flex-wrap: wrap; }
        .nav-link { color: #888; text-decoration: none; font-size: 13px; font-weight: 500; }
        .nav-link:hover, .nav-link.active { color: #fff; }
        .btn-logout { padding: 6px 14px; background: transparent; border: 1px solid #333; border-radius: 4px; color: #ccc; font-size: 12px; text-decoration: none; }

        .container { max-width: 900px; margin: 0 auto; padding: 32px 24px; }
        h1 { font-size: 22px; font-weight: 600; color: #fff; margin-bottom: 4px; }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 24px; }

        .alert { padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 24px; }
        .alert-success { background: #064e3b; color: #6ee7b7; border: 1px solid #059669; }
        .alert-error { background: #7f1d1d; color: #fca5a5; border: 1px solid #dc2626; }

        .settings-card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
        .settings-card h2 { font-size: 14px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #3b82f6; margin-bottom: 16px; border-bottom: 1px solid #262626; padding-bottom: 8px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-full { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        label { font-size: 12px; font-weight: 600; color: #aaa; text-transform: uppercase; letter-spacing: 0.5px; }
        .hint { font-size: 11px; color: #666; font-weight: normal; text-transform: none; }
        input[type="text"], input[type="email"], textarea {
            background: #111; border: 1px solid #333; border-radius: 4px; padding: 10px 12px; color: #fff; font-size: 13px; outline: none; transition: border-color 0.2s;
        }
        input[type="text"]:focus, input[type="email"]:focus, textarea:focus { border-color: #3b82f6; }
        textarea { resize: vertical; min-height: 70px; font-family: inherit; }

        .hours-row { display: grid; grid-template-columns: 120px 1fr; align-items: center; gap: 12px; margin-bottom: 8px; }
        .hours-day { font-size: 13px; font-weight: 600; color: #ccc; }

        .actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
        .btn-save { background: #fff; color: #000; border: none; padding: 12px 28px; border-radius: 6px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; transition: background 0.2s; }
        .btn-save:hover { background: #e5e5e5; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand-group">
            <a href="../dashboard.php" class="brand">ATHLETICA</a>
            <nav class="nav-links">
                <a href="../dashboard.php" class="nav-link">Dashboard</a>
                <a href="../categories/index.php" class="nav-link">Shop Categories</a>
                <a href="../brands/index.php" class="nav-link">Brands</a>
                <a href="../products/index.php" class="nav-link">Products</a>
                <a href="../slides/index.php" class="nav-link">Hero Slides</a>
                <a href="../flash-sales/index.php" class="nav-link">Flash Sales</a>
                <a href="../orders/index.php" class="nav-link">Orders</a>
                <a href="index.php" class="nav-link active">Site Settings</a>
            </nav>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <span style="font-size: 13px; color: #999;"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>Centralized Site Settings</h1>
        <p class="subtitle">Manage store identity, business address, contact information, hours, social links, and footer.</p>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- 1. BUSINESS & HERO INFORMATION -->
            <div class="settings-card">
                <h2>1. Business & Hero Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="site_name">Store Name</label>
                        <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? 'ATHLETICA', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="tagline">Hero Headline / Tagline</label>
                        <input type="text" id="tagline" name="tagline" value="<?= htmlspecialchars($settings['tagline'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. FOCUS / POWER / PERFORMANCE">
                        <span class="hint">Updates immediately reflect dynamically on the storefront Hero Slider.</span>
                    </div>

                    <!-- HERO IMAGES -->
                    <?php
                    $primarySlide = $pdo->query("SELECT media_path, mobile_image FROM hero_slides WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
                    $previewDesktop = !empty($settings['desktop_image']) ? $settings['desktop_image'] : ($primarySlide['media_path'] ?? null);
                    $previewMobile  = $primarySlide['mobile_image'] ?? null;
                    ?>
                    <div class="form-group">
                        <label for="desktop_image">Desktop Hero Image (Landscape)</label>
                        <?php if (!empty($previewDesktop)): ?>
                            <div style="margin-bottom: 6px;">
                                <img src="../../<?= htmlspecialchars($previewDesktop, ENT_QUOTES, 'UTF-8') ?>" alt="Desktop Hero" style="max-height: 60px; border-radius: 4px; border: 1px solid #333;">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="desktop_image" name="desktop_image" accept="image/jpeg,image/png,image/webp">
                        <span class="hint">Recommended: 1920×900 landscape.</span>
                    </div>

                    <div class="form-group">
                        <label for="mobile_image">Mobile Hero Image (Portrait)</label>
                        <?php if (!empty($previewMobile)): ?>
                            <div style="margin-bottom: 6px;">
                                <img src="../../<?= htmlspecialchars($previewMobile, ENT_QUOTES, 'UTF-8') ?>" alt="Mobile Hero" style="max-height: 60px; border-radius: 4px; border: 1px solid #333;">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="mobile_image" name="mobile_image" accept="image/jpeg,image/png,image/webp">
                        <span class="hint">Recommended: 750×1100 portrait.</span>
                    </div>
                    <div class="form-group form-full">
                        <label for="address">Store Address <span class="hint">(Displayed on Contact page & Footer)</span></label>
                        <textarea id="address" name="address" rows="2" placeholder="e.g. Shop 12, Commercial Market, Lahore, Pakistan"><?= htmlspecialchars($settings['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="form-group form-full">
                        <label for="google_map_url">Google Maps Embed URL <span class="hint">Paste the "src" URL from Google Maps (Share &gt; Embed a map).</span></label>
                        <input type="text" id="google_map_url" name="google_map_url" value="<?= htmlspecialchars($settings['google_map_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://www.google.com/maps/embed?pb=...">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="hint">(e.g. +92 300 1234567)</span></label>
                        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($settings['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Support Email <span class="hint">(e.g. contact@athletica.pk)</span></label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($settings['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group form-full">
                        <label for="whatsapp_number">WhatsApp Number <span class="hint">(Used for Floating button, Footer link, and Order Inquiries — Leave blank to disable)</span></label>
                        <input type="text" id="whatsapp_number" name="whatsapp_number" value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="+923001234567">
                    </div>
                </div>
            </div>

            <!-- 2. BUSINESS HOURS -->
            <div class="settings-card">
                <h2>2. Business Hours</h2>
                <p style="font-size: 12px; color: #888; margin-bottom: 16px;">Specify hours (e.g. "10:00 AM - 10:00 PM") or type "Closed" if not operating.</p>
                <?php foreach ($days as $key => $label): ?>
                    <div class="hours-row">
                        <span class="hours-day"><?= $label ?></span>
                        <input type="text" name="hours_<?= $key ?>" value="<?= htmlspecialchars($businessHours[$key] ?? ($key === 'sunday' ? 'Closed' : '10:00 AM - 10:00 PM'), ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. 10:00 AM - 10:00 PM or Closed">
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 3. SOCIAL LINKS -->
            <div class="settings-card">
                <h2>3. Social Links <span class="hint" style="color:#aaa;">(Only configured links will appear in the Footer)</span></h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="instagram_url">Instagram URL</label>
                        <input type="text" id="instagram_url" name="instagram_url" value="<?= htmlspecialchars($settings['instagram_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://instagram.com/athletica.pk">
                    </div>
                    <div class="form-group">
                        <label for="facebook_url">Facebook URL</label>
                        <input type="text" id="facebook_url" name="facebook_url" value="<?= htmlspecialchars($settings['facebook_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://facebook.com/athletica.pk">
                    </div>
                    <div class="form-group">
                        <label for="tiktok_url">TikTok URL</label>
                        <input type="text" id="tiktok_url" name="tiktok_url" value="<?= htmlspecialchars($settings['tiktok_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://tiktok.com/@athletica.pk">
                    </div>
                    <div class="form-group">
                        <label for="youtube_url">YouTube URL</label>
                        <input type="text" id="youtube_url" name="youtube_url" value="<?= htmlspecialchars($settings['youtube_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://youtube.com/@athletica.pk">
                    </div>
                </div>
            </div>

            <!-- 4. FOOTER -->
            <div class="settings-card">
                <h2>4. Footer Information</h2>
                <div class="form-group">
                    <label for="footer_text">Copyright / Bottom Bar Text</label>
                    <input type="text" id="footer_text" name="footer_text" value="<?= htmlspecialchars($settings['footer_text'] ?? ('© ' . date('Y') . ' ATHLETICA. All rights reserved.'), ENT_QUOTES, 'UTF-8') ?>" placeholder="© 2026 ATHLETICA. All rights reserved.">
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn-save">Save Settings</button>
            </div>
        </form>
    </div>
</body>
</html>
