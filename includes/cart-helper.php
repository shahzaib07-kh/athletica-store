<?php
/**
 * ATHLETICA — Shopping Cart Helper Functions
 *
 * Provides functions for session-based cart management, deterministic item keys,
 * unique vs regular product quantity handling, live database hydration, and real-time
 * stock/availability verification.
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

/**
 * Generates a deterministic, unique key for a product and size combination.
 *
 * Example: 10 + "UK 9" -> "10_uk_9"
 *
 * @param int $productId Product ID
 * @param string $size Size label
 * @return string Deterministic cart key
 */
function cart_generate_key(int $productId, string $size): string
{
    $normalized = preg_replace('/[^a-zA-Z0-9]+/', '_', trim($size));
    $normalized = strtolower(trim($normalized, '_'));
    if ($normalized === '') {
        $normalized = 'default';
    }
    return (string)$productId . '_' . $normalized;
}

/**
 * Returns raw items array stored in session cart.
 *
 * @return array Session cart items or empty array
 */
function cart_get_items(): array
{
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        return $_SESSION['cart'];
    }
    return [];
}

/**
 * Counts total items in cart.
 *
 * @param bool $sumQuantities True to sum item quantities, false to count unique lines
 * @return int Total count
 */
function cart_count_items(bool $sumQuantities = true): int
{
    $items = cart_get_items();
    if (empty($items)) {
        return 0;
    }

    if ($sumQuantities) {
        $total = 0;
        foreach ($items as $item) {
            $total += (int)($item['quantity'] ?? 0);
        }
        return $total;
    }

    return count($items);
}

/**
 * Clears all items from the session cart.
 *
 * @return void
 */
function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

/**
 * Adds an item to the shopping cart with live stock/type validation.
 *
 * @param PDO $pdo Database connection
 * @param int $productId Product ID
 * @param string $size Selected size label
 * @param int $quantity Desired quantity (default: 1)
 * @return array ['success' => bool, 'message' => string, 'cart_key' => string, 'already_existed' => bool]
 */
function cart_add(PDO $pdo, int $productId, string $size, int $quantity = 1): array
{
    $size = trim($size);
    if ($productId <= 0 || $size === '') {
        return [
            'success' => false,
            'message' => 'Please select a valid product and size.',
            'cart_key' => '',
            'already_existed' => false,
        ];
    }

    // 1. Fetch product record
    $prodStmt = $pdo->prepare("
        SELECT id, name, slug, price, old_price, color, condition, product_type, status
        FROM products
        WHERE id = :id
        LIMIT 1
    ");
    $prodStmt->execute([':id' => $productId]);
    $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        return [
            'success' => false,
            'message' => 'Product not found.',
            'cart_key' => '',
            'already_existed' => false,
        ];
    }

    if ($product['status'] !== 'active') {
        $msg = ($product['status'] === 'out_of_stock')
            ? 'This product is currently out of stock.'
            : 'This product is not available for purchase.';
        return [
            'success' => false,
            'message' => $msg,
            'cart_key' => '',
            'already_existed' => false,
        ];
    }

    // 2. Fetch size record and check availability
    $sizeStmt = $pdo->prepare("
        SELECT id, product_id, size, is_available, sort_order
        FROM product_sizes
        WHERE product_id = :pid AND LOWER(size) = LOWER(:size)
        LIMIT 1
    ");
    $sizeStmt->execute([':pid' => $productId, ':size' => $size]);
    $sizeRecord = $sizeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sizeRecord) {
        return [
            'success' => false,
            'message' => 'The selected size option does not exist for this product.',
            'cart_key' => '',
            'already_existed' => false,
        ];
    }

    if ((int)$sizeRecord['is_available'] !== 1) {
        return [
            'success' => false,
            'message' => sprintf('Size "%s" is currently out of stock.', $sizeRecord['size']),
            'cart_key' => '',
            'already_existed' => false,
        ];
    }

    // 3. Generate deterministic cart key
    $cartKey = cart_generate_key($productId, $sizeRecord['size']);

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $exists = isset($_SESSION['cart'][$cartKey]);

    // 4. Handle product_type constraints
    if ($product['product_type'] === 'unique') {
        if ($exists) {
            return [
                'success' => true,
                'message' => sprintf('"%s" (Size: %s) is a unique single-pair item and is already in your cart.', $product['name'], $sizeRecord['size']),
                'cart_key' => $cartKey,
                'already_existed' => true,
            ];
        }
        $finalQty = 1;
    } else {
        // 'regular' multi-size product: increments quantity up to max 5
        $requestedQty = max(1, $quantity);
        if ($exists) {
            $currentQty = (int)($_SESSION['cart'][$cartKey]['quantity'] ?? 0);
            $finalQty = min(5, $currentQty + $requestedQty);
        } else {
            $finalQty = min(5, $requestedQty);
        }
    }

    // 5. Save to session
    $_SESSION['cart'][$cartKey] = [
        'cart_key'   => $cartKey,
        'product_id' => $productId,
        'size'       => $sizeRecord['size'],
        'quantity'   => $finalQty,
        'added_at'   => $_SESSION['cart'][$cartKey]['added_at'] ?? time(),
    ];

    return [
        'success'         => true,
        'message'         => sprintf('"%s" (Size: %s) has been added to your cart.', $product['name'], $sizeRecord['size']),
        'cart_key'        => $cartKey,
        'already_existed' => $exists,
    ];
}

