/**
 * ATHLETICA — Main Storefront JavaScript
 * Handles mobile drawer navigation, search toggle, and UI interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Off-Canvas Drawer Navigation
    const drawerOpenTrigger = document.querySelector('.drawer-open-trigger');
    const drawerPanel = document.getElementById('drawerPanel') || document.querySelector('.drawer-panel');
    const drawerBackdrop = document.getElementById('drawerBackdrop') || document.querySelector('.drawer-backdrop');
    const drawerCloseBtn = document.getElementById('drawerCloseBtn') || document.querySelector('.drawer-close-btn');

    function openDrawer() {
        if (drawerPanel) drawerPanel.classList.add('active');
        if (drawerBackdrop) drawerBackdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        if (drawerPanel) drawerPanel.classList.remove('active');
        if (drawerBackdrop) drawerBackdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (drawerOpenTrigger) {
        drawerOpenTrigger.addEventListener('click', openDrawer);
    }

    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', closeDrawer);
    }

    if (drawerCloseBtn) {
        drawerCloseBtn.addEventListener('click', closeDrawer);
    }

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDrawer();
            if (navSearchForm) navSearchForm.classList.remove('active');
        }
    });

    // 2. Top Bar Search Toggle
    const searchToggle = document.querySelector('.nav-search-toggle');
    const navSearchForm = document.getElementById('navSearchForm');
    const navSearchInput = document.getElementById('navSearchInput');

    if (searchToggle && navSearchForm) {
        searchToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            navSearchForm.classList.toggle('active');
            if (navSearchForm.classList.contains('active') && navSearchInput) {
                navSearchInput.focus();
            }
        });

        // Close search popup on click outside
        document.addEventListener('click', (e) => {
            if (!navSearchForm.contains(e.target) && !searchToggle.contains(e.target)) {
                navSearchForm.classList.remove('active');
            }
        });
    }

    // 3. Drawer Accordions ((+) Shop by Size and Shop by Brand)
    const accordionToggles = document.querySelectorAll('.drawer-accordion-toggle');
    accordionToggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            const parent = toggle.closest('.drawer-accordion');
            if (parent) {
                const isOpen = parent.classList.contains('open');
                parent.classList.toggle('open');
                toggle.setAttribute('aria-expanded', !isOpen);
            }
        });
    });

    // 2. Interactive Size Selector (on Product Detail Page)
    const sizeButtons = document.querySelectorAll('.size-option-btn:not(.disabled)');
    const selectedSizeInput = document.getElementById('selected_size_input');
    const whatsappOrderBtn = document.getElementById('whatsapp_direct_order_btn');

    if (sizeButtons.length > 0) {
        sizeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove selected class from all
                sizeButtons.forEach(b => b.classList.remove('selected'));
                // Add to clicked
                btn.classList.add('selected');
                
                const chosenSize = btn.dataset.size;
                if (selectedSizeInput) {
                    selectedSizeInput.value = chosenSize;
                }

                // Update WhatsApp direct order link if available
                if (whatsappOrderBtn && whatsappOrderBtn.dataset.baseurl && whatsappOrderBtn.dataset.product) {
                    const baseUrl = whatsappOrderBtn.dataset.baseurl;
                    const productName = encodeURIComponent(whatsappOrderBtn.dataset.product);
                    const msg = encodeURIComponent(`Hello ATHLETICA, I want to order "${decodeURIComponent(productName)}" (Size: ${chosenSize}) via Cash on Delivery.`);
                    whatsappOrderBtn.href = `${baseUrl}?text=${msg}`;
                }
            });
        });
    }
});
