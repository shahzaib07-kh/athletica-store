<?php
/**
 * ATHLETICA — About Us Page
 * FOCUS | POWER | PERFORMANCE
 */

$pageTitle = 'About Us — ATHLETICA';
$pageDescription = 'Learn about ATHLETICA, our passion for genuine imported athletic footwear, premium sportswear, and customer-first service across Pakistan.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="section-padding">
    <div class="container" style="max-width: 860px;">
        <!-- Breadcrumbs -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb">
            <a href="index.php">Home</a>
            <span class="breadcrumb-separator">/</span>
            <span style="color: #ffffff;">About Us</span>
        </nav>

        <div class="section-header">
            <div>
                <h1 class="section-title">About ATHLETICA</h1>
                <p class="section-subtitle">FOCUS | POWER | PERFORMANCE</p>
            </div>
        </div>

        <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 36px; display: flex; flex-direction: column; gap: 24px; font-size: 1rem; line-height: 1.8;">
            <p>
                Welcome to <strong>ATHLETICA</strong>, your premier destination for genuine, handpicked imported athletic footwear, high-performance running shoes, limited-edition sneakers, and premium sportswear in Pakistan.
            </p>

            <h2 style="font-size: 1.35rem; text-transform: uppercase; color: #fff; margin-top: 8px;">Our Core Philosophy</h2>
            <p>
                At ATHLETICA, we believe that serious athletes, runners, and sneaker enthusiasts deserve authentic, top-tier performance gear without compromise. We source verified original pairs from trusted global distribution channels to ensure every item in our catalog meets strict standards of quality, comfort, and durability.
            </p>

            <h2 style="font-size: 1.35rem; text-transform: uppercase; color: #fff; margin-top: 8px;">Exclusive Single-Pair & Curated Inventory</h2>
            <p>
                Many of our footwear highlights represent <strong>unique, single-pair imports</strong> in specific sizes. When you find your size in our catalog, you are getting an exclusive item ready for immediate nationwide dispatch.
            </p>

            <h2 style="font-size: 1.35rem; text-transform: uppercase; color: #fff; margin-top: 8px;">Why Shop With Us?</h2>
            <ul style="display: flex; flex-direction: column; gap: 12px; margin-left: 20px; list-style: disc;">
                <li><strong style="color: #fff;">100% Authentic Imports:</strong> Zero replicas. Every pair is inspected for genuine materials and construction.</li>
                <li><strong style="color: #fff;">Cash on Delivery:</strong> Shop with complete peace of mind and pay upon arrival at your doorstep.</li>
                <li><strong style="color: #fff;">Fast Dispatch:</strong> Orders are verified and dispatched within 24 hours.</li>
                <li><strong style="color: #fff;">Personalized WhatsApp Support:</strong> Connect directly with our team for size guidance, real photos, and tracking updates.</li>
            </ul>

            <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h3 style="font-size: 1.1rem; color: #fff; margin-bottom: 4px;">Have Questions?</h3>
                    <p style="font-size: 0.9rem;">Chat with us directly on WhatsApp for immediate assistance.</p>
                </div>
                <a href="https://wa.me/<?= urlencode($cleanWhatsapp) ?>?text=<?= urlencode('Hello ATHLETICA, I want to learn more about your products.') ?>" 
                   class="btn btn-whatsapp" target="_blank" rel="noopener noreferrer">
                    💬 Chat on WhatsApp
                </a>
            </div>
        </div>
    </div>
</main>

<?php 
require_once __DIR__ . '/includes/whatsapp-float.php';
require_once __DIR__ . '/includes/footer.php'; 
?>