/**
 * Updates the quantity of a specific item in the cart.
 *
 * @param PDO $pdo Database connection
 * @param string $cartKey Cart item key
 * @param int $quantity New quantity
 * @return array ['success' => bool, 'message' => string]
 */
function cart_update_quantity(PDO $pdo, string $cartKey, int $quantity): array
{
    $cartKey = trim($cartKey);
    if (!isset($_SESSION['cart'][$cartKey])) {
        return [
            'success' => false,
            'message' => 'Cart item not found.',
        ];
    }

    // If quantity <= 0, remove item
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$cartKey]);
        return [
            'success' => true,
            'message' => 'Item removed from your cart.',
        ];
    }

    // Check product type for unique locking
    $productId = (int)($_SESSION['cart'][$cartKey]['product_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT product_type, name FROM products WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product && $product['product_type'] === 'unique') {
        $_SESSION['cart'][$cartKey]['quantity'] = 1;
        return [
            'success' => true,
            'message' => 'Quantity for unique single-pair items is locked to 1.',
        ];
    }

    // Clamp regular items between 1 and 5
    $newQty = max(1, min(5, $quantity));
    $_SESSION['cart'][$cartKey]['quantity'] = $newQty;

    return [
        'success' => true,
        'message' => 'Cart quantity updated successfully.',
    ];
}

/**
 * Removes an item from the cart.
 *
 * @param string $cartKey Cart item key
 * @return bool True if removed, false if not found
 */
function cart_remove(string $cartKey): bool
{
    $cartKey = trim($cartKey);
    if (isset($_SESSION['cart'][$cartKey])) {
        unset($_SESSION['cart'][$cartKey]);
        return true;
    }
    return false;
}

/**
 * Safely converts a monetary value (string, float, or int) into integer cents/paisa.
 * Avoids binary floating-point drift when performing calculations.
 *
 * @param mixed $amount
 * @return int Amount in integer cents
 */
function money_to_cents($amount): int
{
    if ($amount === null || $amount === '') {
        return 0;
    }
    $clean = trim((string)$amount);
    return (int)round(((float)$clean) * 100);
}

/**
 * Formats integer cents/paisa into a standard 2-decimal string format for DECIMAL(10,2).
 *
 * @param int $cents
 * @return string Formatted decimal string (e.g., "7000.00")
 */
