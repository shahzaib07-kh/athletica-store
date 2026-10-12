<?php
/**
 * ATHLETICA — Reusable Utility Functions
 *
 * Provides helper functions for URL slugs, session flash messages,
 * and data formatting across the application.
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

/**
 * Generates a clean URL-friendly slug from an input string.
 *
 * Example: "Running Shoes & Sneakers" -> "running-shoes-sneakers"
 *
 * @param string $text Input string
 * @return string Clean slug
 */
function slugify(string $text): string
{
    // Replace non-alphanumeric characters with hyphens
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    // Transliterate characters to ASCII
    if (function_exists('iconv')) {
        $transliterated = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        if ($transliterated !== false) {
            $text = $transliterated;
        }
    }

    // Remove remaining unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // Trim leading/trailing hyphens and duplicate hyphens
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);

    // Convert to lowercase
    $text = strtolower($text);

    return !empty($text) ? $text : 'item';
}

/**
 * Sets a one-time flash message in the session.
 *
 * @param string $type Message type ('success', 'error', 'info', etc.)
 * @param string $message Message text
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Retrieves and clears a one-time flash message from the session.
 *
 * @param string $type Message type ('success', 'error', 'info', etc.)
 * @return string|null The flash message if present, or null
 */
function get_flash(string $type): ?string
{
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

/**
 * Translates PHP $_FILES error codes into clear, actionable error messages.
 *
 * @param int $errorCode
 * @return string
 */
function get_upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server upload limit (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the HTML form limit.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please check your connection and try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was selected for upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory on the server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to server disk. Check folder permissions.',
        UPLOAD_ERR_EXTENSION  => 'File upload blocked by server extension.',
        default               => "Upload failed (error code: {$errorCode}).",
    };
}

/**
 * Detects whether an image is isolated product photography on a clean/white/studio
 * background with excessive margins or non-4:3 aspect ratio.
 * If detected, centers and normalizes the subject onto a standardized 4:3 canvas (1200x900)
 * occupying ~86% width or ~78% height with contact shadows protected, safe padding, and no clipping.
 *
 * If the image is lifestyle photography, outdoor scene, dark background, or promotional banner,
 * safely returns false to bypass cropping.
 *
 * @param \GdImage $srcImg Source GD image resource
 * @param int $targetW Target canvas width (default 1200)
 * @param int $targetH Target canvas height (default 900)
 * @return \GdImage|false Normalized GD image resource or false if bypassed
 */
