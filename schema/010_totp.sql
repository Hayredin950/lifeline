-- Migration 010: TOTP 2FA for hospital and admin accounts
-- Adds encrypted secret + enabled flag + backup codes to users table.

ALTER TABLE `users`
    ADD COLUMN `totp_secret`       VARCHAR(64)  DEFAULT NULL
        COMMENT 'Base32-encoded TOTP secret (encrypt at rest in prod)',
    ADD COLUMN `totp_enabled`      TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN `totp_backup_codes` TEXT         DEFAULT NULL
        COMMENT 'JSON array of SHA-256-hashed single-use backup codes';

INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('010');
