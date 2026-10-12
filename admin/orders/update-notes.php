<?php
/**
 * ATHLETICA — Admin Order Management: Update Admin Notes Handler
 *
 * Handles POST requests to update internal administrative notes on an order,
 * enforcing CSRF, authentication, length validation, and PRG redirects.
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

// 1. Reject non-POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// 2. Enforce CSRF token verification
if (!csrf_verify()) {
    set_flash('error', 'Security token invalid or expired. Please try again.');
    $orderId = (int)($_POST['order_id'] ?? 0);
    $redirect = ($orderId > 0) ? 'view.php?id=' . $orderId : 'index.php';
    header('Location: ' . $redirect);
    exit;
}

// 3. Collect and validate inputs
$orderId    = (int)($_POST['order_id'] ?? 0);
$adminNotes = trim($_POST['admin_notes'] ?? '');

if ($orderId <= 0) {
    set_flash('error', 'Invalid order ID.');
    header('Location: index.php');
    exit;
}

if (mb_strlen($adminNotes) > 2000) {
    set_flash('error', 'Admin notes cannot exceed 2000 characters.');
    header('Location: view.php?id=' . $orderId);
    exit;
}

// 4. Verify order exists
$stmt = $pdo->prepare("SELECT id FROM orders WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $orderId]);
if (!$stmt->fetch()) {
    set_flash('error', 'Order not found.');
    header('Location: index.php');
    exit;
}

// 5. Update Notes
$updateStmt = $pdo->prepare("UPDATE orders SET admin_notes = :notes WHERE id = :id");
$updateStmt->execute([
    ':notes' => ($adminNotes !== '') ? $adminNotes : null,
    ':id'    => $orderId,
]);

set_flash('success', 'Admin notes updated successfully.');
header('Location: view.php?id=' . $orderId);
exit;