function safe_normalize_product_canvas($srcImg, int $targetW = 1200, int $targetH = 900)
{
    $w = imagesx($srcImg);
    $h = imagesy($srcImg);

    if ($w < 50 || $h < 50) {
        return false;
    }

    // 1. Multi-signal perimeter sampling: 4 corners + 16 perimeter edge points
    $borderPoints = [
        // Corners
        [0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1],
        // Top edge
        [(int)($w * 0.2), 0], [(int)($w * 0.4), 0], [(int)($w * 0.6), 0], [(int)($w * 0.8), 0],
        // Bottom edge
        [(int)($w * 0.2), $h - 1], [(int)($w * 0.4), $h - 1], [(int)($w * 0.6), $h - 1], [(int)($w * 0.8), $h - 1],
        // Left edge
        [0, (int)($h * 0.2)], [0, (int)($h * 0.4)], [0, (int)($h * 0.6)], [0, (int)($h * 0.8)],
        // Right edge
        [$w - 1, (int)($h * 0.2)], [$w - 1, (int)($h * 0.4)], [$w - 1, (int)($h * 0.6)], [$w - 1, (int)($h * 0.8)],
    ];

    $rVals = []; $gVals = []; $bVals = []; $lVals = []; $sVals = [];

    foreach ($borderPoints as [$px, $py]) {
        $c = imagecolorat($srcImg, $px, $py);
        $alpha = ($c >> 24) & 0x7F;
        if ($alpha >= 120) {
            // Transparent border pixel
            continue;
        }
        $r = ($c >> 16) & 0xFF;
        $g = ($c >> 8) & 0xFF;
        $b = $c & 0xFF;

        $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        $s = max($r, $g, $b) - min($r, $g, $b);

        $rVals[] = $r; $gVals[] = $g; $bVals[] = $b;
        $lVals[] = $l; $sVals[] = $s;
    }

    if (empty($rVals)) {
        // Fully transparent canvas -> Treat as pure white backdrop
        $bgR = 255; $bgG = 255; $bgB = 255; $bgL = 255.0;
    } else {
        $count = count($rVals);
        $bgR = (int)round(array_sum($rVals) / $count);
        $bgG = (int)round(array_sum($gVals) / $count);
        $bgB = (int)round(array_sum($bVals) / $count);
        $bgL = array_sum($lVals) / $count;

        // Check 1: Saturation / Chroma along perimeter must be neutral (low)
        $highSatCount = 0;
        foreach ($sVals as $s) {
            if ($s > 28) $highSatCount++;
        }
        if ($highSatCount > 2) {
            // Chromatic or colorful perimeter (Lifestyle / Complex outdoor scene)
            return false;
        }

        // Check 2: Average luminance must be bright (studio backdrop, L >= 170)
        if ($bgL < 170) {
            // Dark perimeter background (Lifestyle / Night / Non-studio)
            return false;
        }

        // Check 3: Perimeter uniformity (variance)
        $maxDiff = 0;
        for ($i = 0; $i < $count; $i++) {
            $diff = max(abs($rVals[$i] - $bgR), abs($gVals[$i] - $bgG), abs($bVals[$i] - $bgB));
            if ($diff > $maxDiff) $maxDiff = $diff;
        }
        if ($maxDiff > 30) {
            // Perimeter variance too high (Lifestyle / Complex / Uneven gradient)
            return false;
        }
    }

    // 2. Scan for subject bounding box (including natural contact shadows)
    $minX = $w; $minY = $h; $maxX = 0; $maxY = 0;
    $isPureWhite = ($bgR >= 252 && $bgG >= 252 && $bgB >= 252);
    $isWhiteBackdrop = ($bgR >= 245 && $bgG >= 245 && $bgB >= 245);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($srcImg, $x, $y);
            $alpha = ($c >> 24) & 0x7F;
            if ($alpha >= 120) continue;

            $r = ($c >> 16) & 0xFF;
            $g = ($c >> 8) & 0xFF;
            $b = $c & 0xFF;

            if ($isPureWhite) {
                $isSubject = ($r < 246 || $g < 246 || $b < 246);
            } else {
                $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $s = max($r, $g, $b) - min($r, $g, $b);
                $diff = max(abs($r - $bgR), abs($g - $bgG), abs($b - $bgB));
                $isSubject = ($diff > 10 || $s > 16 || $l < $bgL - 8);
            }

            if ($isSubject) {
                if ($x < $minX) $minX = $x;
                if ($x > $maxX) $maxX = $x;
                if ($y < $minY) $minY = $y;
                if ($y > $maxY) $maxY = $y;
            }
        }
    }

    if ($minX > $maxX || $minY > $maxY) {
        return false; // Blank or empty image
    }

    $subjectW = $maxX - $minX + 1;
    $subjectH = $maxY - $minY + 1;
    $fillW = $subjectW / $w;
    $fillH = $subjectH / $h;
    $aspectRatio = $w / $h;

    // Check 4: Edge bleed safety. Subject must not bleed off all 4 edges (Full bleed lifestyle photo)
    if ($minX <= 2 && $maxX >= $w - 3 && $minY <= 2 && $maxY >= $h - 3) {
        return false; // Full bleed lifestyle photo
    }

    // Check 5: Case C - Already well-framed on white canvas (fill between 70% and 88%, standard ratio)
    if ($isWhiteBackdrop && $fillW >= 0.70 && $fillW <= 0.88 && $aspectRatio >= 0.95 && $aspectRatio <= 1.55) {
        return false; // Already well-framed product photography, prevent over-cropping
    }

    // 3. Normalization: Pad subject bounding box safely to protect laces and contact shadows
    $padX = max(6, (int)round($subjectW * 0.015));
    $padY = max(6, (int)round($subjectH * 0.015));

    $cropX1 = max(0, $minX - $padX);
    $cropX2 = min($w - 1, $maxX + $padX);
    $cropY1 = max(0, $minY - $padY);
    $cropY2 = min($h - 1, $maxY + $padY);

    $cropW = $cropX2 - $cropX1 + 1;
    $cropH = $cropY2 - $cropY1 + 1;

    // Create intermediate cropped subject
    $crop = imagecreatetruecolor($cropW, $cropH);
    imagecopy($crop, $srcImg, 0, 0, $cropX1, $cropY1, $cropW, $cropH);

    // If background is NOT pure white (e.g. studio grey/off-white), normalize background & shadows to white
    if (!$isPureWhite) {
        $ratio = 255.0 / max(1.0, $bgL);
        for ($cy = 0; $cy < $cropH; $cy++) {
            for ($cx = 0; $cx < $cropW; $cx++) {
                $c = imagecolorat($crop, $cx, $cy);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $s = max($r, $g, $b) - min($r, $g, $b);
                $diff = max(abs($r - $bgR), abs($g - $bgG), abs($b - $bgB));

                // A: Pure background pixel -> Pure White
                if ($s <= 18 && $l >= $bgL - 8 && $diff <= 16) {
                    imagesetpixel($crop, $cx, $cy, imagecolorallocate($crop, 255, 255, 255));
                }
                // B: Soft contact shadow / edge transition -> Smoothly map onto white
                elseif ($s <= 24 && $l < $bgL - 6 && $l >= $bgL - 75) {
                    $newR = min(255, max(0, (int)round(255 - ($bgR - $r) * $ratio)));
                    $newG = min(255, max(0, (int)round(255 - ($bgG - $g) * $ratio)));
                    $newB = min(255, max(0, (int)round(255 - ($bgB - $b) * $ratio)));
                    imagesetpixel($crop, $cx, $cy, imagecolorallocate($crop, $newR, $newG, $newB));
                }
                // C: Genuine shoe pixel -> Kept 100% untouched
            }
        }
    }

    // 4. Place onto standardized 1200x900 4:3 Pure White Canvas
    $canvas = imagecreatetruecolor($targetW, $targetH);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, $white);

    // Scale to occupy ~86% width or ~78% height
    $scale = min(($targetW * 0.86) / $cropW, ($targetH * 0.78) / $cropH);
    $renderedW = (int)round($cropW * $scale);
    $renderedH = (int)round($cropH * $scale);

    $destX = (int)round(($targetW - $renderedW) / 2);
    $destY = (int)round(($targetH - $renderedH) / 2);

    imagecopyresampled($canvas, $crop, $destX, $destY, 0, 0, $renderedW, $renderedH, $cropW, $cropH);
    imagedestroy($crop);

    return $canvas;
}

