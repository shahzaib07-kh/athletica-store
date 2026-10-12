<?php
/**
 * ATHLETICA — Admin Logout
 *
 * Destroys the authenticated session and redirects to the login page.
 *
 * Steps:
 *   1. Start the session (so we can access it)
 *   2. Clear all session data
 *   3. Destroy the session
 *   4. Delete the session cookie
 *   5. Redirect to login page
 */

// Start session so we can destroy it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = [];

// Delete the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session on the server
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
