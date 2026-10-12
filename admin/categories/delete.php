<?php
/**
 * ATHLETICA — Category Management: Delete Category
 *
 * Secure POST-only handler for deleting categories.
 * Enforces business deletion safety: categories with associated products
 * CANNOT be deleted to protect product records and order integrity.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// 1. Enforce POST Method (Reject GET / Direct URL hits)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method. Deletion must be submitted via POST.');
    header('Location: index.php');
    exit;
}

// 2. Verify CSRF Token
if (!csrf_verify()) {
    set_flash('error', 'Invalid or expired security token. Deletion aborted.');
    header('Location: index.php');
    exit;
}

// 3. Validate Category ID
$categoryId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if (!$categoryId || $categoryId <= 0) {
    set_flash('error', 'Invalid category ID specified for deletion.');
    header('Location: index.php');
    exit;
}

// 4. Fetch Category to Ensure Existence
$stmt = $pdo->prepare('SELECT id, name FROM categories WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $categoryId]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('error', 'Category not found.');
    header('Location: index.php');
    exit;
}

// 5. SAFETY CHECK: Check for Associated Products
// Foreign key constraint in schema uses ON DELETE RESTRICT, but we check explicitly
// to give a clean, informative error message and guide the admin to deactivate instead.
$checkStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM products WHERE category_id = :id');
$checkStmt->execute([':id' => $categoryId]);
$productCount = (int) $checkStmt->fetchColumn();

if ($productCount > 0) {
    set_flash('error', sprintf(
        'Cannot delete category "%s" because %d product(s) are currently associated with it. Please deactivate the category instead to hide it without breaking product records.',
        $category['name'],
        $productCount
    ));
    header('Location: index.php');
    exit;
}

// 6. Safe Deletion (No products attached)
try {
    $deleteStmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
    $deleteStmt->execute([':id' => $categoryId]);

    set_flash('success', sprintf('Category "%s" was permanently deleted.', $category['name']));
} catch (PDOException $e) {
    error_log('Category Delete Failed: ' . $e->getMessage());
    set_flash('error', 'An error occurred while deleting the category.');
}

header('Location: index.php');
exit;
