-- Migration 015: Multi-country configuration store (P4.1)
-- Stores per-country compliance flags, default locale, and name.
-- App code reads this at runtime; infra manages per-country DB via env vars.

CREATE TABLE IF NOT EXISTS `country_config` (
    `iso2`               CHAR(2)         NOT NULL COMMENT 'ISO 3166-1 alpha-2 (e.g. ET, KE, NG)',
    `name`               VARCHAR(100)    NOT NULL,
    `default_locale`     VARCHAR(8)      NOT NULL DEFAULT 'en',
    `currency`           CHAR(3)         NOT NULL DEFAULT 'USD',
    `donation_cooloff_days` TINYINT UNSIGNED NOT NULL DEFAULT 56
        COMMENT 'Overrides global DONATION_COOLOFF_DAYS for this country',
    `requires_consent_version` VARCHAR(20) DEFAULT NULL
        COMMENT 'Force a specific T&C version for this country (NULL = use global)',
    `gdpr_mode`          TINYINT(1)      NOT NULL DEFAULT 0
        COMMENT '1 = full GDPR-style DPA rules (EU/UK/Kenya PDPA etc.)',
    `hipaa_mode`         TINYINT(1)      NOT NULL DEFAULT 0
        COMMENT '1 = HIPAA-style BAA requirements',
    `is_active`          TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`iso2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed initial countries
INSERT IGNORE INTO `country_config`
    (`iso2`, `name`, `default_locale`, `currency`, `donation_cooloff_days`, `gdpr_mode`, `hipaa_mode`)
VALUES
    ('ET', 'Ethiopia',       'am', 'ETB', 56, 0, 0),
    ('KE', 'Kenya',          'en', 'KES', 56, 0, 0),
    ('NG', 'Nigeria',        'en', 'NGN', 56, 0, 0),
    ('GH', 'Ghana',          'en', 'GHS', 56, 0, 0),
    ('TZ', 'Tanzania',       'en', 'TZS', 56, 0, 0),
    ('UG', 'Uganda',         'en', 'UGX', 56, 0, 0),
    ('SD', 'Sudan',          'en', 'SDG', 56, 0, 0),
    ('RW', 'Rwanda',         'en', 'RWF', 56, 0, 0),
    ('US', 'United States',  'en', 'USD', 56, 0, 1),
    ('GB', 'United Kingdom', 'en', 'GBP', 84, 1, 0),
    ('DE', 'Germany',        'en', 'EUR', 56, 1, 0);

INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('015_country_config');
