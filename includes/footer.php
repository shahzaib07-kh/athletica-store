<?php
/**
 * ATHLETICA — Storefront Footer Partial
 * Dynamic footer, site settings, social channels, and script imports
 */

$supportEmail = $siteSettings['support_email'] ?? 'support@athletica.pk';
$contactPhone = $siteSettings['contact_phone'] ?? '+92 300 1234567';
$storeAddress = $siteSettings['store_address'] ?? 'Lahore, Pakistan';
$footerText = $siteSettings['footer_text'] ?? 'ATHLETICA — Premium imported athletic footwear and sportswear.';
?>

<!-- Storefront Footer -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand Column -->
            <div class="footer-col">
                <a href="index.php" class="brand-wrapper" style="margin-bottom: 14px;">
                    <span class="brand-logo"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="brand-tagline"><?= htmlspecialchars($siteTagline, ENT_QUOTES, 'UTF-8') ?></span>
                </a>
                <p style="font-size: 0.85rem; max-width: 320px;"><?= htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') ?></p>
                
                <div style="margin-top: 18px; display: flex; gap: 10px;">
                    <a href="https://wa.me/<?= urlencode($cleanWhatsapp) ?>" target="_blank" rel="noopener noreferrer" 
                       class="icon-btn" title="WhatsApp" style="color: var(--whatsapp-green);">
                        💬
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h4>Explore</h4>
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="shop.php">All Products</a>
                    <a href="about.php">About Us</a>
                    <a href="contact.php">Contact Us</a>
                </div>
            </div>

            <!-- Categories -->
            <div class="footer-col">
                <h4>Categories</h4>
                <div class="footer-links">
                    <?php if (!empty($navCategories)): ?>
                        <?php foreach (array_slice($navCategories, 0, 5) as $fCat): ?>
                            <a href="category.php?slug=<?= urlencode($fCat['slug']) ?>">
                                <?= htmlspecialchars($fCat['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <a href="shop.php">Sneakers & Shoes</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact & Help -->
            <div class="footer-col">
                <h4>Customer Support</h4>
                <div class="footer-links">
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">
                        📍 <?= htmlspecialchars($storeAddress, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">
                        📞 <a href="tel:<?= urlencode($contactPhone) ?>"><?= htmlspecialchars($contactPhone, ENT_QUOTES, 'UTF-8') ?></a>
                    </p>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">
                        💬 WhatsApp: <a href="https://wa.me/<?= urlencode($cleanWhatsapp) ?>" target="_blank" style="color: var(--whatsapp-green);"><?= htmlspecialchars($whatsappNumber, ENT_QUOTES, 'UTF-8') ?></a>
                    </p>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">
                        ✉️ <?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</span>
            <span>COD Available Across Pakistan | Genuine Imported Athletic Gear</span>
        </div>
    </div>
</footer>

<!-- JavaScript Files -->
<script src="assets/js/main.js"></script>
<script src="assets/js/gallery.js"></script>

</body>
</html>