function money_format_decimal(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

/**
 * Hydrates session cart items with live database details, stock checks, and exact money totals.
 *
 * All money arithmetic (unit price, line total, subtotal, delivery fee, grand total)
 * is executed using integer-cent precision to eliminate floating-point drift.
 *
 * @param PDO $pdo Database connection
 * @return array Hydrated cart summary array
 */
function cart_hydrate(PDO $pdo): array
{
    $items = cart_get_items();
    $hydratedItems = [];
    $subtotalCents = 0;
    $totalQuantity = 0;
    $hasUnavailableItems = false;

    if (!empty($items)) {
        $prodStmt = $pdo->prepare("
            SELECT p.id, p.category_id, p.brand_id, p.name, p.slug, p.sku, p.description,
                   p.price, p.old_price, p.color, p.condition, p.product_type, p.status,
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

        foreach ($items as $cartKey => $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $size = (string)($item['size'] ?? '');
            $quantity = max(1, (int)($item['quantity'] ?? 1));

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

            $unavailableReason = '';
            if (!$isAvailable) {
                $hasUnavailableItems = true;
                if (!$product) {
                    $unavailableReason = 'Product is no longer available';
                } elseif ($product['status'] === 'out_of_stock') {
                    $unavailableReason = 'Product is out of stock';
                } elseif ($product['status'] !== 'active') {
                    $unavailableReason = 'Product is currently inactive';
                } elseif (!$sizeRecord) {
                    $unavailableReason = 'Selected size is no longer offered';
                } elseif ((int)$sizeRecord['is_available'] !== 1) {
                    $unavailableReason = 'Selected size is out of stock';
                } else {
                    $unavailableReason = 'Item is currently unavailable';
                }
            }

            // Lock unique products to quantity 1
            if ($product && $product['product_type'] === 'unique' && $quantity > 1) {
                $quantity = 1;
                $_SESSION['cart'][$cartKey]['quantity'] = 1;
            }

            // Exact integer-cent money calculations
            $unitPriceCents = $product ? money_to_cents($product['price']) : 0;
            $oldPriceCents = ($product && isset($product['old_price']) && $product['old_price'] !== null)
                ? money_to_cents($product['old_price'])
                : null;
            $lineTotalCents = $isAvailable ? ($unitPriceCents * $quantity) : 0;

            if ($isAvailable) {
                $subtotalCents += $lineTotalCents;
                $totalQuantity += $quantity;
            }

            $hydratedItems[$cartKey] = [
                'cart_key'           => $cartKey,
                'product_id'         => $productId,
                'size'               => $sizeRecord ? $sizeRecord['size'] : $size,
                'quantity'           => $quantity,
                'added_at'           => $item['added_at'] ?? time(),
                'product'            => $product ?: null,
                'name'               => $product['name'] ?? 'Product Removed',
                'slug'               => $product['slug'] ?? '',
                'sku'                => $product['sku'] ?? null,
                'unit_price'         => money_format_decimal($unitPriceCents),
                'unit_price_cents'   => $unitPriceCents,
                'old_price'          => ($oldPriceCents !== null) ? money_format_decimal($oldPriceCents) : null,
                'color'              => $product['color'] ?? null,
                'condition'          => $product['condition'] ?? 'new',
                'product_type'       => $product['product_type'] ?? 'regular',
                'status'             => $product['status'] ?? 'inactive',
                'brand_name'         => $product['brand_name'] ?? null,
                'brand_slug'         => $product['brand_slug'] ?? null,
                'category_name'      => $product['category_name'] ?? null,
                'category_slug'      => $product['category_slug'] ?? null,
                'primary_image'      => !empty($product['primary_image']) ? $product['primary_image'] : 'assets/images/placeholder-product.jpg',
                'size_record'        => $sizeRecord ?: null,
                'is_available'       => $isAvailable,
                'unavailable_reason' => $unavailableReason,
                'line_total'         => money_format_decimal($lineTotalCents),
                'line_total_cents'   => $lineTotalCents,
            ];
        }
    }

    $deliveryFeeCents = 0;
    try {
        $settingsStmt = $pdo->query("SELECT delivery_fee_default FROM site_settings LIMIT 1");
        if ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
            $deliveryFeeCents = money_to_cents($row['delivery_fee_default'] ?? 0);
        }
    } catch (PDOException $e) {
        $deliveryFeeCents = 0;
    }

    $grandTotalCents = $subtotalCents + $deliveryFeeCents;

    return [
        'items'                 => $hydratedItems,
        'subtotal'              => money_format_decimal($subtotalCents),
        'subtotal_cents'        => $subtotalCents,
        'delivery_fee'          => money_format_decimal($deliveryFeeCents),
        'delivery_fee_cents'    => $deliveryFeeCents,
        'grand_total'           => money_format_decimal($grandTotalCents),
        'grand_total_cents'     => $grandTotalCents,
        'total_quantity'        => $totalQuantity,
        'total_lines'           => count($hydratedItems),
        'has_unavailable_items' => $hasUnavailableItems,
        'is_empty'              => empty($hydratedItems),
    ];
}

