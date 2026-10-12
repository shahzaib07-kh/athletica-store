<?php
/**
 * ATHLETICA — Contact Us Page
 * Direct WhatsApp chat, phone, email, and store location
 */

$pageTitle = 'Contact Us — ATHLETICA';
$pageDescription = 'Get in touch with ATHLETICA customer support via WhatsApp, phone, or email. We are here to assist with orders and inquiries.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="section-padding">
    <div class="container" style="max-width: 900px;">
        <!-- Breadcrumbs -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb">
            <a href="index.php">Home</a>
            <span class="breadcrumb-separator">/</span>
            <span style="color: #ffffff;">Contact Us</span>
        </nav>

        <div class="section-header">
            <div>
                <h1 class="section-title">Get In Touch</h1>
                <p class="section-subtitle">We're here to help with sizing, product availability, and order status</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 16px;">
            <!-- Contact Cards Column -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <!-- WhatsApp Highlight Card -->
                <div style="background: linear-gradient(135deg, #0d2818 0%, #05140b 100%); border: 1px solid #14532d; border-radius: var(--radius-md); padding: 24px;">
                    <div style="font-size: 1.5rem; margin-bottom: 8px;">💬</div>
                    <h3 style="font-size: 1.2rem; color: #fff; margin-bottom: 6px;">WhatsApp Support</h3>
                    <p style="font-size: 0.85rem; color: #86efac; margin-bottom: 16px;">Fastest response time for order placement, real photos, and sizing questions.</p>
                    <a href="https://wa.me/<?= urlencode($cleanWhatsapp) ?>?text=<?= urlencode('Hello ATHLETICA, I need assistance with an inquiry.') ?>" 
                       class="btn btn-whatsapp btn-block" target="_blank" rel="noopener noreferrer">
                        Chat on WhatsApp
                    </a>
                </div>

                <!-- Contact Details Card -->
                <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 24px; display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Phone / WhatsApp</div>
                        <div style="font-size: 1rem; color: #fff; font-weight: 600; margin-top: 2px;">
                            <a href="tel:<?= urlencode($contactPhone) ?>"><?= htmlspecialchars($contactPhone, ENT_QUOTES, 'UTF-8') ?></a>
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Email Inquiries</div>
                        <div style="font-size: 1rem; color: #fff; font-weight: 600; margin-top: 2px;">
                            <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Warehouse & Location</div>
                        <div style="font-size: 0.95rem; color: var(--text-secondary); margin-top: 2px;">
                            <?= htmlspecialchars($storeAddress, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ & Hours Column -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 28px; display: flex; flex-direction: column; gap: 20px;">
                <h3 style="font-size: 1.15rem; color: #fff; text-transform: uppercase; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">Frequently Asked Questions</h3>

                <div>
                    <h4 style="font-size: 0.95rem; color: #fff; font-weight: 700; margin-bottom: 4px;">How does Cash on Delivery work?</h4>
                    <p style="font-size: 0.85rem; line-height: 1.6;">You place your order online or via WhatsApp, we dispatch your package via courier, and you pay the exact invoice amount to the rider upon delivery.</p>
                </div>

                <div>
                    <h4 style="font-size: 0.95rem; color: #fff; font-weight: 700; margin-bottom: 4px;">Are all items genuine?</h4>
                    <p style="font-size: 0.85rem; line-height: 1.6;">Yes, all products sold at ATHLETICA are 100% authentic original imported items.</p>
                </div>

                <div>
                    <h4 style="font-size: 0.95rem; color: #fff; font-weight: 700; margin-bottom: 4px;">What are your operating hours?</h4>
                    <p style="font-size: 0.85rem; line-height: 1.6;">Our WhatsApp and customer care lines operate Monday to Saturday from 10:00 AM to 9:00 PM PKT.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
require_once __DIR__ . '/includes/whatsapp-float.php';
require_once __DIR__ . '/includes/footer.php'; 
?>
