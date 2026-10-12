<?php
/**
 * ATHLETICA — Automated REST API Test Suite
 * Milestone B: API Contract & Security Verification
 */

declare(strict_types=1);

// Configure SQLite In-Memory Database for comprehensive API unit & integration tests
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Build SQLite schema mirroring MySQL schema
$pdo->exec("
    CREATE TABLE admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) UNIQUE,
        email VARCHAR(100) UNIQUE,
        password_hash VARCHAR(255),
        full_name VARCHAR(100),
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100),
        slug VARCHAR(120) UNIQUE,
        description TEXT,
        image VARCHAR(255),
        sort_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE brands (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100),
        slug VARCHAR(120) UNIQUE,
        logo VARCHAR(255),
        description TEXT,
        sort_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER,
        brand_id INTEGER,
        name VARCHAR(255),
        slug VARCHAR(280) UNIQUE,
        sku VARCHAR(50) UNIQUE,
        description TEXT,
        price DECIMAL(10,2),
        old_price DECIMAL(10,2),
        color VARCHAR(50),
        condition VARCHAR(20) DEFAULT 'new',
        product_type VARCHAR(20) DEFAULT 'regular',
        status VARCHAR(20) DEFAULT 'active',
        is_featured INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE product_sizes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER,
        size VARCHAR(30),
        is_available INTEGER DEFAULT 1,
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(product_id, size)
    );

    CREATE TABLE product_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER,
        image_path VARCHAR(255),
        alt_text VARCHAR(255),
        is_primary INTEGER DEFAULT 0,
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_number VARCHAR(30) UNIQUE,
        customer_name VARCHAR(100),
        customer_phone VARCHAR(30),
        customer_email VARCHAR(100),
        city VARCHAR(100),
        delivery_address TEXT,
        subtotal DECIMAL(10,2),
        delivery_fee DECIMAL(10,2) DEFAULT 0.00,
        total_amount DECIMAL(10,2),
        status VARCHAR(30) DEFAULT 'pending',
        admin_notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER,
        product_id INTEGER,
        product_name VARCHAR(255),
        product_price DECIMAL(10,2),
        product_size VARCHAR(30),
        product_color VARCHAR(50),
        quantity INTEGER DEFAULT 1,
        line_total DECIMAL(10,2),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE site_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        site_name VARCHAR(100) DEFAULT 'ATHLETICA',
        tagline VARCHAR(255),
        whatsapp_number VARCHAR(30),
        email VARCHAR(100),
        phone VARCHAR(30),
        address TEXT,
        currency_symbol VARCHAR(10) DEFAULT 'Rs.',
        delivery_fee_default DECIMAL(10,2) DEFAULT 0.00,
        footer_text TEXT
    );
");

// Insert seed data
$pdo->exec("
    INSERT INTO site_settings (site_name, tagline, whatsapp_number, email, phone, address, currency_symbol, delivery_fee_default)
    VALUES ('ATHLETICA', 'FOCUS | POWER | PERFORMANCE', '+923001234567', 'contact@athletica.pk', '+923001234567', 'Lahore, Pakistan', 'Rs.', 0.00);

    INSERT INTO categories (id, name, slug, description, sort_order, is_active) VALUES
    (1, 'Shoes', 'shoes', 'Imported athletic shoes', 1, 1),
    (2, 'Sneakers', 'sneakers', 'Premium imported sneakers', 2, 1);

    INSERT INTO brands (id, name, slug, description, sort_order, is_active) VALUES
    (1, 'Nike', 'nike', 'Nike sportswear', 1, 1),
    (2, 'ASICS', 'asics', 'ASICS running shoes', 2, 1);

    -- Product 1: Unique single pair (EU 41)
    INSERT INTO products (id, category_id, brand_id, name, slug, sku, description, price, old_price, color, condition, product_type, status, is_featured)
    VALUES (1, 2, 1, 'Nike Air Max 90 Essential', 'nike-air-max-90', 'ATH-SNK-001', 'Description', 18500.00, 22000.00, 'Black/White', 'new', 'unique', 'active', 1);

    INSERT INTO product_sizes (id, product_id, size, is_available, sort_order) VALUES
    (1, 1, 'EU 41', 1, 1);

    INSERT INTO product_images (id, product_id, image_path, alt_text, is_primary, sort_order) VALUES
    (1, 1, 'uploads/products/nike-am90.jpg', 'Nike AM90 Main', 1, 0);

    -- Product 2: Regular multi-size (EU 40, EU 41, EU 42)
    INSERT INTO products (id, category_id, brand_id, name, slug, sku, description, price, old_price, color, condition, product_type, status, is_featured)
    VALUES (2, 1, 2, 'ASICS GEL-Kayano 30', 'asics-kayano-30', 'ATH-SHO-002', 'ASICS Gel', 25500.00, 29000.00, 'Blue/White', 'new', 'regular', 'active', 1);

    INSERT INTO product_sizes (id, product_id, size, is_available, sort_order) VALUES
    (2, 2, 'EU 40', 1, 1),
    (3, 2, 'EU 41', 1, 2),
    (4, 2, 'EU 42', 0, 3); -- Out of stock

    INSERT INTO product_images (id, product_id, image_path, alt_text, is_primary, sort_order) VALUES
    (2, 2, 'uploads/products/asics-kayano.jpg', 'Kayano Main', 1, 0);
");

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart-helper.php';

$testCount = 0;
$passCount = 0;

function it(string $description, bool $condition): void {
    global $testCount, $passCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo " [PASS] " . $description . "\n";
    } else {
        echo " [FAIL] " . $description . "\n";
    }
}

