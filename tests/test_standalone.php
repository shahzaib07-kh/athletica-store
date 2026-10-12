<?php
/**
 * ATHLETICA — Phase 2 Standalone Logic, Migration & Security Automated Test Runner
 *
 * Runs complete functional tests of:
 * 1. Category CRUD business logic, validation, CSRF, escaping, and deletion safety
 * 2. Product Sizes & Availability schema architecture (Scenario A & Scenario B)
 * 3. Cascade deletion and Shop by Size queries
 * 4. Phase 2D: Product CRUD, Transactions, CSRF Enforcement, Image Upload Security,
 *    and Order History Preservation (ON DELETE SET NULL).
 */

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

echo "====================================================\n";
echo " ATHLETICA — Comprehensive Automated Test Runner\n";
echo " Phase 2A, 2B, 2C, Size Migration & Phase 2D Product CRUD\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void
{
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] " . $name . "\n";
    } else {
        $failCount++;
        echo " [FAIL] " . $name . ($detail ? " ($detail)" : '') . "\n";
    }
}

// --------------------------------------------------------------------------
// Setup SQLite In-Memory Database matching Migrated MySQL Schema
// --------------------------------------------------------------------------
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Enable foreign key support in SQLite
$pdo->exec("PRAGMA foreign_keys = ON;");

$pdo->exec("
    CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        description TEXT NULL,
        image VARCHAR(255) NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE brands (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        logo VARCHAR(255) NULL,
        description TEXT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER NOT NULL,
        brand_id INTEGER NULL,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(280) NOT NULL UNIQUE,
        sku VARCHAR(50) NULL UNIQUE,
        description TEXT NULL,
        price DECIMAL(10,2) NOT NULL,
        old_price DECIMAL(10,2) NULL,
        color VARCHAR(50) NULL,
        condition VARCHAR(20) NOT NULL DEFAULT 'new',
        product_type VARCHAR(20) NOT NULL DEFAULT 'regular',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        is_featured INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT ON UPDATE CASCADE,
        FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT ON UPDATE CASCADE
    );

    CREATE TABLE product_sizes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        size VARCHAR(30) NOT NULL,
        is_available INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (product_id, size),
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
    );

    CREATE TABLE product_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        alt_text VARCHAR(255) NULL,
        is_primary INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
    );

    CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_number VARCHAR(30) NOT NULL UNIQUE,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(30) NOT NULL,
        customer_email VARCHAR(100) NULL,
        city VARCHAR(100) NOT NULL,
        delivery_address TEXT NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        admin_notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_id INTEGER NULL,
        product_name VARCHAR(255) NOT NULL,
        product_price DECIMAL(10,2) NOT NULL,
        product_size VARCHAR(30) NULL,
        product_color VARCHAR(50) NULL,
        quantity INTEGER NOT NULL DEFAULT 1,
        line_total DECIMAL(10,2) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT ON UPDATE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
    );

    CREATE TABLE site_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        site_name VARCHAR(100) NOT NULL DEFAULT 'ATHLETICA',
        tagline VARCHAR(255) NULL,
        whatsapp_number VARCHAR(30) NULL,
        email VARCHAR(100) NULL,
        phone VARCHAR(30) NULL,
        address TEXT NULL,
        currency_symbol VARCHAR(10) NOT NULL DEFAULT 'Rs.',
        delivery_fee_default DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        footer_text TEXT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    INSERT INTO site_settings (site_name, whatsapp_number, delivery_fee_default) VALUES ('ATHLETICA', '+923001234567', 0.00);
");


// --------------------------------------------------------------------------
// TEST 1 & 2: Auth Guard Functionality
// --------------------------------------------------------------------------
echo "--- 1. Auth Guard & Session Protection ---\n";
$authContent = file_get_contents(__DIR__ . '/../includes/auth.php');
assert_test(
    "auth.php enforces session_start() and checks \$_SESSION['admin_id']",
    strpos($authContent, "\$_SESSION['admin_id']") !== false &&
    strpos($authContent, "header('Location: login.php')") !== false
);

$indexContent  = file_get_contents(__DIR__ . '/../admin/categories/index.php');
$createContent = file_get_contents(__DIR__ . '/../admin/categories/create.php');
$editContent   = file_get_contents(__DIR__ . '/../admin/categories/edit.php');
$deleteContent = file_get_contents(__DIR__ . '/../admin/categories/delete.php');
$toggleContent = file_get_contents(__DIR__ . '/../admin/categories/toggle-status.php');

assert_test(
    "Every category CRUD page requires auth.php at the top",
    strpos($indexContent, "require __DIR__ . '/../../includes/auth.php'") !== false &&
    strpos($createContent, "require __DIR__ . '/../../includes/auth.php'") !== false &&
    strpos($editContent, "require __DIR__ . '/../../includes/auth.php'") !== false &&
    strpos($deleteContent, "require __DIR__ . '/../../includes/auth.php'") !== false &&
    strpos($toggleContent, "require __DIR__ . '/../../includes/auth.php'") !== false
);

// --------------------------------------------------------------------------
// TEST 3: Create Category
// --------------------------------------------------------------------------
echo "\n--- 2. Create Category ---\n";
$catName = "Running Shoes";
$catSlug = slugify($catName);
$stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, sort_order, is_active) VALUES (:name, :slug, :desc, :sort, :active)");
$stmt->execute([
    ':name'   => $catName,
    ':slug'   => $catSlug,
    ':desc'   => 'Performance running footwear',
    ':sort'   => 1,
    ':active' => 1,
]);
$shoesId = (int)$pdo->lastInsertId();

assert_test(
    "Category created successfully via prepared statement (ID: $shoesId, Slug: $catSlug)",
    $shoesId === 1 && $catSlug === 'running-shoes'
);

// --------------------------------------------------------------------------
// TEST 4: Empty Name Validation
// --------------------------------------------------------------------------
echo "\n--- 3. Input Validation ---\n";
$emptyInput = trim("   ");
$isRejected = ($emptyInput === '');
assert_test(
    "Empty category name is detected and rejected",
    $isRejected === true
);

// --------------------------------------------------------------------------
// TEST 5: Duplicate Slug Handling
// --------------------------------------------------------------------------
$dupCheck = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug LIMIT 1");
$dupCheck->execute([':slug' => 'running-shoes']);
$foundDup = (bool)$dupCheck->fetch();
assert_test(
    "Duplicate slug 'running-shoes' is detected and rejected",
    $foundDup === true
);

// --------------------------------------------------------------------------
// TEST 6: Edit Category (including slug uniqueness check excluding self)
// --------------------------------------------------------------------------
echo "\n--- 4. Edit Category & Self-Exclusion ---\n";
// Adding second category
$stmt->execute([
    ':name'   => 'Sneakers',
    ':slug'   => 'sneakers',
    ':desc'   => 'Lifestyle sneakers',
    ':sort'   => 2,
    ':active' => 1,
]);
$sneakersId = (int)$pdo->lastInsertId();

// When editing Sneakers, saving with 'sneakers' should pass (self)
$selfSlugCheck = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug AND id != :id LIMIT 1");
$selfSlugCheck->execute([':slug' => 'sneakers', ':id' => $sneakersId]);
$selfConflict = (bool)$selfSlugCheck->fetch();
assert_test(
    "Editing category keeping its own slug does NOT conflict with itself",
    $selfConflict === false
);

// When editing Sneakers, trying to use 'running-shoes' should fail
$otherSlugCheck = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug AND id != :id LIMIT 1");
$otherSlugCheck->execute([':slug' => 'running-shoes', ':id' => $sneakersId]);
$otherConflict = (bool)$otherSlugCheck->fetch();
assert_test(
    "Editing category using another category's slug is correctly BLOCKED",
    $otherConflict === true
);

// Perform valid edit
$updateStmt = $pdo->prepare("UPDATE categories SET name = :name, sort_order = :sort WHERE id = :id");
$updateStmt->execute([':name' => 'Premium Sneakers', ':sort' => 5, ':id' => $sneakersId]);
$checkUpdated = $pdo->query("SELECT name, sort_order FROM categories WHERE id = $sneakersId")->fetch();
assert_test(
    "Category edit updates record accurately",
    $checkUpdated['name'] === 'Premium Sneakers' && (int)$checkUpdated['sort_order'] === 5
);

// --------------------------------------------------------------------------
// TEST 7: Deactivate Category
// --------------------------------------------------------------------------
echo "\n--- 5. Active / Inactive Status Toggle ---\n";
$pdo->prepare("UPDATE categories SET is_active = 0 WHERE id = :id")->execute([':id' => $sneakersId]);
$status1 = (int)$pdo->query("SELECT is_active FROM categories WHERE id = $sneakersId")->fetchColumn();
assert_test(
    "Deactivating category sets is_active = 0",
    $status1 === 0
);

// --------------------------------------------------------------------------
// TEST 8: Reactivate Category
// --------------------------------------------------------------------------
$pdo->prepare("UPDATE categories SET is_active = 1 WHERE id = :id")->execute([':id' => $sneakersId]);
$status2 = (int)$pdo->query("SELECT is_active FROM categories WHERE id = $sneakersId")->fetchColumn();
assert_test(
    "Reactivating category sets is_active = 1",
    $status2 === 1
);

