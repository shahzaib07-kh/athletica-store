-- ============================================================
-- ATHLETICA — Migration: Hero Slides Architecture Cleanup
-- Single source of truth for responsive hero media:
--   Desktop / Landscape: `media_path`
--   Mobile / Portrait:  `mobile_image`
-- Drops redundant duplicate column `mobile_media_path`
-- ============================================================

ALTER TABLE `hero_slides` DROP COLUMN IF EXISTS `mobile_media_path`;
