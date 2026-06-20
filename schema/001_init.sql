-- =============================================================================
-- LifeLine Blood Network — Canonical Consolidated MySQL Schema
-- Migration: 001_init
-- Engine: MySQL 8.0+, InnoDB, utf8mb4
--
-- This single file REPLACES the three drifting sources:
--   - database.sql          (PostgreSQL dialect — retire)
--   - database_mysql.sql    (missing messages/notifications + 2 columns)
--   - fix_db.sql            (ad-hoc patch; still missing messages.is_edited)
--
-- It closes data-layer defects DEF-11, DEF-17, DEF-18, DEF-19 (see docs/04).
-- Forward-only. Idempotent (IF NOT EXISTS). Apply once on a fresh database.
--
-- Column types here mirror the working application exactly (VARCHAR for the
-- closed-set columns) so this is a guaranteed drop-in. ENUM/CHECK hardening and
-- spatial indexing are applied separately in 002_enum_and_index_hardening.sql.
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- -----------------------------------------------------------------------------
-- Migration ledger (every environment converges deterministically)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version`    VARCHAR(64) NOT NULL,
  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- users — identity root
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `email`             VARCHAR(255) NOT NULL,
  `password`          VARCHAR(255) NOT NULL,
  `role`              VARCHAR(20) DEFAULT 'donor',          -- donor|hospital|admin
  `is_active`         TINYINT(1) DEFAULT '1',
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`        TIMESTAMP NULL DEFAULT NULL,          -- soft delete (FR-49)
  `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_key` (`email`),
  KEY `ix_users_role_active` (`role`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- donor_profiles — 1:1 with a donor user
--   (includes donation_points + is_verified, previously only in fix_db.sql)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donor_profiles` (
  `id`                 INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`            INT(11) DEFAULT NULL,
  `full_name`          VARCHAR(255) NOT NULL,
  `phone`              VARCHAR(30) DEFAULT NULL,
  `blood_type`         VARCHAR(5) DEFAULT NULL,
  `address`            TEXT,
  `city`               VARCHAR(100) DEFAULT NULL,
  `state`              VARCHAR(100) DEFAULT NULL,
  `country`            VARCHAR(100) DEFAULT 'Ethiopia',
  `date_of_birth`      DATE DEFAULT NULL,
  `gender`             VARCHAR(10) DEFAULT NULL,
  `profile_pic`        VARCHAR(255) DEFAULT NULL,            -- uploaded avatar filename (FR-16)
  `is_available`       TINYINT(1) DEFAULT '1',
  `last_donation_date` DATE DEFAULT NULL,
  `latitude`           DECIMAL(10,7) DEFAULT NULL,
  `longitude`          DECIMAL(10,7) DEFAULT NULL,
  `total_donations`    INT(11) DEFAULT '0',
  `tier`               VARCHAR(20) DEFAULT 'bronze',        -- bronze|silver|gold|platinum
  `donation_points`    INT(11) DEFAULT '0',                 -- DEF-18
  `is_verified`        TINYINT(1) DEFAULT '0',              -- DEF-18
  `created_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_donor_match` (`blood_type`, `is_available`, `city`),   -- donor discovery (FR-18)
  KEY `ix_donor_user` (`user_id`),
  KEY `ix_donor_geo` (`latitude`, `longitude`),
  CONSTRAINT `donor_profiles_user_id_fkey`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- hospital_profiles — 1:1 with a hospital user
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `hospital_profiles` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11) DEFAULT NULL,
  `hospital_name`  VARCHAR(255) NOT NULL,
  `phone`          VARCHAR(30) DEFAULT NULL,
  `address`        TEXT,
  `city`           VARCHAR(100) DEFAULT NULL,
  `state`          VARCHAR(100) DEFAULT NULL,
  `country`        VARCHAR(100) DEFAULT 'Ethiopia',
  `license_number` VARCHAR(100) DEFAULT NULL,
  `latitude`       DECIMAL(10,7) DEFAULT NULL,
  `longitude`      DECIMAL(10,7) DEFAULT NULL,
  `is_verified`    TINYINT(1) DEFAULT '0',
  `created_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_hospital_user` (`user_id`),
  KEY `ix_hospital_geo` (`latitude`, `longitude`),
  CONSTRAINT `hospital_profiles_user_id_fkey`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- blood_requests — demand (hospital_id NULL for anonymous Emergency SOS)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blood_requests` (
  `id`                 INT(11) NOT NULL AUTO_INCREMENT,
  `hospital_id`        INT(11) DEFAULT NULL,
  `patient_blood_type` VARCHAR(5) DEFAULT NULL,
  `units_needed`       INT(11) DEFAULT '1',
  `urgency`            VARCHAR(20) DEFAULT 'normal',        -- normal|urgent|critical
  `status`             VARCHAR(20) DEFAULT 'open',          -- open|fulfilled|cancelled
  `required_date`      DATE DEFAULT NULL,
  `city`               VARCHAR(100) DEFAULT NULL,
  `state`              VARCHAR(100) DEFAULT NULL,
  `hospital_address`   TEXT,
  `notes`              TEXT,
  `deleted_at`         TIMESTAMP NULL DEFAULT NULL,         -- soft delete (FR-49)
  `created_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_req_queue` (`status`, `urgency`, `created_at`),
  KEY `ix_req_discovery` (`city`, `state`, `patient_blood_type`),
  KEY `ix_req_hospital` (`hospital_id`),
  CONSTRAINT `blood_requests_hospital_id_fkey`
    FOREIGN KEY (`hospital_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- donor_matches — the join between supply & demand
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donor_matches` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `request_id` INT(11) DEFAULT NULL,
  `donor_id`   INT(11) DEFAULT NULL,
  `status`     VARCHAR(20) DEFAULT 'pending',  -- pending|contacted|confirmed|donated|declined
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_matches_request_id_donor_id_key` (`request_id`, `donor_id`),
  KEY `ix_match_donor_status` (`donor_id`, `status`),
  KEY `ix_match_request_status` (`request_id`, `status`),
  CONSTRAINT `donor_matches_donor_id_fkey`
    FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `donor_matches_request_id_fkey`
    FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- donation_history — immutable record of completed donations
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donation_history` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `donor_id`      INT(11) DEFAULT NULL,
  `request_id`    INT(11) DEFAULT NULL,
  `hospital_id`   INT(11) DEFAULT NULL,
  `donation_date` DATE NOT NULL,
  `blood_type`    VARCHAR(5) DEFAULT NULL,
  `units`         INT(11) DEFAULT '1',
  `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_history_donor_date` (`donor_id`, `donation_date`),
  CONSTRAINT `donation_history_donor_id_fkey`
    FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `donation_history_hospital_id_fkey`
    FOREIGN KEY (`hospital_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `donation_history_request_id_fkey`
    FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- achievements — gamification badges (one per donor+type)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `achievements` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `donor_id`    INT(11) NOT NULL,
  `type`        VARCHAR(50) NOT NULL,
  `title`       VARCHAR(100) NOT NULL,
  `description` TEXT,
  `earned_at`   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `achievements_donor_id_type_key` (`donor_id`, `type`),
  CONSTRAINT `achievements_donor_id_fkey`
    FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- audit_logs — governance trail
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11) DEFAULT NULL,
  `action`      VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(100) DEFAULT NULL,
  `entity_id`   INT(11) DEFAULT NULL,
  `old_values`  TEXT,
  `new_values`  TEXT,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `user_agent`  VARCHAR(500) DEFAULT NULL,
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_audit_action_date` (`action`, `created_at`),
  KEY `ix_audit_user_date` (`user_id`, `created_at`),
  CONSTRAINT `audit_logs_user_id_fkey`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- blood_banks — reference directory (seeded)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blood_banks` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(255) NOT NULL,
  `address`         TEXT,
  `city`            VARCHAR(100) DEFAULT NULL,
  `state`           VARCHAR(100) DEFAULT NULL,
  `phone`           VARCHAR(30) DEFAULT NULL,
  `email`           VARCHAR(255) DEFAULT NULL,
  `license_number`  VARCHAR(100) DEFAULT NULL,
  `working_hours`   VARCHAR(100) DEFAULT NULL,
  `has_24h_service` TINYINT(1) DEFAULT '0',
  `latitude`        DECIMAL(10,7) DEFAULT NULL,
  `longitude`       DECIMAL(10,7) DEFAULT NULL,
  `created_at`      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_bank_location` (`state`, `city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- password_resets — single active token per email
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(255) NOT NULL,
  `token`      VARCHAR(255) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `used_at`    TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `password_resets_email_key` (`email`),
  KEY `ix_reset_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- testimonials — moderated social proof
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `donor_id`       INT(11) DEFAULT NULL,
  `recipient_name` VARCHAR(255) DEFAULT NULL,
  `story`          TEXT NOT NULL,
  `rating`         INT(11) DEFAULT '5',
  `is_approved`    TINYINT(1) DEFAULT '0',
  `created_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_testimonial_approved` (`is_approved`, `created_at`),
  CONSTRAINT `testimonials_donor_id_fkey`
    FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- messages — direct messaging (was only in fix_db.sql; now with is_edited)
--   Closes DEF-11 (api/edit_message.php writes is_edited) and DEF-17.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `sender_id`   INT(11) NOT NULL,
  `receiver_id` INT(11) NOT NULL,
  `subject`     VARCHAR(255) DEFAULT NULL,
  `content`     TEXT NOT NULL,
  `is_read`     TINYINT(1) DEFAULT '0',
  `is_edited`   TINYINT(1) DEFAULT '0',                     -- DEF-11
  `deleted_at`  TIMESTAMP NULL DEFAULT NULL,                -- soft delete (Doc 06)
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_msg_inbox` (`receiver_id`, `is_read`),
  KEY `ix_msg_thread` (`sender_id`, `receiver_id`, `created_at`),
  CONSTRAINT `messages_sender_id_fkey`
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_receiver_id_fkey`
    FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- notifications — in-app alerts (was only in fix_db.sql)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL,
  `type`       VARCHAR(50) NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT,
  `link`       VARCHAR(255) DEFAULT NULL,
  `is_read`    TINYINT(1) DEFAULT '0',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_notif_user` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `notifications_user_id_fkey`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- Seed data
