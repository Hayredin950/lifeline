-- =============================================================================
-- LifeLine Blood Network — Verified email change
-- Migration: 004_email_change_verification
-- Depends on: 001_init
--
-- Closes DEF-07: a user (or an attacker with a hijacked session) could change the
-- account email instantly, with no proof of ownership of the new address — a
-- silent account-takeover primitive. We now hold the change in a pending table
-- and only swap the email after the new address proves ownership via a tokened
-- link. Tokens are stored hashed (defense if the table leaks) and expire.
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `email_change_requests` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL,
  `new_email`  VARCHAR(191) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,                 -- sha256 of the raw token in the link
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecr_token` (`token_hash`),
  KEY `ix_ecr_user` (`user_id`),
  KEY `ix_ecr_expires` (`expires_at`),
  CONSTRAINT `fk_ecr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `schema_migrations` (`version`) VALUES ('004_email_change_verification')
ON DUPLICATE KEY UPDATE `version` = `version`;
