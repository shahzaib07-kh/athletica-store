<?php
/**
 * ATHLETICA — API: Order Tracking / Lookup Endpoint
 * GET /api/orders/track.php?order_number=ATH-20260902-8419&phone=03001234567
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_method('GET');

$orderNumber = trim($_GET['order_number'] ?? '');
$phone = trim($_GET['phone'] ?? '');

if ($orderNumber === '') {
    api_error('Order number is required.', 'INVALID_PARAMETERS', 400);
}

try {
    $where = ["o.order_number = :order_num"];
    $params = [':order_num' => $orderNumber];

    if ($phone !== '') {
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        $where[] = "REPLACE(REPLACE(REPLACE(o.customer_phone, ' ', ''), '-', ''), '+', '') LIKE :phone";
        $params[':phone'] = '%' . substr($cleanPhone, -9); // Match trailing 9 digits
    }

    $whereSql = implode(' AND ', $where);

    $orderStmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.customer_phone, o.customer_email,
               o.city, o.delivery_address, o.subtotal, o.delivery_fee, o.total_amount,
               o.status, o.created_at
        FROM orders o
        WHERE {$whereSql}
        LIMIT 1
    ");
    $orderStmt->execute($params);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        api_error('Order not found matching the provided details.', 'NOT_FOUND', 404);
    }

    $itemsStmt = $pdo->prepare("
        SELECT id, product_id, product_name, product_price, product_size, product_color,
               quantity, line_total
        FROM order_items
        WHERE order_id = :oid
        ORDER BY id ASC
    ");
    $itemsStmt->execute([':oid' => (int)$order['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $settingsStmt = $pdo->query("SELECT whatsapp_number FROM site_settings LIMIT 1");
    $rawWa = $settingsStmt->fetchColumn() ?: '+923001234567';
    $normalizedWa = normalize_whatsapp_number((string)$rawWa);

    $inquiryMsg = sprintf(
        "Hello ATHLETICA, I am checking on the status of my Order #%s (%s, %s).",
        $order['order_number'],
        $order['customer_name'],
        $order['city']
    );
    $waUrl = "https://wa.me/{$normalizedWa}?text=" . rawurlencode($inquiryMsg);

    $data = [
        'order_number' => $order['order_number'],
        'status' => $order['status'],
        'created_at' => $order['created_at'],
        'customer' => [
            'name' => $order['customer_name'],
            'city' => $order['city'],
            'delivery_address' => $order['delivery_address'],
        ],
        'subtotal' => number_format((float)$order['subtotal'], 2, '.', ''),
        'delivery_fee' => number_format((float)$order['delivery_fee'], 2, '.', ''),
        'total_amount' => number_format((float)$order['total_amount'], 2, '.', ''),
        'payment_method' => 'Cash on Delivery (COD)',
        'items' => array_map(static function (array $item): array {
            return [
                'id' => (int)$item['id'],
                'product_id' => $item['product_id'] ? (int)$item['product_id'] : null,
                'name' => $item['product_name'],
                'size' => $item['product_size'],
                'color' => $item['product_color'],
                'price' => number_format((float)$item['product_price'], 2, '.', ''),
                'quantity' => (int)$item['quantity'],
                'line_total' => number_format((float)$item['line_total'], 2, '.', ''),
            ];
        }, $items ?: []),
        'whatsapp_support_url' => $waUrl,
    ];

    api_success($data);

} catch (PDOException $e) {
    api_error('Database error tracking order.', 'DB_ERROR', 500);
}
