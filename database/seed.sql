-- ============================================================
-- ATHLETICA — Sample Seed Data
-- Run AFTER schema.sql
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- 1. ADMIN (placeholder)
-- Password: 'ChangeMe123!' hashed with password_hash()
-- In production, generate a real hash via PHP:
--   echo password_hash('YourSecurePassword', PASSWORD_BCRYPT);
-- ============================================================
INSERT INTO `admins` (`username`, `email`, `password_hash`, `full_name`, `is_active`)
VALUES (
    'admin',
    'admin@athletica.pk',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- placeholder hash
    'ATHLETICA Admin',
    1
);

-- ============================================================
-- 2. CATEGORIES (6 initial categories)
-- ============================================================
INSERT INTO `categories` (`name`, `slug`, `description`, `sort_order`, `is_active`) VALUES
('Shoes',     'shoes',     'Imported athletic and casual shoes',           1, 1),
('Sneakers',  'sneakers',  'Premium imported sneakers from top brands',    2, 1),
('Caps',      'caps',      'Stylish caps and headwear',                    3, 1),
('Shirts',    'shirts',    'Athletic and casual shirts',                   4, 1),
('Trousers',  'trousers',  'Joggers, track pants, and trousers',          5, 1),
('Socks',     'socks',     'Performance and casual socks',                6, 1);


-- ============================================================
-- 3. BRANDS (6 initial top athletic brands)
-- ============================================================
INSERT INTO `brands` (`name`, `slug`, `description`, `sort_order`, `is_active`) VALUES
('Nike',         'nike',         'World leader in athletic footwear and sportswear',       1, 1),
('Adidas',       'adidas',       'Iconic 3-Stripes performance and lifestyle footwear',    2, 1),
('ASICS',        'asics',        'Sound mind, sound body — Japanese precision running',    3, 1),
('Hoka',         'hoka',         'Maximalist cushioning for road and trail running',       4, 1),
('Saucony',      'saucony',      'Pure performance running shoes and heritage sneakers',   5, 1),
('Under Armour', 'under-armour', 'High-performance athletic apparel and training shoes',   6, 1);


-- ============================================================
-- 4. PRODUCTS
-- Demonstrates both:
--   - Scenario A: Unique / One-pair imported items (product_type = 'unique')
--   - Scenario B: Regular / Multi-size catalog items (product_type = 'regular')
-- ============================================================

-- Product 1: Unique / One-Pair Imported Sneakers (UK 9) - Nike
INSERT INTO `products` (
    `category_id`, `brand_id`, `name`, `slug`, `sku`, `description`,
    `price`, `old_price`, `color`,
    `condition`, `product_type`, `status`, `is_featured`
) VALUES (
    2,              -- Sneakers
    1,              -- Nike
    'Nike Air Max 90 Essential',
    'nike-air-max-90-essential-black',
    'ATH-SNK-001',
    'Original imported Nike Air Max 90 Essential. Lightweight cushioning with visible Air unit. Perfect for everyday wear and athletic performance.',
    18500.00,       -- Rs. 18,500
    22000.00,       -- Old price Rs. 22,000 (shows discount)
    'Black/White',
    'new',
    'unique',       -- Single physical pair
    'active',
    1               -- Featured
);

-- Product 2: Unique / One-Pair Imported Shoes (UK 10) - Adidas
INSERT INTO `products` (
    `category_id`, `brand_id`, `name`, `slug`, `sku`, `description`,
    `price`, `old_price`, `color`,
    `condition`, `product_type`, `status`, `is_featured`
) VALUES (
    1,              -- Shoes
    2,              -- Adidas
    'Adidas Ultraboost 22',
    'adidas-ultraboost-22-grey',
    'ATH-SHO-001',
    'Imported Adidas Ultraboost 22 running shoes. Responsive Boost midsole with Primeknit upper for a sock-like fit.',
    21000.00,       -- Rs. 21,000
    NULL,           -- No old price
    'Core Grey/Cloud White',
    'new',
    'unique',       -- Single physical pair
    'active',
    1               -- Featured
);