/**
 * Optimizes an uploaded image: handles EXIF orientation, proportional downscaling
 * to max dimension (preserving aspect ratio and details), and creates an optimized WebP copy.
 * Also generates a fast web thumbnail variant for product cards.
 *
 * @param string $sourcePath Path to the uploaded raw image
 * @param string $destPath Path to save the main optimized image
 * @param int $maxDimension Max width or height for main image (e.g. 1800)
 * @param int $quality JPEG/WebP quality (default 85)
 * @param string|null $thumbDestPath Optional path for card thumbnail variant
 * @param int $thumbDimension Max dimension for thumbnail (e.g. 600)
 * @param bool $tryNormalize If true, attempts safe isolated product normalization to 4:3 canvas
 * @return bool
 */
function process_and_optimize_image(
    string $sourcePath,
    string $destPath,
    int $maxDimension = 1800,
    int $quality = 85,
    ?string $thumbDestPath = null,
    int $thumbDimension = 600,
    bool $tryNormalize = false
): bool {
    if (!extension_loaded('gd')) {
        // If GD not available for any reason, safely copy raw file
        return copy($sourcePath, $destPath);
    }

    $imageInfo = @getimagesize($sourcePath);
    if (!$imageInfo) {
        return copy($sourcePath, $destPath);
    }

    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $mime = $imageInfo['mime'];

    // Load source image resource
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png'  => @imagecreatefrompng($sourcePath),
        'image/webp' => @imagecreatefromwebp($sourcePath),
        default      => null,
    };

    if (!$src) {
        return copy($sourcePath, $destPath);
    }

    // Auto-rotate if EXIF orientation tag is present (for JPEG)
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($sourcePath);
        if (!empty($exif['Orientation'])) {
            $src = match ((int)$exif['Orientation']) {
                3 => imagerotate($src, 180, 0),
                6 => imagerotate($src, -90, 0),
                8 => imagerotate($src, 90, 0),
                default => $src,
            };
            $width = imagesx($src);
            $height = imagesy($src);
        }
    }

    // If normalization is requested (for products), test if this is an isolated product photo
    if ($tryNormalize) {
        $normalizedImg = safe_normalize_product_canvas($src, 1200, 900);
        if ($normalizedImg !== false) {
            imagedestroy($src);
            $src = $normalizedImg;
            $width = imagesx($src);
            $height = imagesy($src);
        }
    }

    // 1. Process Main Image: Proportional downscale ONLY if original exceeds $maxDimension
    $scale = min(1.0, $maxDimension / max($width, $height));
    $newWidth = (int)round($width * $scale);
    $newHeight = (int)round($height * $scale);

    $destImg = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG / WebP
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($destImg, false);
        imagesavealpha($destImg, true);
        $transparent = imagecolorallocatealpha($destImg, 0, 0, 0, 127);
        imagefilledrectangle($destImg, 0, 0, $newWidth, $newHeight, $transparent);
    } else {
        // Crisp white background for JPEGs
        $white = imagecolorallocate($destImg, 255, 255, 255);
        imagefilledrectangle($destImg, 0, 0, $newWidth, $newHeight, $white);
    }

    imagecopyresampled($destImg, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Save main image
    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
    $saved = match ($ext) {
        'jpg', 'jpeg' => imagejpeg($destImg, $destPath, $quality),
        'png'         => imagepng($destImg, $destPath, 8),
        'webp'        => imagewebp($destImg, $destPath, $quality),
        default       => false,
    };
    imagedestroy($destImg);

    // 2. Generate Thumbnail Variant if requested (for fast mobile/card loading)
    if ($thumbDestPath) {
        $thumbScale = min(1.0, $thumbDimension / max($width, $height));
        $thumbWidth = (int)round($width * $thumbScale);
        $thumbHeight = (int)round($height * $thumbScale);

        $thumbImg = imagecreatetruecolor($thumbWidth, $thumbHeight);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($thumbImg, false);
            imagesavealpha($thumbImg, true);
            $transparent = imagecolorallocatealpha($thumbImg, 0, 0, 0, 127);
            imagefilledrectangle($thumbImg, 0, 0, $thumbWidth, $thumbHeight, $transparent);
        } else {
            $white = imagecolorallocate($thumbImg, 255, 255, 255);
            imagefilledrectangle($thumbImg, 0, 0, $thumbWidth, $thumbHeight, $white);
        }

        imagecopyresampled($thumbImg, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

        $thumbExt = strtolower(pathinfo($thumbDestPath, PATHINFO_EXTENSION));
        match ($thumbExt) {
            'webp'        => imagewebp($thumbImg, $thumbDestPath, 80),
            'jpg', 'jpeg' => imagejpeg($thumbImg, $thumbDestPath, 80),
            'png'         => imagepng($thumbImg, $thumbDestPath, 7),
            default       => null,
        };
        imagedestroy($thumbImg);
    }

    imagedestroy($src);
    return $saved;
}

