<?php
/**
 * ATHLETICA — Storefront Sticky Navbar Partial with Functional Premium Drawer
 * Features:
 * - Left: Hamburger (≡) & Search Icon (with expandable search input)
 * - Center: Bold stylized brush ATHLETICA brand linked to index.php
 * - Right: Functional Cart icon linked to cart.php with badge
 * - Off-Canvas Slide-Out Drawer with accordion dropdowns for Size and Brand, and social links
 */

$currentScript = basename($_SERVER['PHP_SELF']);
?>

<!-- Header & Sticky Navbar -->
<header class="site-header">
    <div class="container-fluid navbar-wrapper">
        <nav class="navbar" aria-label="Main Navigation">
            <!-- 1. LEFT SIDE: Hamburger menu icon + "ATHLETICA" text + Running Man logo -->
            <div class="nav-left">
                <!-- Hamburger Menu Trigger -->
                <button type="button" class="drawer-open-trigger" aria-label="Open Navigation Menu" title="Menu">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <!-- Brand Logo: ATHLETICA text first, followed immediately by running man symbol -->
                <a href="index.php" class="brand-group" aria-label="ATHLETICA Homepage">
                    <span class="brand-text">ATHLETICA</span>
                    <img src="assets/images/running-man.png" alt="ATHLETICA Running Man" class="brand-running-man">
                </a>
            </div>

            <!-- 2. CENTER: Clean search bar with compact black pill button -->
            <div class="nav-center">
                <form action="shop.php" method="GET" class="nav-search-pill-form">
                    <input type="text" name="search" placeholder="Search for products..." class="nav-search-pill-input" required>
                    <button type="submit" class="nav-search-pill-submit">SEARCH</button>
                </form>
            </div>

            <!-- 3. RIGHT SIDE: Shopping Cart ONLY with YELLOW badge (Clean & Prominent) -->
            <div class="nav-right">
                <a href="cart.php" class="nav-cart-btn" title="Shopping Cart" aria-label="Shopping Cart">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    <span class="cart-badge-yellow"><?= isset($cartCount) ? (int)$cartCount : 0 ?></span>
                </a>
            </div>
        </nav>
    </div>
    <!-- Mobile Search Bar -->
    <div class="mobile-search-bar">
        <form action="shop.php" method="GET" class="nav-search-pill-form">
            <input type="text" name="search" placeholder="Search for products" class="nav-search-pill-input" required>
            <button type="submit" class="nav-search-pill-submit">search</button>
        </form>
    </div>
</header>

<!-- Off-Canvas Slide-Out Navigation Drawer -->
<div class="drawer-backdrop" id="drawerBackdrop"></div>

