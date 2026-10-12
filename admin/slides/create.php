<?php
/**
 * ATHLETICA — Admin: Create Hero Slide
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

$errors = [];
$formData = [
    'title'      => '',
    'subtitle'   => '',
    'cta_text'   => '',
    'cta_url'    => '',
    'sort_order' => 0,
    'is_active'  => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid or expired security token. Please try again.';
    }

    $formData['title']      = trim($_POST['title'] ?? '');
    $formData['subtitle']   = trim($_POST['subtitle'] ?? '');
    $formData['cta_text']   = trim($_POST['cta_text'] ?? '');
    $formData['cta_url']    = trim($_POST['cta_url'] ?? '');
    $formData['sort_order'] = (int)($_POST['sort_order'] ?? 0);
    $formData['is_active']  = isset($_POST['is_active']) ? 1 : 0;

    if (empty($formData['title'])) {
        $errors[] = 'Slide title / headline is required.';
    }

    if (empty($_FILES['media']['name']) || $_FILES['media']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose a media file (Image or Video) to upload.';
    }

    if (empty($errors)) {
        $upload = upload_hero_media($_FILES['media']);
        if (!$upload['success']) {
            $errors[] = 'Desktop Media: ' . $upload['error'];
        } else {
            $mobileImage = null;
            if (!empty($_FILES['mobile_media']['name']) && $_FILES['mobile_media']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadMobile = upload_hero_media($_FILES['mobile_media']);
                if (!$uploadMobile['success']) {
                    $errors[] = 'Mobile Media: ' . $uploadMobile['error'];
                } else {
                    $mobileImage = $uploadMobile['path'];
                }
            }

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO hero_slides (title, subtitle, media_path, mobile_image, media_type, cta_text, cta_url, sort_order, is_active)
                        VALUES (:title, :subtitle, :media_path, :mobile_image, :media_type, :cta_text, :cta_url, :sort_order, :is_active)
                    ");
                    $stmt->execute([
                        ':title'        => $formData['title'],
                        ':subtitle'     => $formData['subtitle'] ?: null,
                        ':media_path'   => $upload['path'],
                        ':mobile_image' => $mobileImage,
                        ':media_type'   => $upload['media_type'],
                        ':cta_text'     => $formData['cta_text'] ?: null,
                        ':cta_url'      => $formData['cta_url'] ?: null,
                        ':sort_order'   => $formData['sort_order'],
                        ':is_active'    => $formData['is_active'],
                    ]);

                    set_flash('success', 'Hero slide created successfully.');
                    header('Location: index.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Hero Slide — ATHLETICA Admin</title>
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
        
        .container { max-width: 800px; margin: 0 auto; padding: 32px 24px; }
        .card { background: #161616; border: 1px solid #262626; border-radius: 8px; padding: 28px; }
        h1 { font-size: 20px; font-weight: 600; color: #fff; margin-bottom: 20px; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #aaa; margin-bottom: 8px; }
        input[type="text"], input[type="number"], textarea {
            width: 100%; padding: 10px 14px; background: #101010; border: 1px solid #303030; border-radius: 4px; color: #fff; font-size: 14px;
        }
        input:focus, textarea:focus { border-color: #60a5fa; outline: none; }
        .hint { font-size: 11px; color: #777; margin-top: 4px; }
        
        .alert-error { background: #450a0a; color: #fca5a5; border: 1px solid #dc2626; padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; }
        
        .btn-actions { display: flex; gap: 12px; margin-top: 28px; align-items: center; }
        .btn-submit { background: #fff; color: #000; padding: 10px 24px; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-cancel { background: transparent; color: #aaa; border: 1px solid #333; padding: 10px 20px; border-radius: 4px; font-size: 13px; text-decoration: none; }
        .spinner { width: 14px; height: 14px; border: 2px solid #666; border-top-color: #000; border-radius: 50%; animation: spin 0.8s linear infinite; display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .media-preview-box { margin-top: 10px; padding: 12px; background: #0a0a0a; border: 1px solid #262626; border-radius: 6px; display: none; align-items: center; gap: 16px; }
        .preview-media { max-height: 90px; max-width: 140px; border-radius: 4px; object-fit: cover; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand-group">
            <a href="../dashboard.php" class="brand">ATHLETICA</a>
            <nav class="nav-links">
                <a href="../dashboard.php" class="nav-link">Dashboard</a>
                <a href="index.php" class="nav-link active">Hero Slides</a>
                <a href="../flash-sales/index.php" class="nav-link">Flash Sales</a>
            </nav>
        </div>
        <div><a href="../logout.php" class="btn-logout">Logout</a></div>
    </header>

    <div class="container">
        <div class="card">
            <h1>Add New Hero Slide</h1>

            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <?php foreach ($errors as $err): ?>
                        <div>• <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="create.php" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label>Slide Headline / Title *</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($formData['title'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. POWER YOUR PERFORMANCE">
                </div>

                <div class="form-group">
                    <label>Subtitle / Description</label>
                    <textarea name="subtitle" rows="2" placeholder="e.g. Handpicked genuine athletic footwear and sportswear."><?= htmlspecialchars($formData['subtitle'], ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- 2-COLUMN MEDIA UPLOAD: DESKTOP & MOBILE -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <!-- DESKTOP HERO MEDIA -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Desktop Hero Media (Landscape) *</label>
                        <input type="file" id="mediaInput" name="media" required accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">
                        <div class="hint">Accepts high-res JPG, PNG, WebP (max 10MB) or MP4, WebM (max 30MB). High-resolution landscape (~1920×900) recommended.</div>
                        <div id="mediaPreviewBox" class="media-preview-box">
                            <div id="previewMediaContainer"></div>
                            <div>
                                <div id="previewFileName" style="font-size: 12px; font-weight: 600; color: #fff;"></div>
                                <div id="previewFileSize" style="font-size: 11px; color: #888;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- MOBILE HERO IMAGE -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Mobile Hero Image (Portrait)</label>
                        <input type="file" id="mobileMediaInput" name="mobile_media" accept="image/jpeg,image/png,image/webp">
                        <div class="hint">Portrait format (~750×1100 or 9:16) for mobile phones. Optional: falls back to Desktop media if omitted. JPG, PNG, WebP (max 10MB).</div>
                        <div id="mobileMediaPreviewBox" class="media-preview-box">
                            <div id="mobilePreviewMediaContainer"></div>
                            <div>
                                <div id="mobilePreviewFileName" style="font-size: 12px; font-weight: 600; color: #fff;"></div>
                                <div id="mobilePreviewFileSize" style="font-size: 11px; color: #888;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>CTA Button Text</label>
                        <input type="text" name="cta_text" value="<?= htmlspecialchars($formData['cta_text'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. SHOP NOW">
                    </div>
                    <div class="form-group">
                        <label>CTA Target URL</label>
                        <input type="text" name="cta_url" value="<?= htmlspecialchars($formData['cta_url'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. /shop or /category/shoes">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" value="<?= (int)$formData['sort_order'] ?>" min="0">
                        <div class="hint">Lower numbers display first (0, 1, 2...).</div>
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; gap: 8px; padding-top: 24px;">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?= $formData['is_active'] ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                        <label for="is_active" style="margin-bottom: 0; cursor: pointer;">Active (Visible on homepage)</label>
                    </div>
                </div>

                <div class="btn-actions">
                    <button type="submit" id="submitBtn" class="btn-submit">
                        <span class="spinner" id="submitSpinner"></span>
                        <span id="submitBtnText">Create Hero Slide</span>
                    </button>
                    <a href="index.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const form = document.querySelector('form');
        const mediaInput = document.getElementById('mediaInput');
        const previewBox = document.getElementById('mediaPreviewBox');
        const previewMediaContainer = document.getElementById('previewMediaContainer');
        const previewFileName = document.getElementById('previewFileName');
        const previewFileSize = document.getElementById('previewFileSize');

        const mobileMediaInput = document.getElementById('mobileMediaInput');
        const mobilePreviewBox = document.getElementById('mobileMediaPreviewBox');
        const mobilePreviewMediaContainer = document.getElementById('mobilePreviewMediaContainer');
        const mobilePreviewFileName = document.getElementById('mobilePreviewFileName');
        const mobilePreviewFileSize = document.getElementById('mobilePreviewFileSize');

        const submitBtn = document.getElementById('submitBtn');
        const submitSpinner = document.getElementById('submitSpinner');
        const submitBtnText = document.getElementById('submitBtnText');

        // Instant desktop media preview
        mediaInput.addEventListener('change', function() {
            previewMediaContainer.innerHTML = '';
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                previewFileName.textContent = file.name;
                previewFileSize.textContent = `${sizeMb} MB • ${file.type || 'unknown format'}`;

                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.className = 'preview-media';
                    img.src = URL.createObjectURL(file);
                    previewMediaContainer.appendChild(img);
                    previewBox.style.display = 'flex';
                } else if (file.type.startsWith('video/')) {
                    const video = document.createElement('video');
                    video.className = 'preview-media';
                    video.src = URL.createObjectURL(file);
                    video.muted = true;
                    video.autoplay = false;
                    previewMediaContainer.appendChild(video);
                    previewBox.style.display = 'flex';
                } else {
                    previewBox.style.display = 'flex';
                }
            } else {
                previewBox.style.display = 'none';
            }
        });

        // Instant mobile media preview
        mobileMediaInput.addEventListener('change', function() {
            mobilePreviewMediaContainer.innerHTML = '';
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                mobilePreviewFileName.textContent = file.name;
                mobilePreviewFileSize.textContent = `${sizeMb} MB • ${file.type || 'unknown format'}`;

                const img = document.createElement('img');
                img.className = 'preview-media';
                img.src = URL.createObjectURL(file);
                mobilePreviewMediaContainer.appendChild(img);
                mobilePreviewBox.style.display = 'flex';
            } else {
                mobilePreviewBox.style.display = 'none';
            }
        });

        // Visible loading state on submit
        form.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitSpinner.style.display = 'inline-block';
            submitBtnText.textContent = 'Saving Slide...';
        });
    </script>
</body>
</html>
