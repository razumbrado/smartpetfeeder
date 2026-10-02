-- ===================================================================
--  Smart Pet Feeder  -  Database schema + sample data
--  Import this file in phpMyAdmin (XAMPP) or run:
--     mysql -u root < database/smartpetfeeder.sql
-- ===================================================================

CREATE DATABASE IF NOT EXISTS `smartpetfeeder`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smartpetfeeder`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `alerts`;
DROP TABLE IF EXISTS `feeding_records`;
DROP TABLE IF EXISTS `feeding_schedules`;
DROP TABLE IF EXISTS `food_level`;
DROP TABLE IF EXISTS `device_status`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------------
--  users  -  admin / pet owner accounts
-- -------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name`    VARCHAR(60)  NOT NULL,
  `last_name`     VARCHAR(60)  NOT NULL,
  `username`      VARCHAR(60)  NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','owner') NOT NULL DEFAULT 'admin',
  `avatar`        VARCHAR(255) NULL DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default login ->  username: admin   |   email: admin@petfeeder.local
-- Password: admin123   (hash generated with PHP password_hash / bcrypt)
INSERT INTO `users` (`first_name`,`last_name`,`username`,`email`,`password_hash`,`role`) VALUES
  ('Pet', 'Owner', 'admin', 'admin@petfeeder.local',
   '$2y$10$cP83J5XwzStXYfy47m0RfezM6AC2sjCp3J0oT3ujEi/zHvqG.ByOy', 'admin');

-- -------------------------------------------------------------------
--  device_status  -  one row per physical feeder (ESP32)
--  Kept "not_connected" until real hardware checks in via the API.
-- -------------------------------------------------------------------
CREATE TABLE `device_status` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_name`  VARCHAR(80) NOT NULL DEFAULT 'ESP32 Feeder #1',
  `status`       ENUM('not_connected','online','offline') NOT NULL DEFAULT 'not_connected',
  `firmware`     VARCHAR(40) DEFAULT NULL,
  `ip_address`   VARCHAR(45) DEFAULT NULL,
  `last_seen`    DATETIME DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `device_status` (`device_name`,`status`) VALUES
  ('ESP32 Feeder #1', 'not_connected');

-- -------------------------------------------------------------------
--  food_level  -  history of food-level readings (percent)
--  For now a manual/sample value; later written by the ESP32 from a
--  load cell + HX711 (food_grams = weight in the hopper).
-- -------------------------------------------------------------------
CREATE TABLE `food_level` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id`    INT UNSIGNED DEFAULT NULL,
  `level_percent` TINYINT UNSIGNED NOT NULL DEFAULT 75,
  `food_grams`   SMALLINT UNSIGNED DEFAULT NULL,
  `source`       ENUM('sample','manual','sensor') NOT NULL DEFAULT 'sample',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_food_level_device` (`device_id`),
  CONSTRAINT `fk_food_level_device` FOREIGN KEY (`device_id`)
    REFERENCES `device_status`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial sample value requested by spec: 75% Remaining
INSERT INTO `food_level` (`device_id`,`level_percent`,`source`) VALUES
  (1, 75, 'sample');

-- -------------------------------------------------------------------
--  feeding_schedules
--  each schedule is a single feeding on a specific date + time
-- -------------------------------------------------------------------
CREATE TABLE `feeding_schedules` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `feed_date`  DATE NOT NULL,
  `feed_time`  TIME NOT NULL,
  `portions`   TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `grams`      SMALLINT UNSIGNED NOT NULL DEFAULT 80,
  `enabled`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_sched_user` (`user_id`),
  CONSTRAINT `fk_sched_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `feeding_schedules` (`user_id`,`feed_date`,`feed_time`,`portions`,`grams`,`enabled`) VALUES
  (1, CURDATE() + INTERVAL 1 DAY, '08:40:00', 2, 80, 1),
  (1, CURDATE() + INTERVAL 1 DAY, '17:40:00', 2, 80, 1);

-- -------------------------------------------------------------------
--  feeding_records  -  every feed command (manual, quick or scheduled)
--  status lifecycle:  pending_hardware -> command_sent -> dispensed | failed
-- -------------------------------------------------------------------
CREATE TABLE `feeding_records` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED DEFAULT NULL,
  `schedule_id`  INT UNSIGNED DEFAULT NULL,
  `portions`     TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `grams`        SMALLINT UNSIGNED NOT NULL DEFAULT 80,
  `source`       ENUM('manual','quick','schedule') NOT NULL DEFAULT 'manual',
  `status`       ENUM('pending_hardware','command_sent','dispensed','failed')
                 NOT NULL DEFAULT 'pending_hardware',
  `note`         VARCHAR(255) DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dispensed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rec_user` (`user_id`),
  KEY `fk_rec_sched` (`schedule_id`),
  CONSTRAINT `fk_rec_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rec_sched` FOREIGN KEY (`schedule_id`)
    REFERENCES `feeding_schedules`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A couple of sample rows so the dashboard is not empty on first run.
INSERT INTO `feeding_records`
  (`user_id`,`schedule_id`,`portions`,`grams`,`source`,`status`,`note`,`created_at`) VALUES
  (1, 1, 2, 80, 'schedule', 'pending_hardware', 'Scheduled breakfast (awaiting ESP32)',
     DATE_SUB(NOW(), INTERVAL 6 HOUR)),
  (1, NULL, 3, 120, 'manual', 'pending_hardware', 'Manual feed (awaiting ESP32)',
     DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- -------------------------------------------------------------------
--  alerts
-- -------------------------------------------------------------------
CREATE TABLE `alerts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`       ENUM('low_food','schedule_reminder','feed_pending','feed_completed',
                    'hardware') NOT NULL,
  `severity`   ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  `title`      VARCHAR(120) NOT NULL,
  `message`    VARCHAR(255) NOT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No seeded rows: alerts are only ever created by the app itself
-- (add_alert(), called from record_feeding(), set_food_level(), and
-- refresh_dynamic_alerts()) once real activity happens.
