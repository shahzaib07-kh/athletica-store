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

$sizeId = filter_input(INPUT_POST, 'size_id', FILTER_VALIDATE_INT);
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

if (!$sizeId || !$productId) {
    set_flash('error', 'Invalid request parameters.');
    header("Location: index.php");
    exit;
}

// Validate size record exists AND belongs to this product
$stmt = $pdo->prepare('SELECT id FROM product_sizes WHERE id = :id AND product_id = :pid');
$stmt->execute(['id' => $sizeId, 'pid' => $productId]);
if (!$stmt->fetch()) {
    set_flash('error', 'Size not found for this product.');
    header("Location: sizes.php?id={$productId}");
    exit;
}

// Count remaining sizes for this product
$stmt = $pdo->prepare('SELECT COUNT(*) FROM product_sizes WHERE product_id = :pid');
$stmt->execute(['pid' => $productId]);
$count = (int)$stmt->fetchColumn();

if ($count <= 1) {
    set_flash('error', 'Cannot delete the last size. A product must have at least one size.');
    header("Location: sizes.php?id={$productId}");
    exit;
}

$stmt = $pdo->prepare('DELETE FROM product_sizes WHERE id = :id AND product_id = :pid');
$stmt->execute(['id' => $sizeId, 'pid' => $productId]);

set_flash('success', 'Size deleted successfully.');
header("Location: sizes.php?id={$productId}");
exit;
