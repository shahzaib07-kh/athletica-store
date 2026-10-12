<?php
/**
 * ATHLETICA — Admin Order Management: Release Unique Item Inventory Handler
 *
 * Handles POST requests to safely re-release a unique single-pair physical product
 * back to the live storefront catalog when an order is cancelled or returned,
 * enforcing atomic state updates, duplicate release protection, and audit logging.
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
$productId = (int)($_POST['product_id'] ?? 0);
$sizeId    = (int)($_POST['size_id'] ?? 0);

if ($orderId <= 0 || $productId <= 0 || $sizeId <= 0) {
    set_flash('error', 'Invalid inventory release request parameters.');
    header('Location: ' . (($orderId > 0) ? 'view.php?id=' . $orderId : 'index.php'));
    exit;
}

// 4. Verify order eligibility
$orderStmt = $pdo->prepare("SELECT id, order_number, status, admin_notes FROM orders WHERE id = :id LIMIT 1");
$orderStmt->execute([':id' => $orderId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    set_flash('error', 'Order not found.');
    header('Location: index.php');
    exit;
}

// STRICT RULE: Only cancelled or returned orders can release physical inventory
if (!in_array($order['status'], ['cancelled', 'returned'], true)) {
    set_flash('error', 'Only cancelled or returned orders are eligible for inventory release.');
    header('Location: view.php?id=' . $orderId);
    exit;
}

// 5. Verify product and size records
$prodStmt = $pdo->prepare("SELECT id, name, product_type FROM products WHERE id = :id LIMIT 1");
$prodStmt->execute([':id' => $productId]);
$product = $prodStmt->fetch(PDO::FETCH_ASSOC);

if (!$product || $product['product_type'] !== 'unique') {
    set_flash('error', 'Item is not a unique single-pair product.');
    header('Location: view.php?id=' . $orderId);
    exit;
}

$sizeStmt = $pdo->prepare("SELECT id, size, is_available FROM product_sizes WHERE id = :id AND product_id = :pid LIMIT 1");
$sizeStmt->execute([':id' => $sizeId, ':pid' => $productId]);
$sizeRecord = $sizeStmt->fetch(PDO::FETCH_ASSOC);

if (!$sizeRecord) {
    set_flash('error', 'Product size record not found.');
    header('Location: view.php?id=' . $orderId);
    exit;
}

// 6. Atomic Release Update (where is_available = 0)
$releaseStmt = $pdo->prepare("
    UPDATE product_sizes 
    SET is_available = 1 
    WHERE id = :id AND product_id = :pid AND is_available = 0
");
$releaseStmt->execute([':id' => $sizeId, ':pid' => $productId]);

if ($releaseStmt->rowCount() === 1) {
    // Append audit log entry to admin_notes
    $adminName = $_SESSION['admin_name'] ?? 'Admin';
    $logEntry  = sprintf(
        "\n[%s] Admin '%s' released unique item '%s' (Size: %s) back to catalog.",
        date('d-M-Y H:i'),
        $adminName,
        $product['name'],
        $sizeRecord['size']
    );
    $newNotes = trim(($order['admin_notes'] ?? '') . $logEntry);

    $notesStmt = $pdo->prepare("UPDATE orders SET admin_notes = :notes WHERE id = :id");
    $notesStmt->execute([':notes' => $newNotes, ':id' => $orderId]);

    set_flash('success', sprintf('Unique item "%s" (Size: %s) has been released back to the live catalog.', $product['name'], $sizeRecord['size']));
} else {
    set_flash('info', sprintf('Size "%s" for "%s" is already marked as available in the catalog.', $sizeRecord['size'], $product['name']));
}

header('Location: view.php?id=' . $orderId);
exit;
