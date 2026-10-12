-- ============================================================
-- ATHLETICA — Migration: Site Settings Architecture Cleanup
-- Single source of truth for responsive hero media:
--   Desktop / Landscape: `hero_slides.media_path`
--   Mobile / Portrait:  `hero_slides.mobile_image`
-- Drops redundant duplicate columns `mobile_image` and `mobile_hero_image` from `site_settings`
-- ============================================================

ALTER TABLE `site_settings` DROP COLUMN IF EXISTS `mobile_image`;
ALTER TABLE `site_settings` DROP COLUMN IF EXISTS `mobile_hero_image`;