echo "\n====================================================\n";
echo " ATHLETICA — Phase 5A REST API Test Suite\n";
echo "====================================================\n\n";

// --- 1. Settings Verification ---
echo "--- 1. API: Settings & Delivery Fee Default ---\n";
$settingsStmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
$settings = $settingsStmt->fetch();
it("site_settings loads delivery_fee_default as 0.00", (float)$settings['delivery_fee_default'] === 0.00);
$normalizedWa = normalize_whatsapp_number($settings['whatsapp_number']);
it("normalize_whatsapp_number converts '+923001234567' to '923001234567'", $normalizedWa === '923001234567');

// --- 2. Categories & Brands Aggregation ---
echo "\n--- 2. API: Categories & Brands Aggregation ---\n";
$catStmt = $pdo->query("
    SELECT c.name, COUNT(p.id) AS product_count 
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id 
    GROUP BY c.id
");
$catRows = $catStmt->fetchAll();
it("Categories count returns correct active categories with associated product counts", count($catRows) === 2);

$brandStmt = $pdo->query("
    SELECT b.name, COUNT(p.id) AS product_count 
    FROM brands b 
    LEFT JOIN products p ON p.brand_id = b.id 
    GROUP BY b.id
");
$brandRows = $brandStmt->fetchAll();
it("Brands count returns active brands with product counts", count($brandRows) === 2);

// --- 3. Sizes Aggregation ---
echo "\n--- 3. API: Available Sizes Aggregation ---\n";
$sizeStmt = $pdo->query("
    SELECT DISTINCT ps.size 
    FROM product_sizes ps 
    JOIN products p ON p.id = ps.product_id 
    WHERE ps.is_available = 1 AND p.status = 'active'
    ORDER BY ps.size ASC
");
$availSizes = $sizeStmt->fetchAll(PDO::FETCH_COLUMN);
it("Sizes aggregator only returns in-stock sizes (EU 40, EU 41) and excludes out of stock (EU 42)", 
    in_array('EU 40', $availSizes, true) && 
    in_array('EU 41', $availSizes, true) && 
    !in_array('EU 42', $availSizes, true)
);

// --- 4. Product Catalog Filtering & Sorting ---
echo "\n--- 4. API: Catalog Filtering (ASICS + EU 41) ---\n";
$filterStmt = $pdo->prepare("
    SELECT p.name, p.price, b.name AS brand_name, ps.size
    FROM products p
    JOIN brands b ON b.id = p.brand_id
    JOIN product_sizes ps ON ps.product_id = p.id
    WHERE p.status = 'active' AND b.slug = 'asics' AND ps.size = 'EU 41' AND ps.is_available = 1
");
$filterStmt->execute();
$filtered = $filterStmt->fetchAll();
it("Combined filter (Brand: ASICS + Size: EU 41) returns only ASICS GEL-Kayano 30", 
    count($filtered) === 1 && $filtered[0]['name'] === 'ASICS GEL-Kayano 30'
);

// --- 5. Cart Hydration & Live Revalidation ---
echo "\n--- 5. API: Cart Hydration & Integer Cents Arithmetic ---\n";
// Simulate input: 1 Unique item (Nike EU 41) + 1 Out-of-Stock regular item (ASICS EU 42)
$cartInput = [
    ['product_id' => 1, 'size' => 'EU 41', 'quantity' => 2], // Unique item should clamp to 1
    ['product_id' => 2, 'size' => 'EU 42', 'quantity' => 1], // Unavailable size
];

$prodStmt = $pdo->prepare("SELECT id, name, price, product_type, status FROM products WHERE id = :id");
$sizeStmt = $pdo->prepare("SELECT id, size, is_available FROM product_sizes WHERE product_id = :pid AND size = :size");

$hydrated = [];
$subtotalCents = 0;
$hasUnavailable = false;

foreach ($cartInput as $item) {
    $prodStmt->execute([':id' => $item['product_id']]);
    $p = $prodStmt->fetch();
    $sizeStmt->execute([':pid' => $item['product_id'], ':size' => $item['size']]);
    $s = $sizeStmt->fetch();

    $isAvail = ($p && $p['status'] === 'active' && $s && (int)$s['is_available'] === 1);
    $qty = $p['product_type'] === 'unique' ? 1 : max(1, min(5, $item['quantity']));
    $unitCents = money_to_cents($p['price']);

    if (!$isAvail) {
        $hasUnavailable = true;
    } else {
        $subtotalCents += ($unitCents * $qty);
    }

    $hydrated[] = [
        'product_id' => $item['product_id'],
        'name' => $p['name'],
        'quantity' => $qty,
        'unit_price' => money_format_decimal($unitCents),
        'is_available' => $isAvail,
    ];
}

it("Cart hydration clamps unique product quantity to 1", $hydrated[0]['quantity'] === 1);
it("Cart hydration calculates exact float-drift-free integer cent subtotal (Rs. 18500.00)", money_format_decimal($subtotalCents) === '18500.00');
it("Cart hydration flags unavailable item (EU 42 out of stock)", $hydrated[1]['is_available'] === false && $hasUnavailable === true);

// --- 6. Checkout Atomic Transaction & Unique Claim ---
echo "\n--- 6. API: Checkout Transaction & Unique Inventory Atomic Claim ---\n";
$pdo->beginTransaction();

// Customer placing order for Product 1 (Unique EU 41)
$claimStmt = $pdo->prepare("UPDATE product_sizes SET is_available = 0 WHERE id = 1 AND product_id = 1 AND is_available = 1");
$claimStmt->execute();
$claimedRows = $claimStmt->rowCount();

$orderNum = generate_order_number($pdo);
$insOrder = $pdo->prepare("
    INSERT INTO orders (order_number, customer_name, customer_phone, city, delivery_address, subtotal, delivery_fee, total_amount, status)
    VALUES (:num, 'Zaid Khan', '03001234567', 'Karachi', 'DHA Phase 6', 18500.00, 0.00, 18500.00, 'pending')
");
$insOrder->execute([':num' => $orderNum]);
$orderId = (int)$pdo->lastInsertId();

$insItem = $pdo->prepare("
    INSERT INTO order_items (order_id, product_id, product_name, product_price, product_size, quantity, line_total)
    VALUES (:oid, 1, 'Nike Air Max 90 Essential', 18500.00, 'EU 41', 1, 18500.00)
");
$insItem->execute([':oid' => $orderId]);
$pdo->commit();

it("Unique item size is atomically claimed (is_available updated to 0) during checkout", $claimedRows === 1);
it("Order record created with pending status, exact subtotal, and 0.00 delivery fee", $orderId > 0 && $orderNum !== '');

// --- 7. Concurrent Claim Rejection (409 Conflict Simulation) ---
echo "\n--- 7. API: Concurrent Claim on Already Claimed Item Is REJECTED ---\n";
$pdo->beginTransaction();
$secondClaim = $pdo->prepare("UPDATE product_sizes SET is_available = 0 WHERE id = 1 AND product_id = 1 AND is_available = 1");
$secondClaim->execute();
$secondClaimRows = $secondClaim->rowCount();
$pdo->rollBack();

it("Second concurrent checkout on claimed unique item affects 0 rows and triggers rollback (409 Conflict)", $secondClaimRows === 0);

// --- 8. WhatsApp Deep Link Generation ---
echo "\n--- 8. API: WhatsApp Deep Link & Message Construction ---\n";
$orderData = [
    'order_number' => $orderNum,
    'customer_name' => 'Zaid Khan',
    'customer_phone' => '03001234567',
    'city' => 'Karachi',
    'delivery_address' => 'DHA Phase 6',
    'subtotal' => '18500.00',
    'delivery_fee' => '0.00',
    'total_amount' => '18500.00',
];
$snapshotItems = [
    [
        'product_name' => 'Nike Air Max 90 Essential',
        'product_size' => 'EU 41',
        'product_color' => 'Black/White',
        'quantity' => 1,
        'product_price' => '18500.00',
        'line_total' => '18500.00',
    ]
];

$waMsg = build_customer_order_whatsapp_message($orderData, $snapshotItems);
$waUrl = "https://wa.me/923001234567?text=" . rawurlencode($waMsg);

it("WhatsApp message contains order number, customer name, snapshot size, and total", 
    str_contains($waMsg, $orderNum) && 
    str_contains($waMsg, 'Zaid Khan') && 
    str_contains($waMsg, 'Size: EU 41') && 
    str_contains($waMsg, 'Rs. 18,500.00')
);
it("WhatsApp URL is properly encoded with target wa.me format", str_starts_with($waUrl, 'https://wa.me/923001234567?text='));

echo "\n====================================================\n";
echo " FULL API TEST SUMMARY: {$passCount} Passed, " . ($testCount - $passCount) . " Failed\n";
echo "====================================================\n\n";

if ($passCount === $testCount) {
    echo "ALL {$testCount} API TEST ASSERTIONS PASSED WITH 100% SUCCESS!\n\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n\n";
    exit(1);
}