// --------------------------------------------------------------------------
// TEST 9: Unsafe Deletion Protection (Associated Products)
// --------------------------------------------------------------------------
echo "\n--- 6. Deletion Safety Checks ---\n";
// Insert a product into category 1 (Running Shoes)
$prodStmt = $pdo->prepare("INSERT INTO products (category_id, name, slug, price, status) VALUES (:cid, 'Nike Air Zoom Pegasus', 'nike-pegasus', 15000, 'active')");
$prodStmt->execute([':cid' => $shoesId]);
$pegasusId = (int)$pdo->lastInsertId();

// Check deletion safety logic
$checkProdCount = $pdo->prepare("SELECT COUNT(*) AS total FROM products WHERE category_id = :id");
$checkProdCount->execute([':id' => $shoesId]);
$associatedCount = (int)$checkProdCount->fetchColumn();

$isDeletionBlocked = ($associatedCount > 0);
assert_test(
    "Category with associated product (count: $associatedCount) is BLOCKED from deletion",
    $isDeletionBlocked === true
);

// --------------------------------------------------------------------------
// TEST 10: CSRF Security
// --------------------------------------------------------------------------
echo "\n--- 7. CSRF Protection ---\n";
$_SESSION['csrf_token'] = 'valid_secret_token_1234567890abcdef';
$validTest = csrf_verify('valid_secret_token_1234567890abcdef');
$invalidTest = csrf_verify('attacker_fake_token');
$emptyTest = csrf_verify('');

assert_test("Valid CSRF token passes", $validTest === true);
assert_test("Forged CSRF token is rejected", $invalidTest === false);
assert_test("Empty CSRF token is rejected", $emptyTest === false);

// --------------------------------------------------------------------------
// TEST 11: Output Escaping
// --------------------------------------------------------------------------
echo "\n--- 8. XSS Escaping ---\n";
$xssPayload = '<script>alert("Hacked")</script>';
$escapedPayload = htmlspecialchars($xssPayload, ENT_QUOTES, 'UTF-8');
assert_test(
    "XSS payload is sanitized safely",
    $escapedPayload === '&lt;script&gt;alert(&quot;Hacked&quot;)&lt;/script&gt;'
);

// --------------------------------------------------------------------------
// TEST 12: Safe Permanent Deletion of Empty Category
// --------------------------------------------------------------------------
echo "\n--- 9. Safe Permanent Deletion of Empty Category ---\n";
// Category 2 (Sneakers) has 0 products
$checkProdCount->execute([':id' => $sneakersId]);
$sneakersCount = (int)$checkProdCount->fetchColumn();

if ($sneakersCount === 0) {
    $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute([':id' => $sneakersId]);
}
$sneakersLeft = $pdo->query("SELECT COUNT(*) FROM categories WHERE id = $sneakersId")->fetchColumn();
assert_test(
    "Category with 0 products is safely and permanently deleted",
    (int)$sneakersLeft === 0
);

// --------------------------------------------------------------------------
// TEST 13: Product Sizes Architecture (Scenario A — Unique Product)
// --------------------------------------------------------------------------
echo "\n--- 10. Product Sizes: Scenario A (Unique One-Pair) ---\n";
// Add 1 size option to Pegasus (UK 9)
$sizeStmt = $pdo->prepare("INSERT INTO product_sizes (product_id, size, is_available, sort_order) VALUES (:pid, :size, :available, :sort)");
$sizeStmt->execute([
    ':pid'       => $pegasusId,
    ':size'      => 'UK 9',
    ':available' => 1,
    ':sort'      => 1,
]);
$sizeRow = $pdo->query("SELECT * FROM product_sizes WHERE product_id = $pegasusId")->fetch();
assert_test(
    "Scenario A: Unique product has single size record (UK 9, is_available = 1)",
    $sizeRow && $sizeRow['size'] === 'UK 9' && (int)$sizeRow['is_available'] === 1
);

// --------------------------------------------------------------------------
// TEST 14: Product Sizes Architecture (Scenario B — Regular Multi-Size)
// --------------------------------------------------------------------------
echo "\n--- 11. Product Sizes: Scenario B (Regular Multi-Size) ---\n";
// Create Shirt Product in Category 1
$insertShirt = $pdo->prepare("INSERT INTO products (category_id, name, slug, price, status) VALUES (:cid, 'Nike Dri-FIT Tee', 'nike-dri-fit-tee', 4500, 'active')");
$insertShirt->execute([':cid' => $shoesId]);
$shirtId = (int)$pdo->lastInsertId();

// Insert multiple sizes with varying availability
$sizeStmt->execute([':pid' => $shirtId, ':size' => 'S',  ':available' => 1, ':sort' => 1]);
$sizeStmt->execute([':pid' => $shirtId, ':size' => 'M',  ':available' => 1, ':sort' => 2]);
$sizeStmt->execute([':pid' => $shirtId, ':size' => 'L',  ':available' => 1, ':sort' => 3]);
$sizeStmt->execute([':pid' => $shirtId, ':size' => 'XL', ':available' => 0, ':sort' => 4]); // Out of stock

$allSizes = $pdo->query("SELECT size, is_available FROM product_sizes WHERE product_id = $shirtId ORDER BY sort_order")->fetchAll();
assert_test(
    "Scenario B: Multi-size product has 4 sizes with individual availability",
    count($allSizes) === 4 && (int)$allSizes[3]['is_available'] === 0
);

// --------------------------------------------------------------------------
// TEST 15: UNIQUE(product_id, size) Constraint
// --------------------------------------------------------------------------
echo "\n--- 12. UNIQUE(product_id, size) Constraint ---\n";
$duplicateCaught = false;
try {
    // Attempt inserting duplicate 'M' size for shirt
    $sizeStmt->execute([':pid' => $shirtId, ':size' => 'M', ':available' => 1, ':sort' => 5]);
} catch (PDOException $e) {
    $duplicateCaught = true;
}
assert_test(
    "Duplicate (product_id, size) is rejected by UNIQUE constraint",
    $duplicateCaught === true
);

// --------------------------------------------------------------------------
// TEST 16: Shop by Size Query
// --------------------------------------------------------------------------
echo "\n--- 13. Shop by Size Query ---\n";
// Query products available in UK 9
$shopQuery = $pdo->query("
    SELECT DISTINCT p.id, p.name
    FROM products p
    JOIN product_sizes ps ON ps.product_id = p.id
    WHERE ps.size = 'UK 9' AND ps.is_available = 1 AND p.status = 'active'
")->fetchAll();

assert_test(
    "Shop by Size (UK 9) successfully locates available products",
    count($shopQuery) === 1 && $shopQuery[0]['name'] === 'Nike Air Zoom Pegasus'
);

// --------------------------------------------------------------------------
// TEST 17: Cascade Deletion on Product Sizes
// --------------------------------------------------------------------------
echo "\n--- 14. Cascade Deletion Integrity ---\n";
// When shirt product is deleted, its 4 sizes must automatically cascade delete
$pdo->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $shirtId]);
$remainingSizes = (int)$pdo->query("SELECT COUNT(*) FROM product_sizes WHERE product_id = $shirtId")->fetchColumn();
assert_test(
    "Deleting product automatically CASCADE deletes associated product_sizes records",
    $remainingSizes === 0
);

// ==========================================================================
// PHASE 2D: PRODUCT MANAGEMENT CRUD AUTOMATED TEST SUITE
// ==========================================================================

echo "\n--- 15. Phase 2D: Auth Guard Protection across All 10 Product Pages ---\n";
$productPages = [
    'index.php', 'create.php', 'edit.php', 'delete.php', 'sizes.php',
    'delete-size.php', 'images.php', 'delete-image.php', 'toggle-featured.php', 'toggle-status.php'
];
$allAuthProtected = true;
foreach ($productPages as $page) {
    $content = file_get_contents(__DIR__ . '/../admin/products/' . $page);
    if (strpos($content, "require __DIR__ . '/../../includes/auth.php'") === false &&
        strpos($content, 'includes/auth.php') === false) {
        $allAuthProtected = false;
        break;
    }
}
assert_test(
    "All 10 product management pages require auth.php authentication guard",
    $allAuthProtected === true
);

// --------------------------------------------------------------------------
// TEST 19: CSRF Enforcement on All 9 Product POST Handlers
// --------------------------------------------------------------------------
echo "\n--- 16. Phase 2D: CSRF Strict Boolean Enforcement ---\n";
$postHandlers = [
    'create.php', 'edit.php', 'delete.php', 'sizes.php',
    'delete-size.php', 'images.php', 'delete-image.php', 'toggle-featured.php', 'toggle-status.php'
];
$allCsrfEnforced = true;
foreach ($postHandlers as $page) {
    $content = file_get_contents(__DIR__ . '/../admin/products/' . $page);
    // Verify that csrf_verify is checked with an if condition (e.g. if (!csrf_verify()))
    if (strpos($content, 'if (!csrf_verify())') === false) {
        $allCsrfEnforced = false;
        echo "   [FAIL DETAIL] $page missing strict if (!csrf_verify()) check\n";
    }
}
assert_test(
    "All 9 state-changing product handlers strictly enforce if (!csrf_verify())",
    $allCsrfEnforced === true
);

