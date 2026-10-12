<?php
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!csrf_verify()) {
    set_flash('error', 'Invalid security token. Action aborted.');
    header('Location: index.php');
    exit;
}

$imageId = filter_input(INPUT_POST, 'image_id', FILTER_VALIDATE_INT);
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

if (!$imageId || !$productId) {
    set_flash('error', 'Invalid request parameters.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare('SELECT id, image_path, is_primary FROM product_images WHERE id = :id AND product_id = :pid');
$stmt->execute(['id' => $imageId, 'pid' => $productId]);
$image = $stmt->fetch();

if (!$image) {
    set_flash('error', 'Image not found.');
    header("Location: images.php?id={$productId}");
    exit;
}

$filePath = __DIR__ . '/../../' . $image['image_path'];
if (file_exists($filePath) && is_file($filePath)) {
    @unlink($filePath);
}

// Clean up associated thumbnail variant if present (e.g. name-thumb.webp)
$pathInfo = pathinfo($filePath);
$thumbPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-thumb.webp';
if (file_exists($thumbPath) && is_file($thumbPath)) {
    @unlink($thumbPath);
}

$stmt = $pdo->prepare('DELETE FROM product_images WHERE id = :id');
$stmt->execute(['id' => $imageId]);

if ($image['is_primary']) {
    $stmt = $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE product_id = :pid ORDER BY sort_order ASC LIMIT 1');
    $stmt->execute(['pid' => $productId]);
}

set_flash('success', 'Image deleted successfully.');
header("Location: images.php?id={$productId}");
exit;
