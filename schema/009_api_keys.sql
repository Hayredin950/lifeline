-- 009: API key store for /api/v1 (Doc 06 §4)
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id`           INT           NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(100)  NOT NULL,
    `key_hash`     CHAR(64)      NOT NULL COMMENT 'SHA-256 of the raw key',
    `user_id`      INT           DEFAULT NULL COMMENT 'Owning user (nullable for system keys)',
    `scopes`       JSON          DEFAULT NULL COMMENT 'NULL or ["*"] = all scopes',
    `rate_limit`   INT           NOT NULL DEFAULT 60 COMMENT 'Requests per minute',
    `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
    `last_used_at` TIMESTAMP     NULL DEFAULT NULL,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_api_key_hash` (`key_hash`),
    KEY `ix_api_key_active` (`is_active`, `key_hash`),
    CONSTRAINT `api_keys_user_fkey`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('009');