/**
 * Securely uploads and optimizes a product image file.
 *
 * Preserves the original uploaded file untouched as prod-{id}-{token}-orig.{ext}.
 * Applies safe product normalization on isolated product photography into a 4:3 canvas
 * with ~86% width fill, centering, and contact shadow preservation.
 * Safely bypasses lifestyle/complex backgrounds with standard proportional scaling.
 * Generates display variant and -thumb.webp thumbnail.
 *
 * @param array  $file      Single file from $_FILES (not the full $_FILES array)
 * @param int    $productId Product ID for filename prefix
 * @return array ['success' => bool, 'path' => string, 'orig_path' => string, 'thumb_path' => string, 'error' => string]
 */
function upload_product_image(array $file, int $productId, int $maxBytes = 10485760): array
{
    $result = ['success' => false, 'path' => '', 'error' => ''];

    // 1. Check for upload errors
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $msg = get_upload_error_message((int)($file['error'] ?? -1));
        $result['error'] = 'File upload failed: ' . $msg;
        return $result;
    }

    // 2. Validate file size (10MB limit)
    if ($file['size'] > $maxBytes || (empty($file['tmp_name']) && $file['size'] > 2 * 1024 * 1024)) {
        $actualMb = number_format($file['size'] / (1024 * 1024), 1);
        $result['error'] = "Image exceeds the maximum file size limit of 10MB (file {$actualMb}MB exceeds 2MB/10MB limit).";
        return $result;
    }

    if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        $result['error'] = 'File upload failed: Temporary upload file missing or not uploaded.';
        return $result;
    }

    if ($file['size'] < 10) {
        $result['error'] = 'The selected file is empty or corrupted.';
        return $result;
    }

    // 3. Validate MIME type
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);

    if (!isset($allowedMimes[$detectedMime])) {
        $result['error'] = 'Invalid image type (' . htmlspecialchars((string)$detectedMime, ENT_QUOTES, 'UTF-8') . '). Allowed formats: JPG, JPEG, PNG, WebP.';
        return $result;
    }

    // 4. Image integrity and dimension validation
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        $result['error'] = 'The file could not be parsed as a valid image (corrupted header).';
        return $result;
    }

    $width = $imageInfo[0];
    $height = $imageInfo[1];

    if ($width < 200 || $height < 200) {
        $result['error'] = "Image resolution ({$width}×{$height}px) is too small. Please upload an image of at least 200×200 pixels.";
        return $result;
    }

    if ($width > 10000 || $height > 10000) {
        $result['error'] = "Image dimensions ({$width}×{$height}px) exceed the 10,000px maximum limit.";
        return $result;
    }

    $extension = $allowedMimes[$detectedMime];

    // 5. Generate collision-resistant unique filenames
    $randomToken = bin2hex(random_bytes(8));
    $filename = sprintf('prod-%d-%s.%s', $productId, $randomToken, $extension);
    $origFilename = sprintf('prod-%d-%s-orig.%s', $productId, $randomToken, $extension);
    $thumbFilename = sprintf('prod-%d-%s-thumb.webp', $productId, $randomToken);

    // 6. Ensure upload directory exists
    $uploadDir = __DIR__ . '/../uploads/products';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . '/' . $filename;
    $origDestination = $uploadDir . '/' . $origFilename;
    $thumbDestination = $uploadDir . '/' . $thumbFilename;

    // 7. PRESERVE ORIGINAL: Move raw uploaded file directly to $origDestination
    if (!@move_uploaded_file($file['tmp_name'], $origDestination)) {
        if (!@copy($file['tmp_name'], $origDestination)) {
            $result['error'] = 'Failed to write original image to disk. Check folder write permissions.';
            return $result;
        }
    }

    // 8. Process and optimize display variant (with safe normalization pipeline enabled)
    $processed = process_and_optimize_image(
        $origDestination,
        $destination,
        1800,
        88,
        $thumbDestination,
        600,
        true // Enable safe normalization pipeline
    );

    if (!$processed || !file_exists($destination)) {
        // Fallback: copy original as display variant
        @copy($origDestination, $destination);
    }

    // 9. Mirror to scratch serving root if running from htdocs, or vice versa
    $scratchServingDir = 'C:/Users/Shahzaib Khan/.gemini/antigravity/scratch/athletica/uploads/products';
    $htdocsServingDir  = 'C:/xampp/htdocs/athletica/uploads/products';
    $syncTargets = [$destination, $origDestination, $thumbDestination];

    foreach ($syncTargets as $syncFile) {
        if (!file_exists($syncFile)) continue;
        $baseName = basename($syncFile);
        if (realpath($uploadDir) !== realpath($scratchServingDir) && is_dir(dirname($scratchServingDir))) {
            if (!is_dir($scratchServingDir)) @mkdir($scratchServingDir, 0755, true);
            @copy($syncFile, $scratchServingDir . '/' . $baseName);
        }
        if (realpath($uploadDir) !== realpath($htdocsServingDir) && is_dir(dirname($htdocsServingDir))) {
            if (!is_dir($htdocsServingDir)) @mkdir($htdocsServingDir, 0755, true);
            @copy($syncFile, $htdocsServingDir . '/' . $baseName);
        }
    }

    // 10. Return relative display path for DB storage
    $result['success'] = true;
    $result['path'] = 'uploads/products/' . $filename;
    $result['orig_path'] = 'uploads/products/' . $origFilename;
    $result['thumb_path'] = 'uploads/products/' . $thumbFilename;
    return $result;
}