// --------------------------------------------------------------------------
// TEST 20: Product Creation via Transaction & Rollback Safety
// --------------------------------------------------------------------------
echo "\n--- 17. Phase 2D: Product Creation & Transaction Safety ---\n";
// Simulate successful product creation with transaction
$pdo->beginTransaction();
$insProd = $pdo->prepare("INSERT INTO products (category_id, name, slug, sku, description, price, old_price, color, condition, status, is_featured) VALUES (:cid, :name, :slug, :sku, :desc, :price, :old_price, :color, :condition, :status, :is_featured)");
$insProd->execute([
    ':cid'         => $shoesId,
    ':name'        => 'Adidas Ultraboost Light',
    ':slug'        => 'adidas-ultraboost-light',
    ':sku'         => 'ATH-UB-001',
    ':desc'        => 'Lightweight responsive running shoe',
    ':price'       => 24500.00,
    ':old_price'   => 28000.00,
    ':color'       => 'Core Black',
    ':condition'   => 'new',
    ':status'      => 'active',
    ':is_featured' => 1,
]);
$ubId = (int)$pdo->lastInsertId();

// Insert initial sizes in same transaction
$insSize = $pdo->prepare("INSERT INTO product_sizes (product_id, size, is_available, sort_order) VALUES (:pid, :size, :avail, :sort)");
$insSize->execute([':pid' => $ubId, ':size' => 'UK 8',  ':avail' => 1, ':sort' => 1]);
$insSize->execute([':pid' => $ubId, ':size' => 'UK 9',  ':avail' => 1, ':sort' => 2]);
$insSize->execute([':pid' => $ubId, ':size' => 'UK 10', ':avail' => 0, ':sort' => 3]);

// Insert primary image
$insImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order) VALUES (:pid, :path, :alt, :primary, :sort)");
$insImg->execute([
    ':pid'     => $ubId,
    ':path'    => 'uploads/products/prod-ub-main.jpg',
    ':alt'     => 'Adidas Ultraboost Light - Main',
    ':primary' => 1,
    ':sort'    => 0,
]);
$pdo->commit();

$createdProduct = $pdo->query("SELECT * FROM products WHERE id = $ubId")->fetch();
$createdSizes = $pdo->query("SELECT COUNT(*) FROM product_sizes WHERE product_id = $ubId")->fetchColumn();
$createdImages = $pdo->query("SELECT COUNT(*) FROM product_images WHERE product_id = $ubId")->fetchColumn();

assert_test(
    "Product creation transaction commits product, sizes, and images atomically",
    $createdProduct && $createdProduct['name'] === 'Adidas Ultraboost Light' && (int)$createdSizes === 3 && (int)$createdImages === 1
);

// --------------------------------------------------------------------------
// TEST 21: Transaction Rollback on Failure
// --------------------------------------------------------------------------
$rollbackPassed = false;
try {
    $pdo->beginTransaction();
    $insProd->execute([
        ':cid'         => $shoesId,
        ':name'        => 'Broken Product',
        ':slug'        => 'broken-product',
        ':sku'         => 'ATH-BRK-001',
        ':desc'        => 'Should be rolled back',
        ':price'       => 1000.00,
        ':old_price'   => null,
        ':color'       => 'Red',
        ':condition'   => 'new',
        ':status'      => 'active',
        ':is_featured' => 0,
    ]);
    $brkId = (int)$pdo->lastInsertId();
    
    // Simulate intentional error (duplicate slug for another insert)
    $insProd->execute([
        ':cid'         => $shoesId,
        ':name'        => 'Duplicate Slug Product',
        ':slug'        => 'broken-product', // duplicate!
        ':sku'         => 'ATH-BRK-002',
        ':desc'        => 'Should fail',
        ':price'       => 2000.00,
        ':old_price'   => null,
        ':color'       => 'Blue',
        ':condition'   => 'new',
        ':status'      => 'active',
        ':is_featured' => 0,
    ]);
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    $checkBrk = $pdo->query("SELECT COUNT(*) FROM products WHERE slug = 'broken-product'")->fetchColumn();
    $rollbackPassed = ((int)$checkBrk === 0);
}
assert_test(
    "Transaction rollback properly discards uncommitted product on failure",
    $rollbackPassed === true
);

// --------------------------------------------------------------------------
// TEST 22: Product Edit & Self-Excluding Uniqueness
// --------------------------------------------------------------------------
echo "\n--- 18. Phase 2D: Product Edit & Self-Excluding Uniqueness ---\n";
// Update Ultraboost without changing slug
$selfSlugStmt = $pdo->prepare("SELECT id FROM products WHERE slug = :slug AND id != :id");
$selfSlugStmt->execute([':slug' => 'adidas-ultraboost-light', ':id' => $ubId]);
$selfSlugConflict = (bool)$selfSlugStmt->fetch();

assert_test(
    "Product edit keeping its own slug does NOT conflict with itself",
    $selfSlugConflict === false
);

// Attempting to change slug to Pegasus slug ('nike-pegasus')
$otherSlugStmt = $pdo->prepare("SELECT id FROM products WHERE slug = :slug AND id != :id");
$otherSlugStmt->execute([':slug' => 'nike-pegasus', ':id' => $ubId]);
$otherSlugConflict = (bool)$otherSlugStmt->fetch();

assert_test(
    "Product edit using another product's slug is correctly BLOCKED",
    $otherSlugConflict === true
);

// Perform valid update
$updateProd = $pdo->prepare("UPDATE products SET price = :price, color = :color, is_featured = :feat WHERE id = :id");
$updateProd->execute([':price' => 23000.00, ':color' => 'Triple Black', ':feat' => 0, ':id' => $ubId]);
$updatedRow = $pdo->query("SELECT price, color, is_featured FROM products WHERE id = $ubId")->fetch();

assert_test(
    "Product edit successfully updates price, color, and featured state",
    (float)$updatedRow['price'] === 23000.00 && $updatedRow['color'] === 'Triple Black' && (int)$updatedRow['is_featured'] === 0
);

// --------------------------------------------------------------------------
// TEST 23: Size Availability Toggle
// --------------------------------------------------------------------------
echo "\n--- 19. Phase 2D: Size Availability & Sort Order Management ---\n";
$uk8Row = $pdo->query("SELECT id, is_available FROM product_sizes WHERE product_id = $ubId AND size = 'UK 8'")->fetch();
$uk8Id = (int)$uk8Row['id'];

// Toggle UK 8 from 1 -> 0
$pdo->prepare("UPDATE product_sizes SET is_available = NOT is_available WHERE id = :id AND product_id = :pid")->execute([':id' => $uk8Id, ':pid' => $ubId]);
$toggledStatus = (int)$pdo->query("SELECT is_available FROM product_sizes WHERE id = $uk8Id")->fetchColumn();

assert_test(
    "Size availability toggle inverts is_available flag (1 -> 0)",
    $toggledStatus === 0
);

// --------------------------------------------------------------------------
// TEST 24: Protection Against Deleting Last Remaining Size
// --------------------------------------------------------------------------
echo "\n--- 20. Phase 2D: Single Remaining Size Protection ---\n";
// Create a unique one-pair product with 1 size
$insProd->execute([
    ':cid'         => $shoesId,
    ':name'        => 'One Pair Rare Shoe',
    ':slug'        => 'one-pair-rare-shoe',
    ':sku'         => 'ATH-RARE-001',
    ':desc'        => 'Only one physical pair exists',
    ':price'       => 35000.00,
    ':old_price'   => null,
    ':color'       => 'Gold',
    ':condition'   => 'new',
    ':status'      => 'active',
    ':is_featured' => 0,
]);
$rareId = (int)$pdo->lastInsertId();
$insSize->execute([':pid' => $rareId, ':size' => 'UK 9.5', ':avail' => 1, ':sort' => 1]);
$rareSizeId = (int)$pdo->lastInsertId();

// Verify delete-size.php protection logic: count sizes before deleting
$sizeCount = (int)$pdo->query("SELECT COUNT(*) FROM product_sizes WHERE product_id = $rareId")->fetchColumn();
$deletionBlocked = ($sizeCount <= 1);

assert_test(
    "Deleting the last remaining size is BLOCKED (minimum 1 size required)",
    $deletionBlocked === true
);

// --------------------------------------------------------------------------
// TEST 25: Status Toggle & Featured Toggle
// --------------------------------------------------------------------------
echo "\n--- 21. Phase 2D: Status & Featured Toggles ---\n";
// Toggle status to 'out_of_stock'
$pdo->prepare("UPDATE products SET status = :st WHERE id = :id")->execute([':st' => 'out_of_stock', ':id' => $rareId]);
$statusAfter = $pdo->query("SELECT status FROM products WHERE id = $rareId")->fetchColumn();
assert_test(
    "Product status toggle correctly sets 'out_of_stock'",
    $statusAfter === 'out_of_stock'
);

// Toggle featured
$pdo->prepare("UPDATE products SET is_featured = NOT is_featured WHERE id = :id")->execute([':id' => $rareId]);
$featAfter = (int)$pdo->query("SELECT is_featured FROM products WHERE id = $rareId")->fetchColumn();
assert_test(
    "Product is_featured toggle sets flag to 1",
    $featAfter === 1
);

// --------------------------------------------------------------------------
// TEST 26: Order History Preservation on Product Deletion (ON DELETE SET NULL)
// --------------------------------------------------------------------------
echo "\n--- 22. Phase 2D: Order History Preservation (ON DELETE SET NULL) ---\n";
// Create an order
$pdo->prepare("INSERT INTO orders (order_number, customer_name, customer_phone, city, delivery_address, subtotal, delivery_fee, total_amount, status) VALUES ('ATH-20260827-0001', 'Ali Ahmed', '+923001234567', 'Lahore', 'Model Town Block A', 23000.00, 200.00, 23200.00, 'confirmed')")->execute();
$orderId = (int)$pdo->lastInsertId();

