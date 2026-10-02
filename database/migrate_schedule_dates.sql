-- -------------------------------------------------------------------
--  Migration: feeding_schedules.days (weekday CSV) -> feed_date (DATE)
--  Run once on an existing database. Fresh installs already get the
--  new column from smartpetfeeder.sql.
--
--  Existing rows are given the next upcoming date that matches the FIRST
--  weekday in their old `days` list (today counts if it matches).
-- -------------------------------------------------------------------
ALTER TABLE `feeding_schedules`
  ADD COLUMN `feed_date` DATE NULL AFTER `user_id`;

UPDATE `feeding_schedules`
   SET `feed_date` = CURDATE() + INTERVAL MOD(
         FIELD(SUBSTRING_INDEX(`days`, ',', 1), 'Mon','Tue','Wed','Thu','Fri','Sat','Sun') - 1
         - WEEKDAY(CURDATE()) + 7, 7) DAY;

ALTER TABLE `feeding_schedules`
  MODIFY `feed_date` DATE NOT NULL,
  DROP COLUMN `days`;
