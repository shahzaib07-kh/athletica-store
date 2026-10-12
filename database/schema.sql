-- ============================================================
-- ATHLETICA — Database Schema
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- MySQL 8.0+ Required
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Create database (uncomment if running standalone)
-- CREATE DATABASE IF NOT EXISTS athletica
--     CHARACTER SET utf8mb4
--     COLLATE utf8mb4_unicode_ci;
-- USE athletica;

-- ============================================================
-- 1. ADMINS
-- ============================================================
CREATE TABLE `admins` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)     NOT NULL,
    `email`         VARCHAR(100)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL COMMENT 'Bcrypt/Argon2 hash via password_hash(). NEVER plain text.',
    `full_name`     VARCHAR(100)    NOT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `last_login_at` DATETIME        NULL     DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_username` (`username`),
    UNIQUE KEY `uq_admins_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2. CATEGORIES
-- ============================================================
CREATE TABLE `categories` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)    NOT NULL,
    `slug`        VARCHAR(120)    NOT NULL,
    `description` TEXT            NULL,
    `image`       VARCHAR(255)    NULL     DEFAULT NULL,
    `sort_order`  INT             NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`),
    KEY `idx_categories_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. BRANDS
-- ============================================================
CREATE TABLE `brands` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)    NOT NULL,
    `slug`        VARCHAR(120)    NOT NULL,
    `logo`        VARCHAR(255)    NULL     DEFAULT NULL COMMENT 'Relative path: uploads/brands/logo.png',
    `description` TEXT            NULL,
    `sort_order`  INT             NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_brands_slug` (`slug`),
    KEY `idx_brands_sort_order` (`sort_order`),
    KEY `idx_brands_is_active`  (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 4. PRODUCTS
-- ============================================================
CREATE TABLE `products` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `category_id`  INT UNSIGNED    NOT NULL,
    `brand_id`     INT UNSIGNED    NULL     DEFAULT NULL,
    `name`         VARCHAR(255)    NOT NULL,
    `slug`         VARCHAR(280)    NOT NULL,
    `sku`          VARCHAR(50)     NULL     DEFAULT NULL COMMENT 'Optional stock-keeping unit for internal tracking',
    `description`      TEXT            NULL,
    `condition_notes`  TEXT            NULL     DEFAULT NULL COMMENT 'Detailed condition assessment for used or refurbished footwear',
    `features`         TEXT            NULL     DEFAULT NULL COMMENT 'Key product highlights or specifications (one per line or formatted)',
    `price`        DECIMAL(10,2)   NOT NULL COMMENT 'Current selling price. DECIMAL avoids float errors.',
    `old_price`    DECIMAL(10,2)   NULL     DEFAULT NULL COMMENT 'Previous price for discount display. NULL = no discount.',
    `color`        VARCHAR(50)     NULL     DEFAULT NULL,
    `condition`    ENUM('new','used','refurbished') NOT NULL DEFAULT 'new',
    `product_type` ENUM('unique','regular') NOT NULL DEFAULT 'regular' COMMENT 'unique = single physical pair; regular = multi-size repeated product',
    `status`       ENUM('active','inactive','out_of_stock') NOT NULL DEFAULT 'active',
    `is_featured`          TINYINT(1)      NOT NULL DEFAULT 0,
    `advance_payment_percentage` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Advance payment percentage required (0-100)',
    `featured_sort_order`  INT             NULL     DEFAULT NULL COMMENT 'Display order in Featured Footwear: 1=first, 2=second. NULL sorts last.',
    `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    UNIQUE KEY `uq_products_sku`  (`sku`),

    -- Foreign Keys
    CONSTRAINT `fk_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT `fk_products_brand`
        FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    -- Performance Indexes
    KEY `idx_products_category_id`      (`category_id`),
    KEY `idx_products_brand_id`         (`brand_id`),
    KEY `idx_products_type`             (`product_type`),
    KEY `idx_products_status`           (`status`),
    KEY `idx_products_is_featured`      (`is_featured`),
    KEY `idx_products_featured_sort`    (`is_featured`, `featured_sort_order`),
    KEY `idx_products_status_category`  (`status`, `category_id`),
    KEY `idx_products_status_brand`     (`status`, `brand_id`),
    KEY `idx_products_status_featured`  (`status`, `is_featured`),

    -- CHECK Constraints (MySQL 8.0.16+)
    CONSTRAINT `chk_products_price_positive`     CHECK (`price` > 0),
    CONSTRAINT `chk_products_old_price_positive`  CHECK (`old_price` IS NULL OR `old_price` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 5. PRODUCT SIZES & AVAILABILITY
-- ============================================================
CREATE TABLE `product_sizes` (
    `id`           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `product_id`   INT UNSIGNED      NOT NULL,
    `size`         VARCHAR(30)       NOT NULL COMMENT 'Size label: UK 8, UK 9, M, L, 32W, One Size, etc.',
    `is_available` TINYINT(1)        NOT NULL DEFAULT 1 COMMENT '1 = Available, 0 = Out of Stock',
    `sort_order`   SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Display order for size selector',
    `created_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Prevent duplicate sizes for the same product
    UNIQUE KEY `uq_product_size` (`product_id`, `size`),

    -- Foreign Key: cascade delete when parent product is removed
    CONSTRAINT `fk_product_sizes_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- Performance indexes for storefront queries
    KEY `idx_product_sizes_size`      (`size`),
    KEY `idx_product_sizes_available` (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 6. PRODUCT IMAGES
-- ============================================================
CREATE TABLE `product_images` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `product_id`  INT UNSIGNED    NOT NULL,
    `image_path`  VARCHAR(255)    NOT NULL COMMENT 'Relative path: uploads/products/filename.jpg',
    `alt_text`    VARCHAR(255)    NULL     DEFAULT NULL,
    `is_primary`  TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = main display image',
    `sort_order`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Foreign Keys
    CONSTRAINT `fk_product_images_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- Performance Indexes
    KEY `idx_product_images_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 7. ORDERS
-- ============================================================
CREATE TABLE `orders` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `order_number`     VARCHAR(30)     NOT NULL COMMENT 'Human-readable: ATH-YYYYMMDD-NNNN',
    `customer_name`    VARCHAR(100)    NOT NULL,
    `customer_phone`   VARCHAR(30)     NOT NULL COMMENT 'WhatsApp / phone number',
    `customer_email`   VARCHAR(100)    NULL     DEFAULT NULL,
    `city`             VARCHAR(100)    NOT NULL,
    `delivery_address` TEXT            NOT NULL,
    `subtotal`         DECIMAL(10,2)   NOT NULL,
    `delivery_fee`     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `total_amount`     DECIMAL(10,2)   NOT NULL,
    `status`           ENUM('pending','confirmed','processing','shipped','delivered','cancelled')
                                       NOT NULL DEFAULT 'pending',
    `admin_notes`      TEXT            NULL     DEFAULT NULL COMMENT 'Internal notes visible only to admins',
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_order_number` (`order_number`),

    -- Performance Indexes
    KEY `idx_orders_status`         (`status`),
    KEY `idx_orders_customer_phone` (`customer_phone`),
    KEY `idx_orders_created_at`     (`created_at`),

    -- CHECK Constraints
    CONSTRAINT `chk_orders_subtotal_positive`     CHECK (`subtotal` >= 0),
    CONSTRAINT `chk_orders_delivery_fee_positive` CHECK (`delivery_fee` >= 0),
    CONSTRAINT `chk_orders_total_positive`        CHECK (`total_amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 8. ORDER ITEMS
-- ============================================================
CREATE TABLE `order_items` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `order_id`       INT UNSIGNED    NOT NULL,
    `product_id`     INT UNSIGNED    NULL     DEFAULT NULL COMMENT 'Nullable: SET NULL if product is ever deleted. Snapshot columns are the source of truth.',
    `product_name`   VARCHAR(255)    NOT NULL COMMENT 'SNAPSHOT: Product name at time of purchase',
    `product_price`  DECIMAL(10,2)   NOT NULL COMMENT 'SNAPSHOT: Unit price at time of purchase',
    `product_size`   VARCHAR(30)     NULL     DEFAULT NULL COMMENT 'SNAPSHOT: Size at time of purchase',
    `product_color`  VARCHAR(50)     NULL     DEFAULT NULL COMMENT 'SNAPSHOT: Color at time of purchase',
    `quantity`       INT UNSIGNED    NOT NULL DEFAULT 1,
    `line_total`     DECIMAL(10,2)   NOT NULL COMMENT 'product_price * quantity',
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Foreign Keys
    CONSTRAINT `fk_order_items_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT `fk_order_items_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    -- Performance Indexes
    KEY `idx_order_items_order_id`   (`order_id`),
    KEY `idx_order_items_product_id` (`product_id`),

    -- CHECK Constraints
    CONSTRAINT `chk_order_items_quantity_min`    CHECK (`quantity` >= 1),
    CONSTRAINT `chk_order_items_price_positive`  CHECK (`product_price` > 0),
    CONSTRAINT `chk_order_items_total_positive`  CHECK (`line_total` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 9. SITE SETTINGS
-- ============================================================
CREATE TABLE `site_settings` (
    `id`                   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `site_name`            VARCHAR(100)    NOT NULL DEFAULT 'ATHLETICA',
    `tagline`              VARCHAR(255)    NULL     DEFAULT NULL,
    `logo`                 VARCHAR(255)    NULL     DEFAULT NULL,
    `favicon`              VARCHAR(255)    NULL     DEFAULT NULL,
    `whatsapp_number`      VARCHAR(30)     NULL     DEFAULT NULL,
    `email`                VARCHAR(100)    NULL     DEFAULT NULL,
    `phone`                VARCHAR(30)     NULL     DEFAULT NULL,
    `address`              TEXT            NULL,
    `facebook_url`         VARCHAR(255)    NULL     DEFAULT NULL,
    `instagram_url`        VARCHAR(255)    NULL     DEFAULT NULL,
    `tiktok_url`           VARCHAR(255)    NULL     DEFAULT NULL,
    `footer_text`          TEXT            NULL,
    `currency_symbol`      VARCHAR(10)     NOT NULL DEFAULT 'Rs.',
    `delivery_fee_default` DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `updated_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SCHEMA COMPLETE
-- ============================================================