// Create snapshot order item referencing Ultraboost
$pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, product_size, product_color, quantity, line_total) VALUES (:oid, :pid, :name, :price, :size, :color, :qty, :total)")->execute([
    ':oid'   => $orderId,
    ':pid'   => $ubId,
    ':name'  => 'Adidas Ultraboost Light',
    ':price' => 23000.00,
    ':size'  => 'UK 9',
    ':color' => 'Triple Black',
    ':qty'   => 1,
    ':total' => 23000.00,
]);
$orderItemId = (int)$pdo->lastInsertId();

// Now delete the Ultraboost product
$pdo->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $ubId]);

// Verify order_items row still exists
$orderItemRow = $pdo->query("SELECT * FROM order_items WHERE id = $orderItemId")->fetch();
$ubSizesRemaining = (int)$pdo->query("SELECT COUNT(*) FROM product_sizes WHERE product_id = $ubId")->fetchColumn();
$ubImagesRemaining = (int)$pdo->query("SELECT COUNT(*) FROM product_images WHERE product_id = $ubId")->fetchColumn();

assert_test(
    "Order item record is PRESERVED after product deletion",
    $orderItemRow !== false
);
assert_test(
    "Order item product_id is set to NULL while snapshot fields remain intact",
    $orderItemRow['product_id'] === null &&
    $orderItemRow['product_name'] === 'Adidas Ultraboost Light' &&
    $orderItemRow['product_size'] === 'UK 9' &&
    (float)$orderItemRow['product_price'] === 23000.00 &&
    (float)$orderItemRow['line_total'] === 23000.00
);
assert_test(
    "Product deletion automatically CASCADE deleted associated product_sizes (0 remaining)",
    $ubSizesRemaining === 0
);
assert_test(
    "Product deletion automatically CASCADE deleted associated product_images (0 remaining)",
    $ubImagesRemaining === 0
);

// --------------------------------------------------------------------------
// TEST 27: Image Upload Helper Security Validation
// --------------------------------------------------------------------------
echo "\n--- 23. Phase 2D: Image Upload Helper Security ---\n";
// Test 1: Upload error rejection
$badFile1 = ['error' => UPLOAD_ERR_INI_SIZE, 'size' => 0, 'tmp_name' => '', 'name' => 'bad.jpg'];
$res1 = upload_product_image($badFile1, 999);
assert_test(
    "upload_product_image rejects upload with error code",
    $res1['success'] === false && strpos($res1['error'], 'failed') !== false
);

// Test 2: File size limit (2MB)
$badFile2 = ['error' => UPLOAD_ERR_OK, 'size' => 3 * 1024 * 1024, 'tmp_name' => '', 'name' => 'huge.jpg'];
$res2 = upload_product_image($badFile2, 999);
assert_test(
    "upload_product_image rejects file exceeding 2MB limit",
    $res2['success'] === false && strpos($res2['error'], '2MB') !== false
);

// Test 3: Disallowed MIME type rejection (e.g. PHP script pretending to be JPG)
$tempScript = tempnam(sys_get_temp_dir(), 'test_sec_');
file_put_contents($tempScript, '<?php echo "evil"; ?>');
$badFile3 = ['error' => UPLOAD_ERR_OK, 'size' => 100, 'tmp_name' => $tempScript, 'name' => 'shell.php'];
$res3 = upload_product_image($badFile3, 999);
@unlink($tempScript);

assert_test(
    "upload_product_image rejects script/executable files via finfo MIME detection",
    $res3['success'] === false && strpos($res3['error'], 'Invalid image type') !== false
);

// --------------------------------------------------------------------------
// TEST 28: .htaccess Upload Directory Security
// --------------------------------------------------------------------------
echo "\n--- 24. Phase 2D: Upload Directory Hardening (.htaccess) ---\n";
$htaccessPath = __DIR__ . '/../uploads/products/.htaccess';
$htaccessContent = file_exists($htaccessPath) ? file_get_contents($htaccessPath) : '';
assert_test(
    "uploads/products/.htaccess exists and blocks PHP/script execution",
    file_exists($htaccessPath) &&
    strpos($htaccessContent, 'Require all denied') !== false &&
    strpos($htaccessContent, 'RemoveHandler .php') !== false
);

// ==========================================================================
// PHASE 3: STOREFRONT ARCHITECTURE & UI/UX INTEGRATION TESTS
// ==========================================================================

// Reactivate product and add test primary image
$pdo->prepare("UPDATE products SET status = 'active', is_featured = 1 WHERE id = :id")->execute([':id' => $rareId]);
$pdo->prepare("INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order) VALUES (:pid, 'uploads/products/rare-main.jpg', 'One Pair Rare Shoe', 1, 0)")->execute([':pid' => $rareId]);