/**
 * Securely uploads a brand logo image.
 *
 * @param array $file Single file array from $_FILES
 * @param int   $brandId Brand ID for filename prefix
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function upload_brand_logo(array $file, int $brandId): array
{
    $result = ['success' => false, 'path' => '', 'error' => ''];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = get_upload_error_message((int)($file['error'] ?? -1));
        return $result;
    }

    $maxSize = 5 * 1024 * 1024; // 5MB limit
    if ($file['size'] > $maxSize) {
        $result['error'] = 'Logo exceeds the maximum file size of 5MB.';
        return $result;
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);

    if (!isset($allowedMimes[$detectedMime])) {
        $result['error'] = 'Invalid logo type. Allowed formats: JPG, PNG, WebP.';
        return $result;
    }

    $extension = $allowedMimes[$detectedMime];
    $filename = sprintf('brand-%d-%s.%s', $brandId, bin2hex(random_bytes(8)), $extension);

    $uploadDir = __DIR__ . '/../uploads/brands';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . '/' . $filename;
    
    // Proportional logo optimization
    $processed = process_and_optimize_image($file['tmp_name'], $destination, 800, 90);
    if (!$processed && !move_uploaded_file($file['tmp_name'], $destination)) {
        $result['error'] = 'Failed to save uploaded brand logo.';
        return $result;
    }

    $result['success'] = true;
    $result['path'] = 'uploads/brands/' . $filename;
    return $result;
}

/**
 * Securely uploads and optimizes a category image file.
 *
 * @param array $file Single file array from $_FILES
 * @param int   $categoryId Category ID for filename prefix
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function upload_category_image(array $file, int $categoryId): array
{
    $result = ['success' => false, 'path' => '', 'error' => ''];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = get_upload_error_message((int)($file['error'] ?? -1));
        return $result;
    }

    $maxSize = 8 * 1024 * 1024; // 8MB limit
    if ($file['size'] > $maxSize) {
        $result['error'] = 'Image exceeds the maximum file size of 8MB.';
        return $result;
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);

    if (!isset($allowedMimes[$detectedMime])) {
        $result['error'] = 'Invalid image type. Allowed formats: JPG, PNG, WebP.';
        return $result;
    }

    $extension = $allowedMimes[$detectedMime];
    $filename = sprintf('cat-%d-%s.%s', $categoryId, bin2hex(random_bytes(8)), $extension);

    $uploadDir = __DIR__ . '/../uploads/categories';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . '/' . $filename;
    
    // Proportional category card image optimization (max 1000px, quality 88)
    $processed = process_and_optimize_image($file['tmp_name'], $destination, 1000, 88);
    if (!$processed && !move_uploaded_file($file['tmp_name'], $destination)) {
        $result['error'] = 'Failed to save uploaded category image.';
        return $result;
    }

    // Mirror to scratch if running from htdocs, or vice versa
    $scratchServingDir = 'C:/Users/Shahzaib Khan/.gemini/antigravity/scratch/athletica/uploads/categories';
    $htdocsServingDir  = 'C:/xampp/htdocs/athletica/uploads/categories';
    if (file_exists($destination)) {
        if (realpath($uploadDir) !== realpath($scratchServingDir) && is_dir(dirname($scratchServingDir))) {
            if (!is_dir($scratchServingDir)) {
                @mkdir($scratchServingDir, 0755, true);
            }
            @copy($destination, $scratchServingDir . '/' . $filename);
        }
        if (realpath($uploadDir) !== realpath($htdocsServingDir) && is_dir(dirname($htdocsServingDir))) {
            if (!is_dir($htdocsServingDir)) {
                @mkdir($htdocsServingDir, 0755, true);
            }
            @copy($destination, $htdocsServingDir . '/' . $filename);
        }
    }

    $result['success'] = true;
    $result['path'] = 'uploads/categories/' . $filename;
    return $result;
}

/**
 * Securely uploads and optimizes a hero slide media file (image or video).
 *
 * Supports up to 10MB for images and 30MB for videos. Images are validated,
 * EXIF auto-rotated, and scaled proportionally (preserving 16:9 / wide banners
 * without distortion).
 *
 * @param array $file Single file array from $_FILES
 * @return array ['success' => bool, 'path' => string, 'media_type' => string, 'error' => string]
 */
