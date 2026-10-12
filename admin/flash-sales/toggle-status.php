<?php
/**
 * ATHLETICA — Admin: Toggle Flash Sale Status
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!csrf_verify()) {
    set_flash('error', 'Invalid security token.');
    header('Location: index.php');
    exit;
}

$saleId = (int)($_POST['sale_id'] ?? 0);
if ($saleId <= 0) {
    set_flash('error', 'Invalid flash sale ID.');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE flash_sales SET is_active = NOT is_active WHERE id = :id");
    $stmt->execute([':id' => $saleId]);
    set_flash('success', 'Flash sale status updated.');
} catch (PDOException $e) {
    set_flash('error', 'Database error: ' . $e->getMessage());
}

header('Location: index.php');
exit;