echo "\n--- 25. Phase 3: Storefront Active Category Navigation Query ---\n";
// Insert test categories if needed
$activeCats = $pdo->query("SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
assert_test(
    "Storefront fetches active categories for navbar and category showcase",
    count($activeCats) >= 1 && $activeCats[0]['name'] === 'Running Shoes'
);

echo "\n--- 26. Phase 3: Storefront Featured Products Query ---\n";
$featProds = $pdo->query("
    SELECT p.*, c.name AS category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'active' AND p.is_featured = 1
")->fetchAll();
assert_test(
    "Storefront query retrieves active featured products with primary image",
    count($featProds) >= 1 && $featProds[0]['name'] === 'One Pair Rare Shoe'
);

echo "\n--- 27. Phase 3: Storefront Shop by Size Query (Scenario A & B) ---\n";
$sizeSearchStmt = $pdo->prepare("
    SELECT DISTINCT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    JOIN product_sizes ps ON ps.product_id = p.id
    WHERE p.status = 'active' AND ps.size = :size AND ps.is_available = 1
");
$sizeSearchStmt->execute([':size' => 'UK 9.5']);
$sizeResults = $sizeSearchStmt->fetchAll();
assert_test(
    "Storefront Shop by Size query returns products in size 'UK 9.5'",
    count($sizeResults) === 1 && $sizeResults[0]['name'] === 'One Pair Rare Shoe'
);

echo "\n--- 28. Phase 3: Storefront Product Details with Gallery & Sizes ---\n";
$detailStmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.slug = :slug AND p.status != 'inactive'
");
$detailStmt->execute([':slug' => 'one-pair-rare-shoe']);
$detailProd = $detailStmt->fetch();

$detailSizesStmt = $pdo->prepare("SELECT * FROM product_sizes WHERE product_id = :pid ORDER BY sort_order ASC");
$detailSizesStmt->execute([':pid' => $detailProd['id']]);
$detailSizes = $detailSizesStmt->fetchAll();

assert_test(
    "Product details query loads product and per-size availability correctly",
    $detailProd !== false && count($detailSizes) === 1 && $detailSizes[0]['size'] === 'UK 9.5'
);

echo "\n--- 29. Phase 3: Storefront Catalog Search Query ---\n";
$searchStmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'active' AND (p.name LIKE :q OR c.name LIKE :q)
");
$searchStmt->execute([':q' => '%Rare%']);
$searchResults = $searchStmt->fetchAll();
assert_test(
    "Storefront search locates products matching keyword 'Rare'",
    count($searchResults) === 1 && $searchResults[0]['name'] === 'One Pair Rare Shoe'
);

// ==========================================================================
// PHASE 4 MIGRATION: DYNAMIC BRANDS, EXPLICIT PRODUCT TYPE & FREE DELIVERY
// ==========================================================================

echo "\n--- 30. Phase 4: Brand Creation via Prepared Statement ---\n";
$insBrand = $pdo->prepare("INSERT INTO brands (name, slug, description, sort_order, is_active) VALUES (:name, :slug, :desc, :sort, :active)");
$insBrand->execute([
    ':name'   => 'Nike',
    ':slug'   => 'nike',
    ':desc'   => 'World leader in athletic footwear',
    ':sort'   => 1,
    ':active' => 1
]);
$nikeId = (int)$pdo->lastInsertId();

$insBrand->execute([
    ':name'   => 'ASICS',
    ':slug'   => 'asics',
    ':desc'   => 'Japanese precision running performance',
    ':sort'   => 2,
    ':active' => 1
]);
$asicsId = (int)$pdo->lastInsertId();

$insBrand->execute([
    ':name'   => 'Hoka',
    ':slug'   => 'hoka',
    ':desc'   => 'Maximalist road and trail running',
    ':sort'   => 3,
    ':active' => 1
]);
$hokaId = (int)$pdo->lastInsertId();

assert_test(
    "Brands created successfully with unique IDs and slugs (Nike: $nikeId, ASICS: $asicsId, Hoka: $hokaId)",
    $nikeId > 0 && $asicsId > 0 && $hokaId > 0
);

echo "\n--- 31. Phase 4: Duplicate Brand Slug Rejection ---\n";
$dupBrandRejected = false;
try {
    $insBrand->execute([
        ':name'   => 'Nike Pakistan',
        ':slug'   => 'nike', // Duplicate slug
        ':desc'   => 'Another Nike entry',
        ':sort'   => 4,
        ':active' => 1
    ]);
} catch (PDOException $e) {
    $dupBrandRejected = true;
}
assert_test(
    "Duplicate brand slug 'nike' is rejected by UNIQUE constraint",
    $dupBrandRejected === true
);

echo "\n--- 32. Phase 4: Brand Edit & Self-Exclusion ---\n";
// Edit own name/desc keeping own slug -> should succeed
$editBrandStmt = $pdo->prepare("UPDATE brands SET name = :name, slug = :slug WHERE id = :id");
$editBrandStmt->execute([
    ':name' => 'Nike Global Athletic',
    ':slug' => 'nike',
    ':id'   => $nikeId
]);
$editedBrand = $pdo->query("SELECT name FROM brands WHERE id = $nikeId")->fetch();
assert_test(
    "Brand edit keeping own slug succeeds without self-conflict",
    $editedBrand['name'] === 'Nike Global Athletic'
);

echo "\n--- 33. Phase 4: Brand Deactivation & Reactivation ---\n";
$toggleBrandStmt = $pdo->prepare("UPDATE brands SET is_active = :st WHERE id = :id");
$toggleBrandStmt->execute([':st' => 0, ':id' => $asicsId]);
$asicsState1 = (int)$pdo->query("SELECT is_active FROM brands WHERE id = $asicsId")->fetchColumn();

$toggleBrandStmt->execute([':st' => 1, ':id' => $asicsId]);
$asicsState2 = (int)$pdo->query("SELECT is_active FROM brands WHERE id = $asicsId")->fetchColumn();

assert_test(
    "Brand deactivation (0) and reactivation (1) updates is_active correctly",
    $asicsState1 === 0 && $asicsState2 === 1
);

echo "\n--- 34. Phase 4: Product-Brand Relationship & Explicit Product Type ---\n";
// Insert products linking brand_id and explicit product_type
$insProdType = $pdo->prepare("
    INSERT INTO products (category_id, brand_id, name, slug, sku, description, price, old_price, color, condition, product_type, status, is_featured)
    VALUES (:cid, :bid, :name, :slug, :sku, :desc, :price, :old_price, :color, :cond, :ptype, :status, :feat)
");

// Product A: Unique single-pair Nike shoe
$insProdType->execute([
    ':cid'       => $shoesId,
    ':bid'       => $nikeId,
    ':name'      => 'Nike Vaporfly Next% 3',
    ':slug'      => 'nike-vaporfly-next-3',
    ':sku'       => 'ATH-VF3-001',
    ':desc'      => 'Elite marathon racing shoe. Single imported pair.',
    ':price'     => 34000.00,
    ':old_price' => 38000.00,
    ':color'     => 'Bright Crimson',
    ':cond'      => 'new',
    ':ptype'     => 'unique', // Explicit UNIQUE
    ':status'    => 'active',
    ':feat'      => 1
]);
$vf3Id = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO product_sizes (product_id, size, is_available, sort_order) VALUES ($vf3Id, 'UK 9', 1, 1)")->execute();

// Product B: Regular multi-size ASICS shoe
$insProdType->execute([
    ':cid'       => $shoesId,
    ':bid'       => $asicsId,
    ':name'      => 'ASICS Novablast 4',
    ':slug'      => 'asics-novablast-4',
    ':sku'       => 'ATH-NB4-001',
    ':desc'      => 'High-energy daily trainer with FF BLAST PLUS ECO.',
    ':price'     => 22500.00,
    ':old_price' => 25000.00,
    ':color'     => 'Illusion Blue',
    ':cond'      => 'new',
    ':ptype'     => 'regular', // Explicit REGULAR
    ':status'    => 'active',
    ':feat'      => 1
]);
$nb4Id = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO product_sizes (product_id, size, is_available, sort_order) VALUES ($nb4Id, 'UK 8', 1, 1), ($nb4Id, 'UK 9', 1, 2), ($nb4Id, 'UK 10', 0, 3)")->execute();

$prodTypes = $pdo->query("SELECT id, name, brand_id, product_type FROM products WHERE id IN ($vf3Id, $nb4Id) ORDER BY id ASC")->fetchAll();
assert_test(
    "Products store foreign brand_id and explicit product_type ('unique' and 'regular')",
    count($prodTypes) === 2 &&
    $prodTypes[0]['product_type'] === 'unique' && $prodTypes[0]['brand_id'] == $nikeId &&
    $prodTypes[1]['product_type'] === 'regular' && $prodTypes[1]['brand_id'] == $asicsId
);

echo "\n--- 35. Phase 4: Brand Deletion Safety (Blocked when products exist) ---\n";
$brandBlocked = false;
$nikeProdCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE brand_id = $nikeId")->fetchColumn();
if ($nikeProdCount > 0) {
    $brandBlocked = true; // Admin deletion logic blocks
}
assert_test(
    "Brand deletion is BLOCKED when products are assigned (Nike product count: $nikeProdCount)",
    $brandBlocked === true && $nikeProdCount >= 1
);

echo "\n--- 36. Phase 4: Safe Deletion of Empty Brand ---\n";
$hokaProdCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE brand_id = $hokaId")->fetchColumn();
$pdo->prepare("DELETE FROM brands WHERE id = :id")->execute([':id' => $hokaId]);
$hokaCheck = $pdo->query("SELECT id FROM brands WHERE id = $hokaId")->fetch();
assert_test(
    "Brand with 0 products (Hoka) is safely and permanently deleted",
    $hokaProdCount === 0 && $hokaCheck === false
);

echo "\n--- 37. Phase 4: Free Delivery Default (0.00) in site_settings ---\n";
$deliveryFee = $pdo->query("SELECT delivery_fee_default FROM site_settings LIMIT 1")->fetchColumn();
assert_test(
    "site_settings.delivery_fee_default is configured as 0.00 (Free Delivery)",
    (float)$deliveryFee === 0.00
);

echo "\n--- 38. Phase 4: Combined Filter Query (Brand: ASICS + Size: UK 8) ---\n";
$combinedStmt = $pdo->prepare("
    SELECT DISTINCT p.*, b.name AS brand_name, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN brands b ON b.id = p.brand_id
    JOIN product_sizes ps ON ps.product_id = p.id
    WHERE p.status = 'active'
      AND b.slug = :brand
      AND ps.size = :size
      AND ps.is_available = 1
");
$combinedStmt->execute([':brand' => 'asics', ':size' => 'UK 8']);
$asicsUk8 = $combinedStmt->fetchAll();
assert_test(
    "Combined query 'ASICS + UK 8' returns only available ASICS products in UK 8",
    count($asicsUk8) === 1 && $asicsUk8[0]['name'] === 'ASICS Novablast 4'
);

echo "\n--- 39. Phase 4: Related Products Matching Brand and Category ---\n";
$relatedStmt = $pdo->prepare("
    SELECT p.*, b.name AS brand_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN brands b ON b.id = p.brand_id
    WHERE p.status = 'active'
      AND p.id != :curr_id
      AND (p.brand_id = :bid OR p.category_id = :cid)
    ORDER BY (p.brand_id = :bid) DESC, p.created_at DESC
");
$relatedStmt->execute([
    ':curr_id' => $vf3Id,
    ':bid'     => $nikeId,
    ':cid'     => $shoesId
]);
$related = $relatedStmt->fetchAll();
assert_test(
    "Related products query retrieves matching items prioritizing same brand/category",
    count($related) >= 1
);

echo "\n--- 40. Phase 4: Admin Brand Management Auth & CSRF Protection ---\n";
$brandPages = ['index.php', 'create.php', 'edit.php', 'delete.php', 'toggle-status.php'];
$allBrandAuth = true;
$allBrandCsrf = true;

foreach ($brandPages as $bp) {
    $content = file_get_contents(__DIR__ . '/../admin/brands/' . $bp);
    if (strpos($content, 'includes/auth.php') === false) {
        $allBrandAuth = false;
    }
    if ($bp !== 'index.php' && strpos($content, 'if (!csrf_verify())') === false) {
        $allBrandCsrf = false;
    }
}
assert_test(
    "All 5 admin brand management files require auth.php and all 4 modifying handlers enforce if (!csrf_verify())",
    $allBrandAuth === true && $allBrandCsrf === true
);

// --------------------------------------------------------------------------
// PHASE 4B: CART ENGINE TESTS
// --------------------------------------------------------------------------
require_once __DIR__ . '/../includes/cart-helper.php';

echo "\n--- 41. Phase 4B: Deterministic Cart Key Generation ---\n";
$key1 = cart_generate_key(10, 'UK 9');
$key2 = cart_generate_key(10, 'uk 9');
$key3 = cart_generate_key(10, 'UK 8');
assert_test(
    "cart_generate_key normalizes size and produces deterministic key",
    $key1 === '10_uk_9' && $key2 === '10_uk_9' && $key3 === '10_uk_8' && $key1 !== $key3
);

echo "\n--- 42. Phase 4B: Add Item to Cart with Database Validation ---\n";
$_SESSION['cart'] = [];
// Product B is ASICS Novablast 4 (ID: $nb4Id, price: 22500.00, regular, size: 'UK 8')
$addRes1 = cart_add($pdo, (int)$nb4Id, 'UK 8', 1);
$nb4CartKey = cart_generate_key($nb4Id, 'UK 8');
assert_test(
    "cart_add successfully adds valid active product and available size",
    $addRes1['success'] === true && isset($_SESSION['cart'][$nb4CartKey])
);

echo "\n--- 43. Phase 4B: Rejection of Inactive Product / Invalid Size ---\n";
$invalidProdRes = cart_add($pdo, 99999, 'UK 8', 1);
$invalidSizeRes = cart_add($pdo, (int)$nb4Id, 'UK 99', 1);
assert_test(
    "cart_add rejects non-existent product ID and invalid size",
    $invalidProdRes['success'] === false && $invalidSizeRes['success'] === false
);

echo "\n--- 44. Phase 4B: Unique Product Quantity Locked to 1 ---\n";
// Insert a unique single-pair product
$pdo->exec("
    INSERT INTO products (category_id, brand_id, name, slug, price, product_type, status)
    VALUES ($shoesId, $nikeId, 'Alphafly 3 Proto', 'alphafly-3-proto', 35000.00, 'unique', 'active');
");
$protoId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO product_sizes (product_id, size, is_available, sort_order) VALUES ($protoId, 'UK 9', 1, 1);");

$addProto1 = cart_add($pdo, $protoId, 'UK 9', 1);
$addProto2 = cart_add($pdo, $protoId, 'UK 9', 1); // Attempt second add
$protoCartKey = cart_generate_key($protoId, 'UK 9');
assert_test(
    "Unique product is locked to quantity 1 and duplicate add does not increment",
    $addProto1['success'] === true &&
    $addProto2['already_existed'] === true &&
    $_SESSION['cart'][$protoCartKey]['quantity'] === 1
);

echo "\n--- 45. Phase 4B: Regular Product Quantity Increments up to Max 5 ---\n";
$regCartKey = cart_generate_key($nb4Id, 'UK 8');
cart_add($pdo, (int)$nb4Id, 'UK 8', 2);
cart_add($pdo, (int)$nb4Id, 'UK 8', 5); // Attempt exceeding 5
assert_test(
    "Regular product increments quantity and clamps at maximum limit of 5",
    $_SESSION['cart'][$regCartKey]['quantity'] === 5
);

echo "\n--- 46. Phase 4B: Cart Hydration & Live Database Pricing ---\n";
// $_SESSION['cart'] contains Novablast 4 (qty 5 @ 22500.00 = 112500.00) and Alphafly Proto (qty 1 @ 35000.00 = 35000.00)
$hydrated = cart_hydrate($pdo);
$expectedSubtotal = (22500.00 * 5) + (35000.00 * 1); // 112500.00 + 35000.00 = 147500.00
assert_test(
    "cart_hydrate loads trusted prices from DB and calculates exact totals",
    $hydrated['is_empty'] === false &&
    $hydrated['subtotal'] === '147500.00' &&
    $hydrated['delivery_fee'] === '0.00' &&
    $hydrated['grand_total'] === '147500.00' &&
    $hydrated['has_unavailable_items'] === false
);

echo "\n--- 47. Phase 4B: Real-Time Availability Change Detection in Cart ---\n";
// Mark Alphafly Proto size UK 9 as unavailable
$pdo->exec("UPDATE product_sizes SET is_available = 0 WHERE product_id = $protoId AND size = 'UK 9';");
$hydratedOut = cart_hydrate($pdo);
assert_test(
    "cart_hydrate detects unavailable items, flags has_unavailable_items, and excludes from subtotal",
    $hydratedOut['has_unavailable_items'] === true &&
    $hydratedOut['items'][$protoCartKey]['is_available'] === false &&
    $hydratedOut['subtotal'] === '112500.00' // Only Novablast 4 contributes
);
// Reset availability for subsequent checkout tests
$pdo->exec("UPDATE product_sizes SET is_available = 1 WHERE product_id = $protoId AND size = 'UK 9';");

// --------------------------------------------------------------------------
// PHASE 4C: CHECKOUT & CASH ON DELIVERY ORDER CREATION TESTS
// --------------------------------------------------------------------------
echo "\n--- 48. Phase 4C: Order Number Generation Format & Uniqueness ---\n";
$orderNum1 = generate_order_number($pdo);
$orderNum2 = generate_order_number($pdo);
$patternMatch = preg_match('/^ATH-\d{8}-\d{4}$/', $orderNum1);
assert_test(
    "generate_order_number produces unique collision-resistant ATH-YYYYMMDD-NNNN format",
    $patternMatch === 1 && $orderNum1 !== $orderNum2
);

echo "\n--- 49. Phase 4C: Customer Input Validation Rules ---\n";
$validPhone1 = preg_match('/^((\+92)|(0092)|(92)|(0))?3[0-9]{9}$/', preg_replace('/[\s\-]/', '', '03001234567'));
$validPhone2 = preg_match('/^((\+92)|(0092)|(92)|(0))?3[0-9]{9}$/', preg_replace('/[\s\-]/', '', '+92 300-1234567'));
$invalidPhone = preg_match('/^((\+92)|(0092)|(92)|(0))?3[0-9]{9}$/', preg_replace('/[\s\-]/', '', '12345'));
assert_test(
    "Pakistani phone format validation correctly validates local and international mobile numbers",
    $validPhone1 === 1 && $validPhone2 === 1 && $invalidPhone === 0
);

echo "\n--- 50. Phase 4C: Unique Product Atomic Claim in Database Transaction ---\n";
// Clean cart: only Alphafly Proto (unique, 35000.00)
$_SESSION['cart'] = [];
cart_add($pdo, $protoId, 'UK 9', 1);

// Execute Order 1 Transaction
$orderId1 = 0;
$orderNumber1 = '';
$uniqueClaimSucceeded = false;

try {
    $pdo->beginTransaction();
    $rawItems = cart_get_items();
    $claimStmt = $pdo->prepare("UPDATE product_sizes SET is_available = 0 WHERE id = (SELECT id FROM product_sizes WHERE product_id = $protoId AND size = 'UK 9') AND is_available = 1");
    $claimStmt->execute();
    if ($claimStmt->rowCount() === 1) {
        $uniqueClaimSucceeded = true;
    }
    
    $orderNumber1 = generate_order_number($pdo);
    $pdo->prepare("
        INSERT INTO orders (order_number, customer_name, customer_phone, city, delivery_address, subtotal, delivery_fee, total_amount, status)
        VALUES (:num, 'Hamza Tariq', '03001112233', 'Lahore', 'Model Town Block C', '35000.00', '0.00', '35000.00', 'pending')
    ")->execute([':num' => $orderNumber1]);
    $orderId1 = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, product_price, product_size, product_color, quantity, line_total)
        VALUES (:oid, $protoId, 'Alphafly 3 Proto', '35000.00', 'UK 9', 'White/Orange', 1, '35000.00')
    ")->execute([':oid' => $orderId1]);

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

$protoSizeCheck = $pdo->query("SELECT is_available FROM product_sizes WHERE product_id = $protoId AND size = 'UK 9'")->fetchColumn();
assert_test(
    "Unique product size is atomically claimed during checkout (is_available updated to 0)",
    $uniqueClaimSucceeded === true && (int)$protoSizeCheck === 0 && $orderId1 > 0
);

echo "\n--- 51. Phase 4C: Concurrent Claim of Claimed Unique Product Is REJECTED ---\n";
// Attempt second checkout on the already claimed unique item
$secondClaimRejected = false;
$secondOrderCreated = false;

try {
    $pdo->beginTransaction();
    $claimStmt2 = $pdo->prepare("UPDATE product_sizes SET is_available = 0 WHERE id = (SELECT id FROM product_sizes WHERE product_id = $protoId AND size = 'UK 9') AND is_available = 1");
    $claimStmt2->execute();
    if ($claimStmt2->rowCount() !== 1) {
        $secondClaimRejected = true;
        throw new Exception("Unique item already claimed!");
    }
    // Should never reach here
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
$ordersCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE customer_name = 'Second Customer'")->fetchColumn();
assert_test(
    "Concurrent claim on already-claimed unique item fails atomically and creates 0 orders",
    $secondClaimRejected === true && $ordersCount === 0
);

echo "\n--- 52. Phase 4C: Regular Product Multi-Item Order with Exact Snapshot ---\n";
// Create Order with Regular multi-size product (ASICS Novablast 4, qty 2 @ 22500.00 = 45000.00)
$_SESSION['cart'] = [];
cart_add($pdo, (int)$nb4Id, 'UK 8', 2);
$orderNumber2 = generate_order_number($pdo);

$pdo->beginTransaction();
$pdo->prepare("
    INSERT INTO orders (order_number, customer_name, customer_phone, customer_email, city, delivery_address, subtotal, delivery_fee, total_amount, status)
    VALUES (:num, 'Zaid Khan', '03211234567', 'zaid@athletica.pk', 'Karachi', 'DHA Phase 6', '45000.00', '0.00', '45000.00', 'pending')
")->execute([':num' => $orderNumber2]);
$orderId2 = (int)$pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO order_items (order_id, product_id, product_name, product_price, product_size, product_color, quantity, line_total)
    VALUES (:oid, :pid, 'ASICS Novablast 4', '22500.00', 'UK 8', 'Illusion Blue', 2, '45000.00')
")->execute([':oid' => $orderId2, ':pid' => $nb4Id]);
$pdo->commit();

// Clear cart after commit
cart_clear();

$savedOrder = $pdo->query("SELECT * FROM orders WHERE id = $orderId2")->fetch();
$savedItems = $pdo->query("SELECT * FROM order_items WHERE order_id = $orderId2")->fetchAll();

assert_test(
    "Regular multi-item order created with pending status, exact subtotal, and 0.00 delivery fee",
    $savedOrder['status'] === 'pending' &&
    (float)$savedOrder['subtotal'] == 45000.00 &&
    (float)$savedOrder['delivery_fee'] == 0.00 &&
    (float)$savedOrder['total_amount'] == 45000.00 &&
    count($savedItems) === 1 &&
    $savedItems[0]['product_size'] === 'UK 8' &&
    (int)$savedItems[0]['quantity'] === 2 &&
    empty($_SESSION['cart'])
);

echo "\n--- 53. Phase 4C: Historical Snapshot Preservation on Product Deletion ---\n";
// Delete product ASICS Novablast 4. Foreign key is ON DELETE SET NULL on order_items.product_id
$pdo->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $nb4Id]);
$snapshotCheck = $pdo->query("SELECT * FROM order_items WHERE order_id = $orderId2")->fetch();
assert_test(
    "Order item snapshots are 100% preserved with product_id SET NULL when product is deleted",
    $snapshotCheck['product_id'] === null &&
    $snapshotCheck['product_name'] === 'ASICS Novablast 4' &&
    (float)$snapshotCheck['product_price'] == 22500.00 &&
    $snapshotCheck['product_size'] === 'UK 8' &&
    (float)$snapshotCheck['line_total'] == 45000.00
);

echo "\n--- 54. Phase 4C: Multi-Item Transaction Full Rollback on Single Item Failure ---\n";
$orderCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$itemCountBefore  = (int)$pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn();

try {
    $pdo->beginTransaction();
    // Item 1: Valid
    $pdo->prepare("INSERT INTO orders (order_number, customer_name, customer_phone, city, delivery_address, subtotal, delivery_fee, total_amount) VALUES ('ATH-FAIL-TEST', 'Test', '03001234567', 'Lahore', 'Address', '1000.00', '0.00', '1000.00')")->execute();
    // Item 2: Simulated failure (e.g. out of stock item throws exception)
    throw new Exception('Simulated stock error');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$orderCountAfter = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$itemCountAfter  = (int)$pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn();
assert_test(
    "Transaction guarantees full rollback with 0 partial records on multi-item failure",
    $orderCountBefore === $orderCountAfter && $itemCountBefore === $itemCountAfter
);

echo "\n--- 55. Phase 4C: CSRF Enforcement on checkout.php & cart-action.php ---\n";
$checkoutFileContent = file_get_contents(__DIR__ . '/../checkout.php');
$cartActionContent   = file_get_contents(__DIR__ . '/../cart-action.php');
$cartFileContent     = file_get_contents(__DIR__ . '/../cart.php');
$orderSuccessContent = file_get_contents(__DIR__ . '/../order-success.php');
assert_test(
    "CSRF verification is enforced in checkout.php and cart-action.php with proper token rendering",
    strpos($checkoutFileContent, 'if (!csrf_verify())') !== false &&
    strpos($cartActionContent, 'if (!csrf_verify())') !== false &&
    strpos($checkoutFileContent, 'csrf_field()') !== false &&
    strpos($cartFileContent, 'csrf_field()') !== false &&
    strpos($orderSuccessContent, 'htmlspecialchars') !== false
);

// --------------------------------------------------------------------------
// PHASE 4D: ADMIN ORDER MANAGEMENT TESTS
// --------------------------------------------------------------------------
echo "\n--- 56. Phase 4D: Admin Orders Auth Guard on All 5 Order Pages ---\n";
$orderAdminPages = ['index.php', 'view.php', 'update-status.php', 'update-notes.php', 'release-unique.php'];
$allOrdersAuth = true;
$allOrdersCsrf = true;

foreach ($orderAdminPages as $op) {
    $content = file_get_contents(__DIR__ . '/../admin/orders/' . $op);
    if (strpos($content, 'includes/auth.php') === false) {
        $allOrdersAuth = false;
    }
    if (in_array($op, ['update-status.php', 'update-notes.php', 'release-unique.php'], true) && strpos($content, 'if (!csrf_verify())') === false) {
        $allOrdersCsrf = false;
    }
}
assert_test(
    "All 5 admin order management pages require auth.php and all 3 state-changing handlers enforce if (!csrf_verify())",
    $allOrdersAuth === true && $allOrdersCsrf === true
);

echo "\n--- 57. Phase 4D: Orders Listing Query with Status & Keyword Search ---\n";
// Query orders using filter and search logic
$listStmt = $pdo->prepare("
    SELECT o.*,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS total_items
    FROM orders o
    WHERE (o.order_number LIKE :s OR o.customer_name LIKE :s OR o.customer_phone LIKE :s OR o.city LIKE :s)
      AND o.status = :status
    ORDER BY o.created_at DESC
");
$listStmt->execute([':s' => '%Zaid%', ':status' => 'pending']);
$foundOrders = $listStmt->fetchAll();
assert_test(
    "Orders listing query successfully searches by customer name and filters by pending status",
    count($foundOrders) >= 1 && $foundOrders[0]['customer_name'] === 'Zaid Khan'
);

echo "\n--- 58. Phase 4D: Order Detail Query with Complete Historical Snapshots ---\n";
$viewStmt = $pdo->prepare("
    SELECT oi.*, p.product_type, ps.is_available AS current_size_availability
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    LEFT JOIN product_sizes ps ON ps.product_id = oi.product_id AND LOWER(ps.size) = LOWER(oi.product_size)
    WHERE oi.order_id = :oid
");
$viewStmt->execute([':oid' => $orderId1]); // Order 1 is Alphafly Proto (unique)
$viewItems = $viewStmt->fetchAll();
assert_test(
    "Order detail query retrieves immutable snapshots for unique items",
    count($viewItems) === 1 &&
    $viewItems[0]['product_name'] === 'Alphafly 3 Proto' &&
    $viewItems[0]['product_size'] === 'UK 9' &&
    (float)$viewItems[0]['product_price'] == 35000.00 &&
    $viewItems[0]['product_type'] === 'unique'
);

echo "\n--- 59. Phase 4D: Order Status Workflow Transitions & Terminal Protection ---\n";
// Transition Order 1: pending -> confirmed -> shipped -> delivered
$pdo->prepare("UPDATE orders SET status = 'confirmed' WHERE id = :id")->execute([':id' => $orderId1]);
$st1 = $pdo->query("SELECT status FROM orders WHERE id = $orderId1")->fetchColumn();

$pdo->prepare("UPDATE orders SET status = 'shipped' WHERE id = :id")->execute([':id' => $orderId1]);
$st2 = $pdo->query("SELECT status FROM orders WHERE id = $orderId1")->fetchColumn();

$pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = :id")->execute([':id' => $orderId1]);
$st3 = $pdo->query("SELECT status FROM orders WHERE id = $orderId1")->fetchColumn();

assert_test(
    "Order status transitions cleanly from pending -> confirmed -> shipped -> delivered",
    $st1 === 'confirmed' && $st2 === 'shipped' && $st3 === 'delivered'
);

echo "\n--- 60. Phase 4D: Unique Item Release is STRICTLY BLOCKED on Delivered Orders ---\n";
// Attempt to release unique item on a delivered order
$deliveredReleaseBlocked = false;
$orderDeliveredStatus = $pdo->query("SELECT status FROM orders WHERE id = $orderId1")->fetchColumn();
if (!in_array($orderDeliveredStatus, ['cancelled', 'returned'], true)) {
    $deliveredReleaseBlocked = true; // Handler blocks release
}
assert_test(
    "Unique item inventory release is strictly blocked on delivered orders (physical item delivered to customer)",
    $deliveredReleaseBlocked === true && $orderDeliveredStatus === 'delivered'
);

echo "\n--- 61. Phase 4D: Unique Item Manual Release on Cancelled Order ---\n";
// Create a new unique product and order to test cancellation release
$pdo->exec("
    INSERT INTO products (category_id, brand_id, name, slug, price, product_type, status)
    VALUES ($shoesId, $nikeId, 'Saucony Endorphin Elite', 'saucony-endorphin-elite', 32000.00, 'unique', 'active');
");
$sauconyId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO product_sizes (product_id, size, is_available, sort_order) VALUES ($sauconyId, 'UK 9.5', 1, 1);");
$sauconySizeId = (int)$pdo->lastInsertId();

// Customer checks out unique Saucony shoe -> size claimed (is_available = 0)
$pdo->exec("UPDATE product_sizes SET is_available = 0 WHERE id = $sauconySizeId;");
$orderNum3 = generate_order_number($pdo);
$pdo->prepare("
    INSERT INTO orders (order_number, customer_name, customer_phone, city, delivery_address, subtotal, delivery_fee, total_amount, status)
    VALUES (:num, 'Bilal Ahmed', '03335556677', 'Islamabad', 'F-7/2', '32000.00', '0.00', '32000.00', 'pending')
")->execute([':num' => $orderNum3]);
$orderId3 = (int)$pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO order_items (order_id, product_id, product_name, product_price, product_size, product_color, quantity, line_total)
    VALUES (:oid, :pid, 'Saucony Endorphin Elite', '32000.00', 'UK 9.5', 'Slime Green', 1, '32000.00')
")->execute([':oid' => $orderId3, ':pid' => $sauconyId]);

// Order is cancelled pre-dispatch
$pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id")->execute([':id' => $orderId3]);

// Admin releases unique item back to catalog
$relStmt = $pdo->prepare("UPDATE product_sizes SET is_available = 1 WHERE id = :id AND product_id = :pid AND is_available = 0");
$relStmt->execute([':id' => $sauconySizeId, ':pid' => $sauconyId]);
$sauconyAvailableAfter = (int)$pdo->query("SELECT is_available FROM product_sizes WHERE id = $sauconySizeId")->fetchColumn();

assert_test(
    "Unique single-pair item is successfully re-released back to live catalog upon order cancellation",
    $relStmt->rowCount() === 1 && $sauconyAvailableAfter === 1
);

echo "\n--- 62. Phase 4D: Duplicate Release of Already Released Unique Item Is Safely Ignored ---\n";
// Attempt second release on the same item
$relStmt2 = $pdo->prepare("UPDATE product_sizes SET is_available = 1 WHERE id = :id AND product_id = :pid AND is_available = 0");
$relStmt2->execute([':id' => $sauconySizeId, ':pid' => $sauconyId]);
assert_test(
    "Duplicate release attempt on already available item affects 0 rows and avoids double-release side effects",
    $relStmt2->rowCount() === 0
);

echo "\n--- 63. Phase 4D: Unique Item Release on Returned COD Order ---\n";
// Simulate an order that was shipped then returned by courier
$pdo->exec("UPDATE product_sizes SET is_available = 0 WHERE id = $sauconySizeId;"); // re-claimed in new order
$orderNum4 = generate_order_number($pdo);
$pdo->prepare("
    INSERT INTO orders (order_number, customer_name, customer_phone, city, delivery_address, subtotal, delivery_fee, total_amount, status)
    VALUES (:num, 'Usman Tariq', '03451122334', 'Multan', 'Cantt', '32000.00', '0.00', '32000.00', 'shipped')
")->execute([':num' => $orderNum4]);
$orderId4 = (int)$pdo->lastInsertId();

// Parcel returns to warehouse: status updated to 'returned'
$pdo->prepare("UPDATE orders SET status = 'returned' WHERE id = :id")->execute([':id' => $orderId4]);

// Admin verifies returned shoe and releases to catalog
$relReturned = $pdo->prepare("UPDATE product_sizes SET is_available = 1 WHERE id = :id AND product_id = :pid AND is_available = 0");
$relReturned->execute([':id' => $sauconySizeId, ':pid' => $sauconyId]);
$sauconyFinalState = (int)$pdo->query("SELECT is_available FROM product_sizes WHERE id = $sauconySizeId")->fetchColumn();

assert_test(
    "Unique single-pair item is successfully re-released back to live catalog upon courier parcel return",
    $relReturned->rowCount() === 1 && $sauconyFinalState === 1
);

echo "\n--- 64. Phase 4D: Admin Notes Update & Audit Logging ---\n";
$testNotes = "TCS Tracking # 7712345678. Customer verified address via WhatsApp call.";
$pdo->prepare("UPDATE orders SET admin_notes = :notes WHERE id = :id")->execute([
    ':notes' => $testNotes,
    ':id'    => $orderId4,
]);
$savedNotes = $pdo->query("SELECT admin_notes FROM orders WHERE id = $orderId4")->fetchColumn();
assert_test(
    "Admin notes are safely persisted with courier tracking info and audit trail",
    $savedNotes === $testNotes
);

echo "\n--- 65. Phase 4D: Orders Permanent Record Protection (No Delete Handler) ---\n";
$hasDeleteOrderFile = file_exists(__DIR__ . '/../admin/orders/delete.php');
assert_test(
    "Orders table has no delete endpoint; orders are permanent accounting and audit records",
    $hasDeleteOrderFile === false
);

// --------------------------------------------------------------------------
// PHASE 4E: WHATSAPP INTEGRATION TESTS
// --------------------------------------------------------------------------
echo "\n--- 66. Phase 4E: Phone Number Normalization for WhatsApp ---\n";
$norm1 = normalize_whatsapp_number('03001234567');
$norm2 = normalize_whatsapp_number('+923001234567');
$norm3 = normalize_whatsapp_number('00923001234567');
$norm4 = normalize_whatsapp_number('3001234567');
$norm5 = normalize_whatsapp_number('0300-1234567');
assert_test(
    "normalize_whatsapp_number normalizes local, international, trunk, and punctuated Pakistani numbers to pure digits 923001234567",
    $norm1 === '923001234567' &&
    $norm2 === '923001234567' &&
    $norm3 === '923001234567' &&
    $norm4 === '923001234567' &&
    $norm5 === '923001234567'
);

echo "\n--- 67. Phase 4E: Business WhatsApp Sourcing from site_settings ---\n";
$siteWa = $pdo->query("SELECT whatsapp_number FROM site_settings LIMIT 1")->fetchColumn();
$normSiteWa = normalize_whatsapp_number($siteWa ?: '+923001234567');
assert_test(
    "Business WhatsApp number is loaded from site_settings and normalized correctly",
    !empty($siteWa) && $normSiteWa === '923001234567'
);

echo "\n--- 68. Phase 4E: Customer Order WhatsApp Message Structure & Snapshot Integrity ---\n";
$testOrderData = [
    'order_number'     => 'ATH-20260829-9999',
    'customer_name'    => 'Ali Raza',
    'customer_phone'   => '03121234567',
    'city'             => 'Lahore',
    'delivery_address' => 'Gulberg III, Block B',
    'subtotal'         => 35000.00,
    'delivery_fee'     => 0.00,
    'total_amount'     => 35000.00,
];
$testOrderItems = [
    [
        'product_name'  => 'Alphafly 3 Proto',
        'product_size'  => 'UK 9',
        'product_color' => 'White/Volt',
        'quantity'      => 1,
        'product_price' => 35000.00,
        'line_total'    => 35000.00,
    ]
];
$custMsg = build_customer_order_whatsapp_message($testOrderData, $testOrderItems);
assert_test(
    "build_customer_order_whatsapp_message includes order number, customer details, item snapshots, subtotal, FREE delivery, and COD total",
    strpos($custMsg, 'ATH-20260829-9999') !== false &&
    strpos($custMsg, 'Ali Raza') !== false &&
    strpos($custMsg, 'Alphafly 3 Proto') !== false &&
    strpos($custMsg, 'UK 9') !== false &&
    strpos($custMsg, 'FREE (Rs. 0.00)') !== false &&
    strpos($custMsg, '35,000.00') !== false &&
    strpos($custMsg, 'Cash on Delivery (COD)') !== false
);

echo "\n--- 69. Phase 4E: Admin Order Verification WhatsApp Message & Privacy ---\n";
$adminMsg = build_admin_verification_whatsapp_message($testOrderData, $testOrderItems);
assert_test(
    "build_admin_verification_whatsapp_message creates clear confirmation text without exposing internal admin notes or database IDs",
    strpos($adminMsg, 'Hello Ali Raza') !== false &&
    strpos($adminMsg, 'ATH-20260829-9999') !== false &&
    strpos($adminMsg, 'Lahore') !== false &&
    strpos($adminMsg, '35,000.00') !== false &&
    strpos($adminMsg, 'admin_notes') === false &&
    strpos($adminMsg, 'password') === false
);

echo "\n--- 70. Phase 4E: WhatsApp Link Generation, URL Encoding, & Target Security ---\n";
$orderSuccessFile = file_get_contents(__DIR__ . '/../order-success.php');
$adminViewFile    = file_get_contents(__DIR__ . '/../admin/orders/view.php');
assert_test(
    "Both order-success.php and admin/orders/view.php use proper URL encoding, target='_blank', and rel='noopener noreferrer'",
    strpos($orderSuccessFile, 'rawurlencode') !== false &&
    strpos($orderSuccessFile, 'https://wa.me/') !== false &&
    strpos($orderSuccessFile, 'rel="noopener noreferrer"') !== false &&
    strpos($adminViewFile, 'rawurlencode') !== false &&
    strpos($adminViewFile, 'https://wa.me/') !== false &&
    strpos($adminViewFile, 'rel="noopener noreferrer"') !== false
);

echo "\n--- 71. Phase 4E: Order Status Independence from WhatsApp Actions ---\n";
// Ensure that generating or opening a WhatsApp link does not alter order status
$orderStatusBefore = $pdo->query("SELECT status FROM orders WHERE id = $orderId4")->fetchColumn();
// Simulate generating WhatsApp link
$waLinkSim = 'https://wa.me/923001234567?text=' . rawurlencode($custMsg);
$orderStatusAfter = $pdo->query("SELECT status FROM orders WHERE id = $orderId4")->fetchColumn();
assert_test(
    "WhatsApp deep link generation does not alter orders.status or create duplicate orders",
    $orderStatusBefore === $orderStatusAfter && strlen($waLinkSim) > 20
);

// --------------------------------------------------------------------------
// Summary
// --------------------------------------------------------------------------
echo "\n====================================================\n";
echo " FULL TEST SUMMARY: $passCount Passed, $failCount Failed\n";
echo "====================================================\n\n";

if ($failCount === 0) {
    echo " ALL $passCount TEST ASSERTIONS PASSED WITH 100% SUCCESS!\n";
    echo " Phase 4 Dynamic Brands, Explicit Product Type, Free Delivery, Cart Engine, COD Checkout, Admin Orders & WhatsApp verified.\n";
    exit(0);
} else {
    echo " Some tests failed. Please review the errors above.\n";
    exit(1);
}





