<?php
/**
 * ATHLETICA — Admin Dashboard
 * Direct Hero Banner & Dynamic Tagline Management + Portal Overview
 */

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../config/database.php';

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');

// Handle Hero Configuration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_hero') {
    if (!csrf_verify()) {
        set_flash('error', 'Security token expired. Please try again.');
        header('Location: dashboard.php');
        exit;
    }

    $tagline = trim($_POST['tagline'] ?? '');

    // Fetch existing settings
    $curSettings = $pdo->query("SELECT * FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $desktopImage = $curSettings['desktop_image'] ?? null;

    // Fetch primary active slide ID for deterministic slide synchronization
    $primarySlide = $pdo->query("SELECT id, media_path, mobile_image FROM hero_slides WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $primarySlideId = $primarySlide ? (int)$primarySlide['id'] : null;

    // Process Desktop Image upload if provided
    if (!empty($_FILES['desktop_image']['name']) && $_FILES['desktop_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upDesktop = upload_hero_media($_FILES['desktop_image']);
        if ($upDesktop['success']) {
            $desktopImage = $upDesktop['path'];
            // Synchronize primary active slide media_path
            if ($primarySlideId) {
                $pdo->prepare("UPDATE hero_slides SET media_path = :media_path WHERE id = :id")->execute([
                    ':media_path' => $desktopImage,
                    ':id'         => $primarySlideId,
                ]);
            }
        } else {
            set_flash('error', 'Desktop Image: ' . $upDesktop['error']);
            header('Location: dashboard.php');
            exit;
        }
    }

    // Process Mobile Image upload if provided
    if (!empty($_FILES['mobile_image']['name']) && $_FILES['mobile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upMobile = upload_hero_media($_FILES['mobile_image']);
        if ($upMobile['success']) {
            $mobileImage = $upMobile['path'];
            // Synchronize primary active slide mobile_image (Source of Truth)
            if ($primarySlideId) {
                $pdo->prepare("UPDATE hero_slides SET mobile_image = :mobile_image WHERE id = :id")->execute([
                    ':mobile_image' => $mobileImage,
                    ':id'           => $primarySlideId,
                ]);
            }
        } else {
            set_flash('error', 'Mobile Image: ' . $upMobile['error']);
            header('Location: dashboard.php');
            exit;
        }
    }

    // Save tagline and desktop_image to site_settings (mobile_image is managed exclusively via hero_slides)
    $stmt = $pdo->prepare("
        UPDATE site_settings SET
            tagline = :tagline,
            desktop_image = :desktop_image,
            updated_at = NOW()
        WHERE id = 1
    ");
    $stmt->execute([
        ':tagline'       => $tagline ?: null,
        ':desktop_image' => $desktopImage,
    ]);

    set_flash('success', 'Hero Banner & Dynamic Tagline successfully saved! Changes reflect live on the storefront.');
    header('Location: dashboard.php');
    exit;
}

// Load current configuration
$settings = $pdo->query("SELECT * FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$activeSlide = $pdo->query("SELECT * FROM hero_slides WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

$currentDesktop = !empty($activeSlide['media_path']) ? $activeSlide['media_path'] : ($settings['desktop_image'] ?? null);
$currentMobile  = $activeSlide['mobile_image'] ?? null;
$currentTagline = $settings['tagline'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — ATHLETICA Admin</title>
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

        .topbar .brand {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 3px;
            color: #ffffff;
            text-decoration: none;
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

        .content {
            padding: 32px 24px;
            max-width: 900px;
            margin: 0 auto;
        }

        .content h2 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #fff;
        }

        .content p.lead {
            color: #888;
            font-size: 13px;
            margin-bottom: 24px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .alert-success { background: #064e3b; color: #6ee7b7; border: 1px solid #059669; }
        .alert-error { background: #7f1d1d; color: #fca5a5; border: 1px solid #dc2626; }

        .hero-card {
            background: #161616;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 32px;
        }

        .hero-card h3 {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #3b82f6;
            margin-bottom: 16px;
            border-bottom: 1px solid #262626;
            padding-bottom: 8px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #aaa;
            margin-bottom: 8px;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px 14px;
            background: #101010;
            border: 1px solid #303030;
            border-radius: 4px;
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus {
            border-color: #3b82f6;
        }

        .hint {
            font-size: 11px;
            color: #777;
            margin-top: 4px;
        }

        .upload-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 16px;
            margin-bottom: 20px;
        }

        .upload-box {
            background: #111;
            border: 1px solid #262626;
            border-radius: 6px;
            padding: 16px;
        }

        .preview-container {
            margin-bottom: 12px;
            background: #0a0a0a;
            border: 1px solid #202020;
            border-radius: 4px;
            padding: 8px;
            min-height: 80px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .preview-img {
            max-height: 80px;
            max-width: 120px;
            object-fit: cover;
            border-radius: 4px;
        }

        .badge-active {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .badge-desktop { background: #064e3b; color: #34d399; }
        .badge-mobile { background: #3b0764; color: #c084fc; }

        .btn-save {
            background: #fff;
            color: #000;
            border: none;
            padding: 12px 28px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover { background: #e5e5e5; }
        .btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

        .status-card {
            background: #161616;
            border: 1px solid #262626;
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
        }

        .status-card h3 {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 12px;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #222;
            font-size: 13px;
        }

        .status-row:last-child { border-bottom: none; }
        .status-row .label { color: #888; }
        .status-row .value { color: #e0e0e0; font-weight: 500; }

        .badge-ok {
            background: #0a1c0a;
            border: 1px solid #154a15;
            color: #2ecc71;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div style="display: flex; align-items: center; gap: 24px;">
            <a href="dashboard.php" class="brand">ATHLETICA</a>
            <nav style="display: flex; gap: 16px; flex-wrap: wrap;">
                <a href="dashboard.php" style="color: #fff; text-decoration: none; font-size: 13px; font-weight: 500;">Dashboard</a>
                <a href="categories/index.php" style="color: #888; text-decoration: none; font-size: 13px; font-weight: 500;">Categories</a>
                <a href="brands/index.php" style="color: #888; text-decoration: none; font-size: 13px; font-weight: 500;">Brands</a>
                <a href="products/index.php" style="color: #888; text-decoration: none; font-size: 13px; font-weight: 500;">Products</a>
                <a href="slides/index.php" style="color: #888; text-decoration: none; font-size: 13px; font-weight: 500;">Hero Slides</a>
                <a href="flash-sales/index.php" style="color: #888; text-decoration: none; font-size: 13px; font-weight: 500;">Flash Sales</a>
                <a href="orders/index.php" style="color: #888; text-decoration: none; font-size: 13px; font-weight: 500;">Orders</a>
                <a href="settings/index.php" style="color: #888; text-decoration: none; font-size: 13px; font-weight: 500;">Site Settings</a>
            </nav>
        </div>
        <div class="admin-info">
            <span><?= htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="content">
        <h2>ATHLETICA Control Panel</h2>
        <p class="lead">Welcome back. Manage hero imagery, dynamic headlines, and store operations below.</p>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <!-- HERO SECTION & DYNAMIC TAGLINE MANAGER -->
        <div class="hero-card">
            <h3>⚡ Hero Banner & Dynamic Tagline Management</h3>
            <form method="POST" action="dashboard.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_hero">

                <!-- 1. DYNAMIC TAGLINE FIELD -->
                <div class="form-group">
                    <label for="tagline">Hero Headline / Tagline</label>
                    <input type="text" id="tagline" name="tagline" value="<?= htmlspecialchars($currentTagline, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. FOCUS / POWER / PERFORMANCE">
                    <div class="hint">Dynamic headline displayed on the Hero Slider. Updates immediately reflect on the storefront in real-time.</div>
                </div>

                <!-- 2. DUAL IMAGE UPLOAD SYSTEM -->
                <div class="upload-grid">
                    <!-- FIELD 1: DESKTOP HERO IMAGE (LANDSCAPE) -->
                    <div class="upload-box">
                        <label>Desktop Hero Image (Landscape)</label>
                        <div class="preview-container">
                            <?php if ($currentDesktop): ?>
                                <img src="../<?= htmlspecialchars($currentDesktop, ENT_QUOTES, 'UTF-8') ?>" alt="Desktop Hero" class="preview-img">
                                <div>
                                    <span class="badge-active badge-desktop">Active Desktop</span>
                                    <div style="font-size: 10px; color: #777; margin-top: 4px; word-break: break-all;"><?= htmlspecialchars($currentDesktop, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 11px; color: #777;">No desktop image active.</div>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="desktopImageInput" name="desktop_image" accept="image/jpeg,image/png,image/webp">
                        <div class="hint">Recommended: 1920×900 landscape. JPG, PNG, WebP (max 10MB).</div>
                        <div id="desktopLivePreview" style="display: none; margin-top: 8px; font-size: 11px; color: #60a5fa;"></div>
                    </div>

                    <!-- FIELD 2: MOBILE HERO IMAGE (PORTRAIT) -->
                    <div class="upload-box">
                        <label>Mobile Hero Image (Portrait)</label>
                        <div class="preview-container">
                            <?php if ($currentMobile): ?>
                                <img src="../<?= htmlspecialchars($currentMobile, ENT_QUOTES, 'UTF-8') ?>" alt="Mobile Hero" class="preview-img">
                                <div>
                                    <span class="badge-active badge-mobile">Active Mobile</span>
                                    <div style="font-size: 10px; color: #777; margin-top: 4px; word-break: break-all;"><?= htmlspecialchars($currentMobile, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 11px; color: #777;">No mobile portrait image active (falls back to desktop).</div>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="mobileImageInput" name="mobile_image" accept="image/jpeg,image/png,image/webp">
                        <div class="hint">Recommended: 750×1100 or 9:16 portrait. JPG, PNG, WebP (max 10MB).</div>
                        <div id="mobileLivePreview" style="display: none; margin-top: 8px; font-size: 11px; color: #a78bfa;"></div>
                    </div>
                </div>

                <button type="submit" class="btn-save">
                    <span>Save Hero Configuration</span>
                </button>
            </form>
        </div>

        <!-- QUICK ACCESS MODULES -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
            <a href="orders/index.php" style="background: #161616; border: 1px solid #262626; border-radius: 8px; padding: 20px; text-decoration: none; color: inherit; display: block; transition: border-color 0.2s;">
                <div style="font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: #fbbf24; margin-bottom: 6px;">Customer Orders</div>
                <div style="font-size: 18px; font-weight: 600; color: #fff; margin-bottom: 6px;">Orders &rarr;</div>
                <div style="font-size: 13px; color: #777;">View, verify, and update nationwide Cash on Delivery shipments.</div>
            </a>
            <a href="slides/index.php" style="background: #161616; border: 1px solid #262626; border-radius: 8px; padding: 20px; text-decoration: none; color: inherit; display: block; transition: border-color 0.2s;">
                <div style="font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: #60a5fa; margin-bottom: 6px;">Homepage CMS</div>
                <div style="font-size: 18px; font-weight: 600; color: #fff; margin-bottom: 6px;">Hero Slides &rarr;</div>
                <div style="font-size: 13px; color: #777;">Manage multi-slide carousel, video backgrounds, and CTAs.</div>
            </a>
            <a href="products/index.php" style="background: #161616; border: 1px solid #262626; border-radius: 8px; padding: 20px; text-decoration: none; color: inherit; display: block; transition: border-color 0.2s;">
                <div style="font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: #34d399; margin-bottom: 6px;">Footwear & Apparel</div>
                <div style="font-size: 18px; font-weight: 600; color: #fff; margin-bottom: 6px;">Products &rarr;</div>
                <div style="font-size: 13px; color: #777;">Add, edit, and manage products, sizes, images, and inventory.</div>
            </a>
            <a href="settings/index.php" style="background: #161616; border: 1px solid #262626; border-radius: 8px; padding: 20px; text-decoration: none; color: inherit; display: block; transition: border-color 0.2s;">
                <div style="font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: #e2e8f0; margin-bottom: 6px;">Store Configuration</div>
                <div style="font-size: 18px; font-weight: 600; color: #fff; margin-bottom: 6px;">Site Settings &rarr;</div>
                <div style="font-size: 13px; color: #777;">Manage store details, hours, WhatsApp number, and policies.</div>
            </a>
        </div>

        <div class="status-card">
            <h3>System Session Info</h3>

            <div class="status-row">
                <span class="label">Logged in as</span>
                <span class="value"><?= htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="status-row">
                <span class="label">Email</span>
                <span class="value"><?= htmlspecialchars($_SESSION['admin_email'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="status-row">
                <span class="label">Authentication</span>
                <span class="badge-ok">ACTIVE SESSION</span>
            </div>
        </div>
    </div>

    <script>
        const dInput = document.getElementById('desktopImageInput');
        const dLive = document.getElementById('desktopLivePreview');
        dInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                dLive.textContent = `Selected: ${this.files[0].name} (${(this.files[0].size / (1024*1024)).toFixed(2)} MB)`;
                dLive.style.display = 'block';
            } else {
                dLive.style.display = 'none';
            }
        });

        const mInput = document.getElementById('mobileImageInput');
        const mLive = document.getElementById('mobileLivePreview');
        mInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                mLive.textContent = `Selected: ${this.files[0].name} (${(this.files[0].size / (1024*1024)).toFixed(2)} MB)`;
                mLive.style.display = 'block';
            } else {
                mLive.style.display = 'none';
            }
        });
    </script>
</body>
</html>
