<?php
/**
 * ATHLETICA — API: COD Checkout & Order Creation Endpoint
 * POST /api/checkout.php
 *
 * Atomically creates a Cash on Delivery order:
 * 1. Validates customer information and phone format.
 * 2. Begins a MySQL transaction.
 * 3. Atomically claims unique product sizes (UPDATE product_sizes SET is_available = 0).
 * 4. Verifies regular product availability.
 * 5. Re-computes trusted prices from products.price using integer-cent arithmetic.
 * 6. Inserts immutable snapshot rows into orders and order_items.
 * 7. Commits transaction and generates customer WhatsApp deep link.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_require_method('POST');

$input = api_get_json_input();
$customer = $input['customer'] ?? [];
$rawItems = $input['items'] ?? [];

// 1. Validate Customer Fields
$errors = [];
$name = trim((string)($customer['name'] ?? ''));
$phone = trim((string)($customer['phone'] ?? ''));
$email = trim((string)($customer['email'] ?? ''));
$city = trim((string)($customer['city'] ?? ''));
$address = trim((string)($customer['delivery_address'] ?? ''));

if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors['customer.name'] = 'Please enter your full name (2–100 characters).';
}

$cleanDigits = preg_replace('/[^\d]/', '', $phone);
if ($phone === '' || strlen($cleanDigits) < 10 || strlen($cleanDigits) > 13) {
    $errors['customer.phone'] = 'Please enter a valid Pakistani mobile/WhatsApp number (e.g. 03001234567).';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['customer.email'] = 'Please enter a valid email address or leave blank.';
}

if ($city === '' || mb_strlen($city) < 2 || mb_strlen($city) > 100) {
    $errors['customer.city'] = 'Please enter your delivery city.';
}

if ($address === '' || mb_strlen($address) < 5) {
    $errors['customer.delivery_address'] = 'Please provide a complete delivery street address.';
}

if (!is_array($rawItems) || empty($rawItems)) {
    $errors['items'] = 'Your cart is empty. Please add items before checking out.';
}

if (!empty($errors)) {
    api_error('Validation failed. Please correct the highlighted fields.', 'VALIDATION_ERROR', 422, $errors);
}

// 2. Process Order inside ACID Database Transaction
try {
    $pdo->beginTransaction();

    $prodStmt = $pdo->prepare("
        SELECT id, name, slug, price, old_price, color, `condition`, product_type, status
        FROM products
        WHERE id = :id
        LIMIT 1
    ");

    $sizeStmt = $pdo->prepare("
        SELECT id, product_id, size, is_available, sort_order
        FROM product_sizes
        WHERE product_id = :pid AND LOWER(size) = LOWER(:size)
        LIMIT 1
    ");

    $claimUniqueStmt = $pdo->prepare("
        UPDATE product_sizes
        SET is_available = 0
        WHERE id = :id AND product_id = :pid AND is_available = 1
    ");

    $orderItemsToInsert = [];
    $subtotalCents = 0;

    foreach ($rawItems as $idx => $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $size = trim((string)($item['size'] ?? ''));
        $quantity = max(1, (int)($item['quantity'] ?? 1));

        if ($productId <= 0 || $size === '') {
            $pdo->rollBack();
            api_error(
                "Invalid item at index {$idx}.",
                'INVALID_CART_ITEM',
                400
            );
        }

        $prodStmt->execute([':id' => $productId]);
        $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || $product['status'] !== 'active') {
            $pdo->rollBack();
            $pName = $product['name'] ?? "Product #{$productId}";
            api_error(
                "\"{$pName}\" is no longer available for purchase.",
                'PRODUCT_UNAVAILABLE',
                422,
                ["items[{$idx}]" => 'Product inactive or out of stock']
            );
        }

        $sizeStmt->execute([':pid' => $productId, ':size' => $size]);
        $sizeRecord = $sizeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sizeRecord) {
            $pdo->rollBack();
            api_error(
                "Size \"{$size}\" is no longer offered for \"{$product['name']}\".",
                'SIZE_NOT_FOUND',
                422,
                ["items[{$idx}].size" => 'Size option does not exist']
            );
        }

        if ((int)$sizeRecord['is_available'] !== 1) {
            $pdo->rollBack();
            api_error(
                "\"{$product['name']}\" (Size: {$sizeRecord['size']}) is currently out of stock.",
                'SIZE_OUT_OF_STOCK',
                422,
                ["items[{$idx}].size" => 'Size is out of stock']
            );
        }

        // Handle unique single-pair items (Atomic Claim)
        if ($product['product_type'] === 'unique') {
            $quantity = 1;
            $claimUniqueStmt->execute([
                ':id' => $sizeRecord['id'],
                ':pid' => $productId,
            ]);

            // If 0 rows affected, another concurrent buyer already claimed this unique item
            if ($claimUniqueStmt->rowCount() !== 1) {
                $pdo->rollBack();
                api_error(
                    "\"{$product['name']}\" (Size: {$sizeRecord['size']}) is a unique single-pair item and was just claimed by another customer.",
                    'UNIQUE_ITEM_ALREADY_CLAIMED',
                    409,
                    ["items[{$idx}]" => 'Unique inventory claim collision']
                );
            }
        } else {
            // Regular items: clamp quantity [1, 5]
            $quantity = max(1, min(5, $quantity));
        }

        // Enforce server-side pricing authority (Flash Sale or Regular)
        $pricing = resolve_product_price(
            $pdo, 
            $productId, 
            (float)$product['price'], 
            !empty($product['old_price']) ? (float)$product['old_price'] : null
        );

        $unitPriceCents = money_to_cents((string)$pricing['effective_price']);
        $lineTotalCents = $unitPriceCents * $quantity;
        $subtotalCents += $lineTotalCents;

        $orderItemsToInsert[] = [
            'product_id' => $productId,
            'product_name' => $product['name'],
            'product_price' => money_format_decimal($unitPriceCents),
            'product_size' => $sizeRecord['size'],
            'product_color' => $product['color'] ?? null,
            'quantity' => $quantity,
            'line_total' => money_format_decimal($lineTotalCents),
        ];
    }

    if (empty($orderItemsToInsert) || $subtotalCents <= 0) {
        $pdo->rollBack();
        api_error('Cannot create order with an empty total.', 'EMPTY_ORDER', 400);
    }

    // Delivery fee default: 0.00 (FREE)
    $settingsStmt = $pdo->query("SELECT delivery_fee_default, whatsapp_number FROM site_settings LIMIT 1");
    $settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $rawFee = $settingsRow['delivery_fee_default'] ?? 0.00;
    $deliveryFeeCents = money_to_cents($rawFee);
    $grandTotalCents = $subtotalCents + $deliveryFeeCents;

    // Generate collision-resistant order number
    $orderNumber = generate_order_number($pdo);

    // Extract advance_status (confirmed vs cancelled draft lead)
    $advanceStatus = trim((string)($input['advance_status'] ?? 'confirmed'));
    if (!in_array($advanceStatus, ['confirmed', 'cancelled', 'pending'], true)) {
        $advanceStatus = 'confirmed';
    }

    // 3. INSERT INTO orders table
    $orderInsertStmt = $pdo->prepare("
        INSERT INTO orders (
            order_number, customer_name, customer_phone, customer_email,
            city, delivery_address, subtotal, delivery_fee, total_amount, status, advance_status
        ) VALUES (
            :order_num, :c_name, :c_phone, :c_email,
            :city, :addr, :subtotal, :delivery_fee, :total_amt, 'pending', :adv_status
        )
    ");

    $orderInsertStmt->execute([
        ':order_num' => $orderNumber,
        ':c_name' => $name,
        ':c_phone' => $phone,
        ':c_email' => $email !== '' ? $email : null,
        ':city' => $city,
        ':addr' => $address,
        ':subtotal' => money_format_decimal($subtotalCents),
        ':delivery_fee' => money_format_decimal($deliveryFeeCents),
        ':total_amt' => money_format_decimal($grandTotalCents),
        ':adv_status' => $advanceStatus,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // 4. INSERT INTO order_items snapshot table
    $itemInsertStmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id, product_id, product_name, product_price,
            product_size, product_color, quantity, line_total
        ) VALUES (
            :oid, :pid, :pname, :pprice,
            :psize, :pcolor, :qty, :ltotal
        )
    ");

    foreach ($orderItemsToInsert as $oItem) {
        $itemInsertStmt->execute([
            ':oid' => $orderId,
            ':pid' => $oItem['product_id'],
            ':pname' => $oItem['product_name'],
            ':pprice' => $oItem['product_price'],
            ':psize' => $oItem['product_size'],
            ':pcolor' => $oItem['product_color'],
            ':qty' => $oItem['quantity'],
            ':ltotal' => $oItem['line_total'],
        ]);
    }

    $pdo->commit();

    // 5. Build WhatsApp Deep Link & Message
    $orderRecord = [
        'order_number' => $orderNumber,
        'customer_name' => $name,
        'customer_phone' => $phone,
        'city' => $city,
        'delivery_address' => $address,
        'subtotal' => money_format_decimal($subtotalCents),
        'delivery_fee' => money_format_decimal($deliveryFeeCents),
        'total_amount' => money_format_decimal($grandTotalCents),
    ];

    $rawWa = $settingsRow['whatsapp_number'] ?? '+923001234567';
    $normalizedWa = normalize_whatsapp_number((string)$rawWa);
    $waMessage = build_customer_order_whatsapp_message($orderRecord, $orderItemsToInsert);
    $waUrl = "https://wa.me/{$normalizedWa}?text=" . rawurlencode($waMessage);

    // Also clear PHP session cart if one was active
    if (function_exists('cart_clear')) {
        cart_clear();
    }

    api_success([
        'order_number' => $orderNumber,
        'customer' => [
            'name' => $name,
            'phone' => $phone,
            'email' => $email !== '' ? $email : null,
            'city' => $city,
            'delivery_address' => $address,
        ],
        'subtotal' => money_format_decimal($subtotalCents),
        'delivery_fee' => money_format_decimal($deliveryFeeCents),
        'total_amount' => money_format_decimal($grandTotalCents),
        'payment_method' => 'Cash on Delivery (COD)',
        'items' => $orderItemsToInsert,
        'whatsapp' => [
            'number' => $normalizedWa,
            'direct_url' => $waUrl,
            'prefilled_message' => $waMessage,
        ],
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_error('Failed to process checkout transaction: ' . $e->getMessage(), 'TRANSACTION_ERROR', 500);
}
