-- ============================================================
-- ATHLETICA — Phase 5C Migration: Homepage CMS & Flash Sales
-- Idempotent table creation for hero_slides, flash_sales, flash_sale_products
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;

-- 1. HERO SLIDES
CREATE TABLE IF NOT EXISTS `hero_slides` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255)    NOT NULL,
    `subtitle`    VARCHAR(500)    NULL     DEFAULT NULL,
    `media_path`  VARCHAR(500)    NOT NULL COMMENT 'Relative path: uploads/slides/filename.webp or .mp4',
    `media_type`  ENUM('image','video') NOT NULL DEFAULT 'image',
    `cta_text`    VARCHAR(100)    NULL     DEFAULT NULL COMMENT 'Button label',
    `cta_url`     VARCHAR(500)    NULL     DEFAULT NULL COMMENT 'Link target URL',
    `sort_order`  INT             NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_hero_slides_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. FLASH SALES
CREATE TABLE IF NOT EXISTS `flash_sales` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255)    NOT NULL COMMENT 'Campaign name, e.g. Weekend Flash Drop',
    `starts_at`   DATETIME        NOT NULL,
    `ends_at`     DATETIME        NOT NULL,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Admin toggle',
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_flash_sales_schedule` (`is_active`, `starts_at`, `ends_at`),
    CONSTRAINT `chk_flash_sales_dates` CHECK (`ends_at` > `starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. FLASH SALE PRODUCTS
CREATE TABLE IF NOT EXISTS `flash_sale_products` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `flash_sale_id` INT UNSIGNED    NOT NULL,
    `product_id`    INT UNSIGNED    NOT NULL,
    `sale_price`    DECIMAL(10,2)   NOT NULL COMMENT 'Promotional price during sale window',
    `sort_order`    INT             NOT NULL DEFAULT 0,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_flash_sale_product` (`flash_sale_id`, `product_id`),
    CONSTRAINT `fk_fsp_flash_sale`
        FOREIGN KEY (`flash_sale_id`) REFERENCES `flash_sales` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fsp_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_fsp_sale_price` CHECK (`sale_price` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
