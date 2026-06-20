-- Migration 018: Consented clinical-trial / rare-blood recruitment (P4.4)
-- Admins create trials; donors opt in with explicit consent; eligibility matching engine.

-- ── Clinical trial catalogue ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `clinical_trials` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `title`            VARCHAR(200)     NOT NULL,
    `description`      TEXT             DEFAULT NULL,
    `eligibility_notes` TEXT            DEFAULT NULL COMMENT 'Plain-language eligibility criteria for donors',
    `blood_types`      VARCHAR(100)     DEFAULT NULL COMMENT 'Comma-separated blood types, NULL = any',
    `component_codes`  VARCHAR(200)     DEFAULT NULL COMMENT 'Comma-separated component codes, NULL = any',
    `min_donations`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Minimum prior donations required',
    `recruiting_until` DATE             DEFAULT NULL,
    `target_enrolment` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status`           ENUM('open','closed','paused') NOT NULL DEFAULT 'open',
    `created_by`       INT              NOT NULL COMMENT 'Admin user_id',
    `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_trial_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Donor trial enrolments (with explicit consent) ─────────────────────────
CREATE TABLE IF NOT EXISTS `trial_enrolments` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `trial_id`        INT UNSIGNED    NOT NULL,
    `donor_id`        INT             NOT NULL,
    `consent_given_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `consent_version`  VARCHAR(20)    NOT NULL DEFAULT '1.0',
    `status`          ENUM('enrolled','withdrawn','completed') NOT NULL DEFAULT 'enrolled',
    `withdrawn_at`    TIMESTAMP       NULL DEFAULT NULL,
    `notes`           VARCHAR(500)    DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_trial_donor`  (`trial_id`, `donor_id`),
    KEY `ix_enrol_donor`         (`donor_id`),
    CONSTRAINT `enrol_trial_fk`  FOREIGN KEY (`trial_id`)  REFERENCES `clinical_trials` (`id`) ON DELETE CASCADE,
    CONSTRAINT `enrol_donor_fk`  FOREIGN KEY (`donor_id`)  REFERENCES `users`           (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('018_clinical_trials');