function upload_hero_media(array $file): array
{
    $result = ['success' => false, 'path' => '', 'media_type' => 'image', 'error' => ''];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = get_upload_error_message((int)($file['error'] ?? -1));
        return $result;
    }

    $allowedImageMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $allowedVideoMimes = [
        'video/mp4'  => 'mp4',
        'video/webm' => 'webm',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);

    if (isset($allowedImageMimes[$detectedMime])) {
        $mediaType = 'image';
        $extension = $allowedImageMimes[$detectedMime];
        $maxSize = 10 * 1024 * 1024; // 10 MB for images
        if ($file['size'] > $maxSize) {
            $actualMb = number_format($file['size'] / (1024 * 1024), 1);
            $result['error'] = "Slide image size ({$actualMb}MB) exceeds the maximum allowed limit of 10MB.";
            return $result;
        }

        // Image integrity and dimension check
        $imgSize = @getimagesize($file['tmp_name']);
        if (!$imgSize) {
            $result['error'] = 'The slide image file is corrupted and cannot be read.';
            return $result;
        }

        if ($imgSize[0] < 200 || $imgSize[1] < 200) {
            $result['error'] = "Slide image resolution ({$imgSize[0]}×{$imgSize[1]}px) is too small. Minimum 200×200 required (high-res landscape ~1920×900 recommended).";
            return $result;
        }

    } elseif (isset($allowedVideoMimes[$detectedMime])) {
        $mediaType = 'video';
        $extension = $allowedVideoMimes[$detectedMime];
        $maxSize = 30 * 1024 * 1024; // 30 MB for videos
        if ($file['size'] > $maxSize) {
            $actualMb = number_format($file['size'] / (1024 * 1024), 1);
            $result['error'] = "Slide video size ({$actualMb}MB) exceeds the maximum allowed limit of 30MB.";
            return $result;
        }
    } else {
        $result['error'] = 'Invalid file type (' . htmlspecialchars((string)$detectedMime, ENT_QUOTES, 'UTF-8') . '). Allowed formats: JPG, PNG, WebP (Images) or MP4, WebM (Videos).';
        return $result;
    }

    $filename = sprintf('slide-%s.%s', bin2hex(random_bytes(10)), $extension);
    $uploadDir = __DIR__ . '/../uploads/slides';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . '/' . $filename;

    // Dual-write target: if running from htdocs or scratch, ensure the active scratch serving root also receives the file
    $scratchServingDir = 'C:/Users/Shahzaib Khan/.gemini/antigravity/scratch/athletica/uploads/slides';
    $htdocsServingDir  = 'C:/xampp/htdocs/athletica/uploads/slides';

    if ($mediaType === 'image') {
        // Optimize banner: max 2560px wide, high quality 88
        $processed = process_and_optimize_image($file['tmp_name'], $destination, 2560, 88);
        if (!$processed && !move_uploaded_file($file['tmp_name'], $destination)) {
            $result['error'] = 'Failed to save slide image to destination.';
            return $result;
        }
    } else {
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $result['error'] = 'Failed to save uploaded video to destination.';
            return $result;
        }
    }

    // Mirror to active scratch server root if saved elsewhere
    if (file_exists($destination)) {
        if (realpath($uploadDir) !== realpath($scratchServingDir) && is_dir(dirname($scratchServingDir))) {
            if (!is_dir($scratchServingDir)) {
                @mkdir($scratchServingDir, 0755, true);
            }
            @copy($destination, $scratchServingDir . '/' . $filename);
        }
        if (realpath($uploadDir) !== realpath($htdocsServingDir) && is_dir(dirname($htdocsServingDir))) {
            if (!is_dir($htdocsServingDir)) {
                @mkdir($htdocsServingDir, 0755, true);
            }
            @copy($destination, $htdocsServingDir . '/' . $filename);
        }
    }

    $result['success'] = true;
    $result['path'] = 'uploads/slides/' . $filename;
    $result['media_type'] = $mediaType;
    return $result;
}

