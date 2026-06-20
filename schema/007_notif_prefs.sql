-- 007: Notification preferences + unsubscribe tokens (FR-32)
-- JSON prefs column (NULL = all enabled by default).
ALTER TABLE `donor_profiles`
    ADD COLUMN `email_notif_prefs` JSON DEFAULT NULL;

-- Token used in one-click unsubscribe links so we never expose email in the URL.
ALTER TABLE `users`
    ADD COLUMN `unsubscribe_token` VARCHAR(64) DEFAULT NULL;

-- Backfill tokens for all existing users.
UPDATE `users` SET `unsubscribe_token` = LOWER(HEX(RANDOM_BYTES(32)));

INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('007');
