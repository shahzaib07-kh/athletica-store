<?php
/**
 * ATHLETICA — Brand Management: Toggle Status (Active / Inactive)
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    set_flash('error', 'Method not allowed.');
    header('Location: index.php');
    exit;
}

// Verify CSRF token strictly
if (!csrf_verify()) {
    set_flash('error', 'Invalid or expired security token.');
    header('Location: index.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    set_flash('error', 'Invalid brand ID.');
    header('Location: index.php');
    exit;
}

// Fetch current brand status
$stmt = $pdo->prepare('SELECT id, name, is_active FROM brands WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$brand = $stmt->fetch();

if (!$brand) {
    set_flash('error', 'Brand not found.');
    header('Location: index.php');
    exit;
}

// Toggle status
$newStatus = $brand['is_active'] ? 0 : 1;
$updateStmt = $pdo->prepare('UPDATE brands SET is_active = :status WHERE id = :id');
$updateStmt->execute([
    ':status' => $newStatus,
    ':id'     => $id,
]);

$statusLabel = $newStatus ? 'activated' : 'deactivated';
set_flash('success', sprintf('Brand "%s" has been %s.', $brand['name'], $statusLabel));

header('Location: index.php');
exit;
