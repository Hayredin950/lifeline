-- =============================================================================
-- LifeLine Blood Network — Forced password change
-- Migration: 005_force_password_change
-- Depends on: 001_init
--
-- Closes the "default admin credential" risk (Doc 07): a freshly seeded admin
-- account must rotate its password before it can do anything. The flag is generic
-- so it can also be used for admin-initiated resets of any account later.
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Add the column only if it does not already exist (idempotent re-run safety).
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'must_change_password'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `schema_migrations` (`version`) VALUES ('005_force_password_change')
ON DUPLICATE KEY UPDATE `version` = `version`;
