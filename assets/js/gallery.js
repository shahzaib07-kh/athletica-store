/**
 * ATHLETICA — Product Gallery JavaScript
 * Handles thumbnail click and main image switching
 */

document.addEventListener('DOMContentLoaded', () => {
    const mainImg = document.getElementById('gallery_main_image');
    const thumbBtns = document.querySelectorAll('.gallery-thumb-btn');

    if (mainImg && thumbBtns.length > 0) {
        thumbBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetSrc = btn.dataset.fullimage;
                if (targetSrc) {
                    mainImg.src = targetSrc;
                    thumbBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                }
            });
        });
    }
});