/**
 * Generates a collision-resistant unique order number in format: ATH-YYYYMMDD-NNNN
 *
 * @param PDO $pdo Database connection
 * @return string Unique order number string
 */
function generate_order_number(PDO $pdo): string
{
    $datePrefix = 'ATH-' . date('Ymd') . '-';
    for ($i = 0; $i < 10; $i++) {
        $randomSuffix = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $candidate = $datePrefix . $randomSuffix;
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = :num LIMIT 1");
        $stmt->execute([':num' => $candidate]);
        if (!$stmt->fetch()) {
            return $candidate;
        }
    }
    return $datePrefix . substr((string)time(), -4);
}

/**
 * Normalizes any Pakistani customer or business phone number to pure digits
 * formatted for WhatsApp deep links (e.g. 923001234567).
 *
 * Accepts: '03001234567', '+923001234567', '00923001234567', '3001234567', '0300-1234567'
 * Returns: '923001234567'
 *
 * @param string $phone Raw phone string
 * @param string $defaultCountryCode Default country code (default '92')
 * @return string Normalized digit string
 */
function normalize_whatsapp_number(string $phone, string $defaultCountryCode = '92'): string
{
    // 1. Strip all non-digit characters
    $digits = preg_replace('/[^\d]/', '', $phone);

    // 2. Handle '0092' prefix -> '92'
    if (str_starts_with($digits, '00' . $defaultCountryCode)) {
        $digits = substr($digits, 2);
    }
    // 3. Handle local leading zero '03xx' -> '923xx' (11 digits)
    elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
        $digits = $defaultCountryCode . substr($digits, 1);
    }
    // 4. Handle 10-digit without prefix '3xxxxxxxxx' -> '923xxxxxxxxx'
    elseif (strlen($digits) === 10 && str_starts_with($digits, '3')) {
        $digits = $defaultCountryCode . $digits;
    }

    return $digits;
}