-- =============================================================================
INSERT INTO `blood_banks`
  (`id`,`name`,`address`,`city`,`state`,`phone`,`email`,`license_number`,`working_hours`,`has_24h_service`,`latitude`,`longitude`,`created_at`)
VALUES
  (1,'Ethiopian Red Cross Society Blood Bank','Ras Desta Damtew Street, Addis Ababa','Addis Ababa','Addis Ababa','+251 11 551 5166',NULL,NULL,'24 Hours',1,NULL,NULL,CURRENT_TIMESTAMP),
  (2,'Tikur Anbessa (Black Lion) Hospital Blood Bank','Siddist Kilo, Addis Ababa','Addis Ababa','Addis Ababa','+251 11 551 4016',NULL,NULL,'24 Hours',1,NULL,NULL,CURRENT_TIMESTAMP),
  (3,'Yekatit 12 Hospital Blood Bank','Piassa, Addis Ababa','Addis Ababa','Addis Ababa','+251 11 155 3800',NULL,NULL,'24 Hours',1,NULL,NULL,CURRENT_TIMESTAMP),
  (4,'St. Paul''s Hospital Millennium Medical College Blood Bank','Gulele, Addis Ababa','Addis Ababa','Addis Ababa','+251 11 276 5298',NULL,NULL,'24 Hours',1,NULL,NULL,CURRENT_TIMESTAMP),
  (5,'Bahir Dar University Teaching Hospital Blood Bank','Bahir Dar, Amhara','Bahir Dar','Amhara','+251 58 220 7230',NULL,NULL,'8am-6pm',0,NULL,NULL,CURRENT_TIMESTAMP),
  (6,'Mekelle University Hospital Blood Bank','Mekelle, Tigray','Mekelle','Tigray','+251 34 441 6680',NULL,NULL,'8am-6pm',0,NULL,NULL,CURRENT_TIMESTAMP),
  (7,'Hawassa University Comprehensive Specialized Hospital Blood Bank','Hawassa, SNNP','Hawassa','SNNP','+251 46 220 9038',NULL,NULL,'8am-6pm',0,NULL,NULL,CURRENT_TIMESTAMP),
  (8,'Jimma University Medical Centre Blood Bank','Jimma, Oromia','Jimma','Oromia','+251 47 111 4100',NULL,NULL,'8am-6pm',0,NULL,NULL,CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `testimonials` (`id`,`donor_id`,`recipient_name`,`story`,`rating`,`is_approved`,`created_at`)
VALUES
  (1,NULL,'Abebe Girma''s Family','My father needed O- blood urgently after his accident. Within 2 hours, LifeLine matched us with a donor in Addis Ababa. He survived because of this platform. Forever grateful.',5,1,CURRENT_TIMESTAMP),
  (2,NULL,'Dr. Tigist Haile','As a hospital administrator at Tikur Anbessa Hospital, LifeLine has transformed how we handle emergency blood needs. The matching system is incredibly fast and reliable.',5,1,CURRENT_TIMESTAMP),
  (3,NULL,'Meron Tadesse','I donated blood for the first time through LifeLine. The process was so simple and knowing I helped save a life is the best feeling in the world.',5,1,CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE `story` = VALUES(`story`);

-- Record this migration
INSERT INTO `schema_migrations` (`version`) VALUES ('001_init')
ON DUPLICATE KEY UPDATE `version` = `version`;
