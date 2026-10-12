<?php
/**
 * ATHLETICA — Admin Order Management: Update Order Status Handler
 *
 * Handles POST requests to change order statuses according to the COD state machine rules,
 * enforcing CSRF, authentication, terminal state protection, and PRG redirects.
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
$orderId   = (int)($_POST['order_id'] ?? 0);
$newStatus = trim($_POST['status'] ?? '');

$allowedStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled', 'returned'];

if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
    set_flash('error', 'Invalid order status selected.');
    header('Location: ' . (($orderId > 0) ? 'view.php?id=' . $orderId : 'index.php'));
    exit;
}

// 4. Fetch current order record
$stmt = $pdo->prepare("SELECT id, order_number, status FROM orders WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    set_flash('error', 'Order not found.');
    header('Location: index.php');
    exit;
}

$currentStatus = $order['status'];

// 5. State Machine Validation
if ($currentStatus === 'delivered' && $newStatus !== 'delivered') {
    set_flash('error', 'Delivered orders are final and cannot be modified.');
    header('Location: view.php?id=' . $orderId);
    exit;
}

if ($currentStatus === 'cancelled' && in_array($newStatus, ['shipped', 'delivered'], true)) {
    set_flash('error', 'Cancelled orders cannot be directly marked as shipped or delivered.');
    header('Location: view.php?id=' . $orderId);
    exit;
}

if ($currentStatus === 'returned' && in_array($newStatus, ['shipped', 'delivered'], true)) {
    set_flash('error', 'Returned orders are closed and cannot be directly marked as shipped or delivered.');
    header('Location: view.php?id=' . $orderId);
    exit;
}

// 6. Update Status
$updateStmt = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
$updateStmt->execute([
    ':status' => $newStatus,
    ':id'     => $orderId,
]);

set_flash('success', sprintf('Order #%s status updated to "%s".', $order['order_number'], ucfirst($newStatus)));
header('Location: view.php?id=' . $orderId);
exit;