<aside class="drawer-panel" id="drawerPanel" aria-label="Navigation Drawer">
    <!-- Drawer Header with Close (X) Button -->
    <div class="drawer-panel-header">
        <a href="index.php" class="brand-group drawer-brand">
            <span class="brand-text">ATHLETICA</span>
            <img src="assets/images/running-man.png" alt="ATHLETICA Running Man" class="brand-running-man">
        </a>
        <button type="button" class="drawer-close-btn" id="drawerCloseBtn" aria-label="Close Menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <!-- Drawer Navigation Links List -->
    <nav class="drawer-panel-nav">
        <!-- 1. HOME -->
        <a href="index.php" class="drawer-nav-item <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
            HOME
        </a>

        <!-- 2. SHOES -->
        <a href="shop.php?category=shoes" class="drawer-nav-item <?= (isset($_GET['category']) && $_GET['category'] === 'shoes') ? 'active' : '' ?>">
            SHOES
        </a>

        <!-- 3. SLIDERS -->
        <a href="shop.php?category=sliders" class="drawer-nav-item <?= (isset($_GET['category']) && $_GET['category'] === 'sliders') ? 'active' : '' ?>">
            SLIDERS
        </a>

        <!-- 4. CAPS -->
        <a href="shop.php?category=caps" class="drawer-nav-item <?= (isset($_GET['category']) && $_GET['category'] === 'caps') ? 'active' : '' ?>">
            CAPS
        </a>

        <!-- 5. SHIRTS -->
        <a href="shop.php?category=shirts" class="drawer-nav-item <?= (isset($_GET['category']) && $_GET['category'] === 'shirts') ? 'active' : '' ?>">
            SHIRTS
        </a>

        <!-- 6. TROUSERS -->
        <a href="shop.php?category=trousers" class="drawer-nav-item <?= (isset($_GET['category']) && $_GET['category'] === 'trousers') ? 'active' : '' ?>">
            TROUSERS
        </a>

        <!-- 7. FLASH SALE (Text in red) -->
        <a href="shop.php" class="drawer-nav-item text-red-sale">
            FLASH SALE
        </a>

        <!-- 8. SHOP BY SIZE (+) -> Expandable Accordion -->
        <div class="drawer-accordion">
            <button type="button" class="drawer-accordion-toggle" aria-expanded="false">
                <span>SHOP BY SIZE</span>
                <span class="accordion-icon">+</span>
            </button>
            <div class="drawer-accordion-content">
                <a href="shop.php?size=EU39" class="drawer-sub-link">EU 39</a>
                <a href="shop.php?size=EU40" class="drawer-sub-link">EU 40</a>
                <a href="shop.php?size=EU41" class="drawer-sub-link">EU 41</a>
                <a href="shop.php?size=EU42" class="drawer-sub-link">EU 42</a>
                <a href="shop.php?size=EU43" class="drawer-sub-link">EU 43</a>
                <a href="shop.php?size=EU44" class="drawer-sub-link">EU 44</a>
                <a href="shop.php?size=EU45" class="drawer-sub-link">EU 45</a>
                <a href="shop.php?size=EU46" class="drawer-sub-link">EU 46</a>
            </div>
        </div>

        <!-- 9. SHOP BY BRAND (+) -> Expandable Accordion (Nike, Adidas, ASICS, Hoka, QC) -->
        <div class="drawer-accordion">
            <button type="button" class="drawer-accordion-toggle" aria-expanded="false">
                <span>SHOP BY BRAND</span>
                <span class="accordion-icon">+</span>
            </button>
            <div class="drawer-accordion-content">
                <a href="shop.php?brand=nike" class="drawer-sub-link">Nike</a>
                <a href="shop.php?brand=adidas" class="drawer-sub-link">Adidas</a>
                <a href="shop.php?brand=asics" class="drawer-sub-link">ASICS</a>
                <a href="shop.php?brand=hoka" class="drawer-sub-link">Hoka</a>
                <a href="shop.php?brand=qc" class="drawer-sub-link">QC</a>
            </div>
        </div>

        <!-- 10. ABOUT US -->
        <a href="about.php" class="drawer-nav-item <?= ($currentScript === 'about.php') ? 'active' : '' ?>">
            ABOUT US
        </a>

        <!-- 11. CONTACT US -->
        <a href="contact.php" class="drawer-nav-item <?= ($currentScript === 'contact.php') ? 'active' : '' ?>">
            CONTACT US
        </a>
    </nav>

    <!-- Drawer Footer: Social Media Icons (WhatsApp, Facebook, TikTok) -->
    <div class="drawer-panel-footer">
        <div class="drawer-social-icons">
            <!-- WhatsApp -->
            <a href="https://wa.me/<?= urlencode($cleanWhatsapp ?? '923001234567') ?>" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="WhatsApp" title="WhatsApp">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                </svg>
            </a>

            <!-- Facebook -->
            <a href="#" class="social-icon-btn" aria-label="Facebook" title="Facebook">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
            </a>

            <!-- TikTok -->
            <a href="#" class="social-icon-btn" aria-label="TikTok" title="TikTok">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.24 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                </svg>
            </a>
        </div>
        <div class="drawer-footer-note">
            COD Across Pakistan
        </div>
    </div>
</aside>

