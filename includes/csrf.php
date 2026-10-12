<?php
/**
 * ATHLETICA — Reusable CSRF Protection Helper
 *
 * Provides functions to generate, render, and verify Cross-Site Request
 * Forgery (CSRF) tokens across all admin and state-changing forms.
 *
 * Usage in forms:
 *   <?= csrf_field(); ?>
 *
 * Usage in POST handlers:
 *   if (!csrf_verify()) {
 *       // handle invalid token
 *   }
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

/**
 * Returns the current CSRF token from the session, generating one if absent.
 *
 * @return string 64-character hexadecimal token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generates an HTML hidden input tag containing the CSRF token.
 *
 * @return string HTML <input> tag with escaped token
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verifies a submitted CSRF token against the stored session token using
 * a timing-attack-safe comparison.
 *
 * @param string|null $submittedToken Optional token string; defaults to $_POST['csrf_token']
 * @return bool True if valid, false otherwise
 */
function csrf_verify(?string $submittedToken = null): bool
{
    if ($submittedToken === null) {
        $submittedToken = $_POST['csrf_token'] ?? '';
    }

    if (!isset($_SESSION['csrf_token']) || empty($submittedToken)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}
