<?php
/**
 * ATHLETICA — API: Orders Endpoint
 * Supports DELETE and GET methods for order management.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'DELETE') {
    // 1. Determine target order ID or order_number
    $input = api_get_json_input();
    $orderId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($input['id'] ?? 0);
    $orderNumber = trim((string)($_GET['order_number'] ?? ($input['order_number'] ?? '')));

    if ($orderId <= 0 && $orderNumber === '') {
        api_error('Order ID or Order Number is required for deletion.', 'INVALID_PARAMETERS', 400);
    }

    // 2. Fetch order to verify existence and get ID & order_number
    if ($orderId > 0) {
        $stmt = $pdo->prepare("SELECT id, order_number FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $orderId]);
    } else {
        $stmt = $pdo->prepare("SELECT id, order_number FROM orders WHERE order_number = :onum LIMIT 1");
        $stmt->execute([':onum' => $orderNumber]);
    }

    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        api_error('Order not found.', 'ORDER_NOT_FOUND', 404);
    }

    $targetId = (int)$order['id'];
    $targetNum = (string)$order['order_number'];

    // 3. Purge order records atomically
    try {
        $pdo->beginTransaction();

        // Delete order items first (due to ON DELETE RESTRICT on foreign key)
        $delItems = $pdo->prepare("DELETE FROM order_items WHERE order_id = :oid");
        $delItems->execute([':oid' => $targetId]);
        $itemsDeleted = $delItems->rowCount();

        // Delete the order record
        $delOrder = $pdo->prepare("DELETE FROM orders WHERE id = :oid");
        $delOrder->execute([':oid' => $targetId]);

        $pdo->commit();

        api_success([
            'deleted' => true,
            'order_id' => $targetId,
            'order_number' => $targetNum,
            'items_deleted' => $itemsDeleted,
            'message' => "Order {$targetNum} deleted successfully.",
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_error('Failed to delete order: ' . $e->getMessage(), 'DATABASE_ERROR', 500);
    }
} elseif ($method === 'GET') {
    $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $orderNumber = trim((string)($_GET['order_number'] ?? ''));

    if ($orderId > 0 || $orderNumber !== '') {
        if ($orderId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $orderId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = :onum LIMIT 1");
            $stmt->execute([':onum' => $orderNumber]);
        }
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            api_error('Order not found.', 'ORDER_NOT_FOUND', 404);
        }

        $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC");
        $itemsStmt->execute([':oid' => (int)$order['id']]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        api_success($order);
    } else {
        // List recent orders
        $stmt = $pdo->query("SELECT id, order_number, customer_name, customer_phone, city, total_amount, status, advance_status, created_at FROM orders ORDER BY created_at DESC LIMIT 50");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        api_success(['orders' => $orders]);
    }
} else {
    api_error("Method {$method} not allowed.", 'METHOD_NOT_ALLOWED', 405);
}