-- Product 3: Regular / Multi-Size Training Shirt (S, M, L, XL) - Nike
INSERT INTO `products` (
    `category_id`, `brand_id`, `name`, `slug`, `sku`, `description`,
    `price`, `old_price`, `color`,
    `condition`, `product_type`, `status`, `is_featured`
) VALUES (
    4,              -- Shirts
    1,              -- Nike
    'Nike Dri-FIT Training Tee',
    'nike-dri-fit-training-tee-black',
    'ATH-SHR-001',
    'Imported Nike Dri-FIT training t-shirt. Sweat-wicking fabric keeps you dry and comfortable during workouts.',
    4500.00,        -- Rs. 4,500
    5500.00,        -- Old price Rs. 5,500
    'Black',
    'new',
    'regular',      -- Multi-size repeated product
    'active',
    0
);

-- Product 4: Regular / Multi-Size Running Shoes - ASICS (UK 8, UK 9, UK 10, UK 11)
INSERT INTO `products` (
    `category_id`, `brand_id`, `name`, `slug`, `sku`, `description`,
    `price`, `old_price`, `color`,
    `condition`, `product_type`, `status`, `is_featured`
) VALUES (
    1,              -- Shoes
    3,              -- ASICS
    'ASICS GEL-Kayano 30',
    'asics-gel-kayano-30-blue',
    'ATH-SHO-002',
    'Advanced stability running shoes with 4D GUIDANCE SYSTEM and PureGEL cushioning for maximum endurance.',
    25500.00,       -- Rs. 25,500
    29000.00,       -- Old price Rs. 29,000
    'French Blue/White',
    'new',
    'regular',      -- Multi-size product
    'active',
    1               -- Featured
);

-- Product 5: Unique / One-Pair Running Shoes - Hoka (UK 9)
INSERT INTO `products` (
    `category_id`, `brand_id`, `name`, `slug`, `sku`, `description`,
    `price`, `old_price`, `color`,
    `condition`, `product_type`, `status`, `is_featured`
) VALUES (
    1,              -- Shoes
    4,              -- Hoka
    'Hoka Clifton 9',
    'hoka-clifton-9-all-black',
    'ATH-SHO-003',
    'Lightweight daily trainer with responsive foam and improved outsole design. Single imported pair.',
    27000.00,       -- Rs. 27,000
    NULL,
    'All Black',
    'new',
    'unique',       -- Single physical pair
    'active',
    1               -- Featured
);


-- ============================================================
-- 5. PRODUCT SIZES & AVAILABILITY
-- ============================================================

-- Sizes for Product 1 (Nike AM90 - Unique One-Pair)
INSERT INTO `product_sizes` (`product_id`, `size`, `is_available`, `sort_order`) VALUES
(1, 'UK 9', 1, 1);

-- Sizes for Product 2 (Adidas Ultraboost 22 - Unique One-Pair)
INSERT INTO `product_sizes` (`product_id`, `size`, `is_available`, `sort_order`) VALUES
(2, 'UK 10', 1, 1);

-- Sizes for Product 3 (Nike Shirt - Regular Multi-Size)
INSERT INTO `product_sizes` (`product_id`, `size`, `is_available`, `sort_order`) VALUES
(3, 'S',  1, 1),   -- Available
(3, 'M',  1, 2),   -- Available
(3, 'L',  1, 3),   -- Available
(3, 'XL', 0, 4);   -- Unavailable / Out of Stock example

-- Sizes for Product 4 (ASICS Kayano - Regular Multi-Size)
INSERT INTO `product_sizes` (`product_id`, `size`, `is_available`, `sort_order`) VALUES
(4, 'UK 8',  1, 1),
(4, 'UK 9',  1, 2),
(4, 'UK 10', 1, 3),
(4, 'UK 11', 0, 4);

