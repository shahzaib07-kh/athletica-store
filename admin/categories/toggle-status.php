<?php
/**
 * ATHLETICA — Category Management: Toggle Active / Inactive Status
 *
 * Secure POST-only handler for activating or deactivating categories.
 * Allows hiding categories from the storefront without deleting them.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// 1. Enforce POST Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method. Status update must be submitted via POST.');
    header('Location: index.php');
    exit;
}

// 2. Verify CSRF Token
if (!csrf_verify()) {
    set_flash('error', 'Invalid or expired security token. Action aborted.');
    header('Location: index.php');
    exit;
}

// 3. Validate Category ID
$categoryId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if (!$categoryId || $categoryId <= 0) {
    set_flash('error', 'Invalid category ID.');
    header('Location: index.php');
    exit;
}

// 4. Fetch Category
$stmt = $pdo->prepare('SELECT id, name, is_active FROM categories WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $categoryId]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('error', 'Category not found.');
    header('Location: index.php');
    exit;
}

// 5. Toggle Status
$newStatus = $category['is_active'] ? 0 : 1;

try {
    $updateStmt = $pdo->prepare('UPDATE categories SET is_active = :is_active WHERE id = :id');
    $updateStmt->execute([
        ':is_active' => $newStatus,
        ':id'        => $categoryId,
    ]);

    set_flash('success', sprintf(
        'Category "%s" was %s successfully.',
        $category['name'],
        $newStatus ? 'activated' : 'deactivated'
    ));
} catch (PDOException $e) {
    error_log('Category Status Toggle Failed: ' . $e->getMessage());
    set_flash('error', 'An error occurred while updating category status.');
}

header('Location: index.php');
exit;
