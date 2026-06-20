-- 008: Consent capture + versioning (FR-49 / Doc 07 §6)
CREATE TABLE IF NOT EXISTS `consent_log` (
    `id`            INT           NOT NULL AUTO_INCREMENT,
    `user_id`       INT           NOT NULL,
    `terms_version` VARCHAR(20)   NOT NULL,
    `ip_address`    VARCHAR(45)   DEFAULT NULL,
    `user_agent`    VARCHAR(500)  DEFAULT NULL,
    `consented_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_consent_user` (`user_id`),
    CONSTRAINT `consent_log_user_fkey`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('008');
