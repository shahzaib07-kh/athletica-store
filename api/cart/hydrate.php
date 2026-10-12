<?php
/**
 * ATHLETICA — API: Cart Hydration & Live Price / Stock Verification
 * POST /api/cart/hydrate.php
 *
 * Input:
 * {
 *   "items": [
 *     { "product_id": 1, "size": "EU 41", "quantity": 1 }
 *   ]
 * }
 *
 * Revalidates every item from live MySQL database:
 * - products.price is source of truth (NEVER client price)
 * - product_sizes.is_available is checked in real time
 * - product_type == 'unique' locks quantity to 1
 * - product_type == 'regular' clamps quantity to [1, 5]
 * - Uses integer-cent precision (money_to_cents) to eliminate float drift
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_method('POST');

$input = api_get_json_input();
$rawItems = $input['items'] ?? [];

if (!is_array($rawItems)) {
    api_error('Invalid items list. Expected an array of cart items.', 'INVALID_PAYLOAD', 400);
}

try {
    $prodStmt = $pdo->prepare("
        SELECT p.id, p.category_id, p.brand_id, p.name, p.slug, p.sku, p.description,
               p.price, p.old_price, p.color, p.`condition`, p.product_type, p.status, p.advance_payment_percentage,
               c.name AS category_name, c.slug AS category_slug,
               b.name AS brand_name, b.slug AS brand_slug,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS primary_image
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE p.id = :id
        LIMIT 1
    ");

    $sizeStmt = $pdo->prepare("
        SELECT id, product_id, size, is_available, sort_order
        FROM product_sizes
        WHERE product_id = :pid AND LOWER(size) = LOWER(:size)
        LIMIT 1
    ");

    $hydratedItems = [];
    $subtotalCents = 0;
    $totalQuantity = 0;
    $hasUnavailableItems = false;

    foreach ($rawItems as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $size = trim((string)($item['size'] ?? ''));
        $quantity = max(1, (int)($item['quantity'] ?? 1));

        if ($productId <= 0 || $size === '') {
            continue;
        }

        $prodStmt->execute([':id' => $productId]);
        $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

        $sizeRecord = null;
        if ($product) {
            $sizeStmt->execute([':pid' => $productId, ':size' => $size]);
            $sizeRecord = $sizeStmt->fetch(PDO::FETCH_ASSOC);
        }

        $isAvailable = (
            $product &&
            $product['status'] === 'active' &&
            $sizeRecord &&
            (int)$sizeRecord['is_available'] === 1
        );

        $unavailableReason = null;
        if (!$isAvailable) {
            $hasUnavailableItems = true;
            if (!$product) {
                $unavailableReason = 'Product no longer exists';
            } elseif ($product['status'] === 'out_of_stock') {
                $unavailableReason = 'Product is currently out of stock';
            } elseif ($product['status'] !== 'active') {
                $unavailableReason = 'Product is currently unavailable';
            } elseif (!$sizeRecord) {
                $unavailableReason = 'Selected size is no longer offered';
            } elseif ((int)$sizeRecord['is_available'] !== 1) {
                $unavailableReason = 'Selected size is out of stock';
            } else {
                $unavailableReason = 'Item is unavailable';
            }
        }

        // Handle unique vs regular quantity constraints
        if ($product && $product['product_type'] === 'unique') {
            $quantity = 1;
        } else {
            $quantity = max(1, min(5, $quantity));
        }

        $pricing = $product 
            ? resolve_product_price($pdo, $productId, (float)$product['price'], !empty($product['old_price']) ? (float)$product['old_price'] : null)
            : ['effective_price' => 0.0, 'regular_price' => 0.0, 'sale_price' => null, 'is_flash_sale' => false, 'discount_percent' => 0];

        $unitPriceCents = money_to_cents((string)$pricing['effective_price']);
        $lineTotalCents = $unitPriceCents * $quantity;

        $itemAdvancePct = (isset($product['advance_payment_percentage']) && $product['advance_payment_percentage'] !== null) 
            ? (float)$product['advance_payment_percentage'] 
            : 5.0;

        if ($isAvailable) {
            $subtotalCents += $lineTotalCents;
            $totalQuantity += $quantity;
            if ($itemAdvancePct !== null && $itemAdvancePct > 0) {
                $advanceRequiredCents = (int)round(($lineTotalCents * $itemAdvancePct) / 100);
            }
        }

        $cartKey = cart_generate_key($productId, $sizeRecord['size'] ?? $size);

        $hydratedItems[] = [
            'cart_key' => $cartKey,
            'product_id' => $productId,
            'name' => $product['name'] ?? 'Unavailable Product',
            'slug' => $product['slug'] ?? '',
            'color' => $product['color'] ?? null,
            'condition' => $product['condition'] ?? 'new',
            'product_type' => $product['product_type'] ?? 'regular',
            'advance_payment_percentage' => $itemAdvancePct,
            'size' => $sizeRecord['size'] ?? $size,
            'size_id' => $sizeRecord ? (int)$sizeRecord['id'] : null,
            'quantity' => $quantity,
            'unit_price' => money_format_decimal($unitPriceCents),
            'regular_price' => money_format_decimal(money_to_cents((string)$pricing['regular_price'])),
            'sale_price' => $pricing['sale_price'] !== null ? money_format_decimal(money_to_cents((string)$pricing['sale_price'])) : null,
            'is_flash_sale' => $pricing['is_flash_sale'],
            'discount_percent' => $pricing['discount_percent'],
            'line_total' => money_format_decimal($lineTotalCents),
            'primary_image' => $product['primary_image'] ?? null,
            'category_name' => $product['category_name'] ?? null,
            'brand_name' => $product['brand_name'] ?? null,
            'is_available' => $isAvailable,
            'unavailable_reason' => $unavailableReason,
        ];
    }

    // Free delivery default: 0.00
    $settingsStmt = $pdo->query("SELECT delivery_fee_default FROM site_settings LIMIT 1");
    $rawFee = $settingsStmt->fetchColumn();
    $deliveryFeeCents = ($rawFee !== false && $rawFee !== null) ? money_to_cents($rawFee) : 0;
    $grandTotalCents = $subtotalCents + $deliveryFeeCents;

    // Calculate total advance payment across all available items
    $totalAdvanceCents = 0;
    foreach ($hydratedItems as $hItem) {
        if ($hItem['is_available'] && !empty($hItem['advance_payment_percentage']) && (float)$hItem['advance_payment_percentage'] > 0) {
            $itemLineCents = money_to_cents($hItem['line_total']);
            $totalAdvanceCents += (int)round(($itemLineCents * (float)$hItem['advance_payment_percentage']) / 100);
        }
    }
    $remainingCodCents = max(0, $grandTotalCents - $totalAdvanceCents);

    $data = [
        'items' => $hydratedItems,
        'subtotal' => money_format_decimal($subtotalCents),
        'delivery_fee' => money_format_decimal($deliveryFeeCents),
        'total_amount' => money_format_decimal($grandTotalCents),
        'advance_amount' => money_format_decimal($totalAdvanceCents),
        'remaining_cod_amount' => money_format_decimal($remainingCodCents),
        'has_advance_payment' => ($totalAdvanceCents > 0),
        'total_quantity' => $totalQuantity,
        'has_unavailable_items' => $hasUnavailableItems,
        'can_checkout' => (!empty($hydratedItems) && !$hasUnavailableItems && $subtotalCents > 0),
    ];

    api_success($data);

} catch (PDOException $e) {
    api_error('Database error hydrating cart.', 'DB_ERROR', 500);
}
