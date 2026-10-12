<?php
/**
 * ATHLETICA — Storefront Header Partial
 * Initializes database, fetches site settings, and renders HTML head & announcement bar
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/cart-helper.php';

// Fetch global site settings (or sensible defaults)
try {
    $settingsStmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
    $siteSettings = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $siteSettings = [];
}

$siteName = $siteSettings['site_name'] ?? 'ATHLETICA';
$siteTagline = $siteSettings['tagline'] ?? 'FOCUS | POWER | PERFORMANCE';
$currencySymbol = $siteSettings['currency_symbol'] ?? 'Rs.';
$whatsappNumber = $siteSettings['whatsapp_number'] ?? '+923001234567';

// Clean WhatsApp number for links (digits and plus only)
$cleanWhatsapp = preg_replace('/[^\d+]/', '', $whatsappNumber);

// Page metadata defaults
$pageTitle = isset($pageTitle) ? $pageTitle . ' — ' . $siteName : $siteName . ' — ' . $siteTagline;
$pageDescription = $pageDescription ?? 'Original imported shoes, sneakers, caps, shirts, and athletic wear in Pakistan. Cash on Delivery nationwide.';
$pageImage = $pageImage ?? 'assets/images/og-default.jpg';
$cartCount = cart_count_items(true);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">

    <!-- OpenGraph Social Metadata -->
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">

    <!-- Typography Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- CSS Design System -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/pages.css">
</head>
<body>

<!-- 1. Top Scrolling Line (Ticker/Marquee) -->
<div class="announcement-ticker">
    <div class="ticker-track">
        <div class="ticker-content">
            <span>🚚 CASH ON DELIVERY NATIONWIDE</span>
            <span class="ticker-dot">•</span>
            <span>📦 OPEN & CHECK PARCEL BEFORE PAYMENT</span>
            <span class="ticker-dot">•</span>
            <span>👟 100% ORIGINAL IMPORTED SHOES & SNEAKERS</span>
            <span class="ticker-dot">•</span>
            <span>🩴 PREMIUM ATHLETIC SLIDERS</span>
            <span class="ticker-dot">•</span>
            <span>👕 ORIGINAL IMPORTED SHIRTS & TEES</span>
            <span class="ticker-dot">•</span>
            <span>👖 ATHLETIC TROUSERS & TRACK PANTS</span>
            <span class="ticker-dot">•</span>
            <span>🧢 STYLISH IMPORTED CAPS</span>
            <span class="ticker-dot">•</span>
            <span>⚡ FAST 2-4 DAYS DISPATCH ACROSS PAKISTAN</span>
            <span class="ticker-dot">•</span>
        </div>
        <div class="ticker-content" aria-hidden="true">
            <span>🚚 CASH ON DELIVERY NATIONWIDE</span>
            <span class="ticker-dot">•</span>
            <span>📦 OPEN & CHECK PARCEL BEFORE PAYMENT</span>
            <span class="ticker-dot">•</span>
            <span>👟 100% ORIGINAL IMPORTED SHOES & SNEAKERS</span>
            <span class="ticker-dot">•</span>
            <span>🩴 PREMIUM ATHLETIC SLIDERS</span>
            <span class="ticker-dot">•</span>
            <span>👕 ORIGINAL IMPORTED SHIRTS & TEES</span>
            <span class="ticker-dot">•</span>
            <span>👖 ATHLETIC TROUSERS & TRACK PANTS</span>
            <span class="ticker-dot">•</span>
            <span>🧢 STYLISH IMPORTED CAPS</span>
            <span class="ticker-dot">•</span>
            <span>⚡ FAST 2-4 DAYS DISPATCH ACROSS PAKISTAN</span>
            <span class="ticker-dot">•</span>
        </div>
    </div>
</div>
