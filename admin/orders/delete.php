<?php
/**
 * ATHLETICA — Admin: Delete Order Handler
 * POST /admin/orders/delete.php
 *
 * Atomically deletes an order and its associated order_items snapshots.
 */

declare(strict_types=1);

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!csrf_verify()) {
    set_flash('error', 'Security token invalid or expired. Please try again.');
    header('Location: index.php');
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    set_flash('error', 'Invalid order ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, order_number FROM orders WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    set_flash('error', 'Order not found or already deleted.');
    header('Location: index.php');
    exit;
}

$orderNumber = (string)$order['order_number'];

try {
    $pdo->beginTransaction();

    // Delete order items first (due to ON DELETE RESTRICT on FK)
    $delItems = $pdo->prepare("DELETE FROM order_items WHERE order_id = :oid");
    $delItems->execute([':oid' => $orderId]);

    // Delete the order record
    $delOrder = $pdo->prepare("DELETE FROM orders WHERE id = :oid");
    $delOrder->execute([':oid' => $orderId]);

    $pdo->commit();

    set_flash('success', sprintf('Order #%s was permanently deleted.', $orderNumber));
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Failed to delete order: ' . $e->getMessage());
}

header('Location: index.php');
exit;