-- Sizes for Product 5 (Hoka Clifton - Unique One-Pair)
INSERT INTO `product_sizes` (`product_id`, `size`, `is_available`, `sort_order`) VALUES
(5, 'UK 9', 1, 1);


-- ============================================================
-- 6. PRODUCT IMAGES
-- ============================================================

-- Images for Product 1: Nike Air Max 90
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`) VALUES
(1, 'uploads/products/nike-air-max-90-main.jpg',    'Nike Air Max 90 Essential - Front View',    1, 0),
(1, 'uploads/products/nike-air-max-90-side.jpg',    'Nike Air Max 90 Essential - Side View',     0, 1),
(1, 'uploads/products/nike-air-max-90-back.jpg',    'Nike Air Max 90 Essential - Back View',     0, 2),
(1, 'uploads/products/nike-air-max-90-sole.jpg',    'Nike Air Max 90 Essential - Sole Detail',   0, 3);

-- Images for Product 2: Adidas Ultraboost 22
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`) VALUES
(2, 'uploads/products/adidas-ultraboost-22-main.jpg',  'Adidas Ultraboost 22 - Front View',   1, 0),
(2, 'uploads/products/adidas-ultraboost-22-top.jpg',   'Adidas Ultraboost 22 - Top View',     0, 1);

-- Images for Product 3: Nike Dri-FIT Tee
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`) VALUES
(3, 'uploads/products/nike-dri-fit-tee-main.jpg',   'Nike Dri-FIT Training Tee - Front',   1, 0),
(3, 'uploads/products/nike-dri-fit-tee-back.jpg',   'Nike Dri-FIT Training Tee - Back',    0, 1);

-- Images for Product 4: ASICS Kayano
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`) VALUES
(4, 'uploads/products/asics-kayano-30-main.jpg',    'ASICS GEL-Kayano 30 - Main View',     1, 0);

-- Images for Product 5: Hoka Clifton
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`) VALUES
(5, 'uploads/products/hoka-clifton-9-main.jpg',      'Hoka Clifton 9 - Main View',          1, 0);


-- ============================================================
-- 7. SITE SETTINGS (initial row with Free Delivery default)
-- ============================================================
INSERT INTO `site_settings` (
    `site_name`, `tagline`, `whatsapp_number`, `email`, `phone`,
    `address`, `currency_symbol`, `delivery_fee_default`, `footer_text`
) VALUES (
    'ATHLETICA',
    'FOCUS | POWER | PERFORMANCE',
    '+923001234567',            -- Replace with actual WhatsApp number
    'contact@athletica.pk',     -- Replace with actual email
    '+923001234567',            -- Replace with actual phone
    'Lahore, Pakistan',         -- Replace with actual address
    'Rs.',
    0.00,                       -- Free Delivery Default: Rs. 0.00
    '© 2026 ATHLETICA. All rights reserved. FOCUS | POWER | PERFORMANCE'
);


-- ============================================================
-- SEED DATA COMPLETE
-- ============================================================

-- ============================================================
-- SAMPLE QUERIES (for reference)
-- ============================================================

-- 1. Get product with its available sizes:
-- SELECT p.*, GROUP_CONCAT(ps.size ORDER BY ps.sort_order SEPARATOR ', ') AS available_sizes
-- FROM products p
-- JOIN product_sizes ps ON ps.product_id = p.id AND ps.is_available = 1
-- WHERE p.id = 3 AND p.status = 'active'
-- GROUP BY p.id;

-- 2. Shop by Size (e.g. UK 9):
-- SELECT DISTINCT p.*, pi.image_path AS main_image
-- FROM products p
-- JOIN product_sizes ps ON ps.product_id = p.id
-- LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
-- WHERE ps.size = 'UK 9' AND ps.is_available = 1 AND p.status = 'active';
