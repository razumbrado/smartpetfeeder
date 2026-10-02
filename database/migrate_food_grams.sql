-- -------------------------------------------------------------------
--  Migration: store the load-cell (HX711) weight with each food-level
--  reading. Run once on an existing database. Fresh installs already get
--  the column from smartpetfeeder.sql.
-- -------------------------------------------------------------------
ALTER TABLE `food_level`
  ADD COLUMN `food_grams` SMALLINT UNSIGNED DEFAULT NULL AFTER `level_percent`;