/**
 * Builds a clean, professional pre-filled customer order message for WhatsApp.
 * Sources 100% of data from trusted order and snapshot rows.
 *
 * @param array $order Order record from orders table
 * @param array $orderItems Snapshot rows from order_items table
 * @return string Formatted plain-text WhatsApp message
 */
function build_customer_order_whatsapp_message(array $order, array $orderItems): string
{
    $msg = "Hello ATHLETICA,\n\n";
    $msg .= "I have placed a Cash on Delivery order on your website.\n\n";
    $msg .= "Order #: " . ($order['order_number'] ?? 'N/A') . "\n\n";
    $msg .= "Customer Details:\n";
    $msg .= "Name: " . ($order['customer_name'] ?? '') . "\n";
    $msg .= "Phone: " . ($order['customer_phone'] ?? '') . "\n";
    $msg .= "City: " . ($order['city'] ?? '') . "\n";
    $msg .= "Delivery Address: " . ($order['delivery_address'] ?? '') . "\n\n";

    $msg .= "Items Ordered:\n";
    foreach ($orderItems as $item) {
        $sizeStr = !empty($item['product_size']) ? ' | Size: ' . $item['product_size'] : '';
        $colorStr = !empty($item['product_color']) ? ' | Color: ' . $item['product_color'] : '';
        $msg .= sprintf(
            "• %s%s%s | Qty: %d | Price: Rs. %s | Total: Rs. %s\n",
            $item['product_name'],
            $sizeStr,
            $colorStr,
            (int)$item['quantity'],
            number_format((float)$item['product_price'], 2),
            number_format((float)$item['line_total'], 2)
        );
    }

    $msg .= "\nOrder Summary:\n";
    $msg .= "Subtotal: Rs. " . number_format((float)($order['subtotal'] ?? 0), 2) . "\n";
    $deliveryFee = (float)($order['delivery_fee'] ?? 0);
    $msg .= "Delivery Fee: " . ($deliveryFee == 0 ? "FREE (Rs. 0.00)" : "Rs. " . number_format($deliveryFee, 2)) . "\n";
    $msg .= "Total Amount Due at Delivery: Rs. " . number_format((float)($order['total_amount'] ?? 0), 2) . "\n";
    $msg .= "Payment Method: Cash on Delivery (COD)\n\n";
    $msg .= "Please confirm my order and dispatch details.";

    return $msg;
}

/**
 * Builds a standardized admin-to-customer WhatsApp order verification message.
 * Strictly excludes internal notes or confidential administrative details.
 *
 * @param array $order Order record from orders table
 * @param array $orderItems Snapshot rows from order_items table
 * @return string Formatted plain-text WhatsApp message
 */
function build_admin_verification_whatsapp_message(array $order, array $orderItems): string
{
    $msg = sprintf("Hello %s,\n\n", $order['customer_name'] ?? 'Customer');
    $msg .= sprintf("This is ATHLETICA regarding your Cash on Delivery order #%s.\n\n", $order['order_number'] ?? '');
    $msg .= "Order Details:\n";
    $msg .= sprintf("• %d item(s) totaling Rs. %s\n", count($orderItems), number_format((float)($order['total_amount'] ?? 0), 2));
    $msg .= "• Delivery City: " . ($order['city'] ?? '') . "\n";
    $msg .= "• Shipping Address: " . ($order['delivery_address'] ?? '') . "\n\n";
    $msg .= "We are contacting you to confirm your address and dispatch availability. Please reply to confirm.";

    return $msg;
}


