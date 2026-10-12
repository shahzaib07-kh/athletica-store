<?php
/**
 * ATHLETICA — Authentication Guard
 *
 * Include this file at the TOP of any page that requires
 * a logged-in admin. If the visitor has no valid session,
 * they are redirected to the login page immediately.
 *
 * Usage (from any file inside admin/):
 *   require __DIR__ . '/../includes/auth.php';
 *
 * The following session variables are available after authentication:
 *   $_SESSION['admin_id']    — Admin's database ID
 *   $_SESSION['admin_email'] — Admin's email address
 *   $_SESSION['admin_name']  — Admin's display name
 */

// ============================================================
// SECURE SESSION CONFIGURATION
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    // Only allow session IDs from cookies (never from URL parameters)
    ini_set('session.use_only_cookies', '1');

    // Reject uninitialized session IDs
    ini_set('session.use_strict_mode', '1');

    // Prevent JavaScript from reading the session cookie (XSS protection)
    ini_set('session.cookie_httponly', '1');

    session_start();
}

// ============================================================
// AUTHENTICATION CHECK
// ============================================================
if (!isset($_SESSION['admin_id'])) {
    // Determine relative path to login.php
    $loginUrl = file_exists(__DIR__ . '/../admin/login.php') ? (
        str_contains($_SERVER['REQUEST_URI'] ?? '', '/athletica/admin/') ? '/athletica/admin/login.php' : '../login.php'
    ) : 'login.php';
    header('Location: ' . $loginUrl);
    exit;
}
