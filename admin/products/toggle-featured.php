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

$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

if (!$productId) {
    set_flash('error', 'Invalid product ID.');
    header('Location: index.php');
    exit;
}

// Validate product exists
$stmt = $pdo->prepare('SELECT id FROM products WHERE id = :id');
$stmt->execute(['id' => $productId]);
if (!$stmt->fetch()) {
    set_flash('error', 'Product not found.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('UPDATE products SET is_featured = NOT is_featured WHERE id = :id');
$stmt->execute(['id' => $productId]);

set_flash('success', 'Product featured status updated.');
header('Location: index.php');
exit;
