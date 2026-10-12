<?php
/**
 * ATHLETICA — Shopping Cart Action Handler Controller
 *
 * Handles POST requests for adding items, direct "buy now" checkout transitions,
 * quantity updates, item removals, and emptying the cart with strict CSRF verification.
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

// 1. Reject non-POST requests immediately
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

// 2. Include core dependencies
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/cart-helper.php';

// 3. Strict CSRF verification
if (!csrf_verify()) {
    set_flash('error', 'Security token invalid or expired. Please try again.');
    header('Location: cart.php');
    exit;
}

// 4. Action Router
$action = trim($_POST['action'] ?? '');

switch ($action) {
    case 'add':
        $productId = (int)($_POST['product_id'] ?? 0);
        $size = trim($_POST['size'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 1);

        $res = cart_add($pdo, $productId, $size, $quantity);
        set_flash($res['success'] ? 'success' : 'error', $res['message']);
        header('Location: cart.php');
        exit;

    case 'buy_now':
        $productId = (int)($_POST['product_id'] ?? 0);
        $size = trim($_POST['size'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 1);

        $res = cart_add($pdo, $productId, $size, $quantity);
        if ($res['success']) {
            header('Location: checkout.php');
            exit;
        } else {
            set_flash('error', $res['message']);
            $fallback = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
            header('Location: ' . $fallback);
            exit;
        }

    case 'update':
    case 'update_quantity':
        $cartKey = trim($_POST['cart_key'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 1);

        $res = cart_update_quantity($pdo, $cartKey, $quantity);
        set_flash($res['success'] ? 'success' : 'error', $res['message']);
        header('Location: cart.php');
        exit;

    case 'remove':
        $cartKey = trim($_POST['cart_key'] ?? '');
        cart_remove($cartKey);
        set_flash('success', 'Item removed from your cart.');
        header('Location: cart.php');
        exit;

    case 'clear':
        cart_clear();
        set_flash('success', 'Your cart has been cleared.');
        header('Location: cart.php');
        exit;

    default:
        header('Location: cart.php');
        exit;
}
