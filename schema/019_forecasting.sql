-- Migration 019: ML demand forecasting + donor-propensity scoring tables (P4.5)
-- De-identified: no PII stored in forecasting/scoring tables.

-- ── Demand forecast snapshots (blood type × region, updated nightly) ────────
CREATE TABLE IF NOT EXISTS `demand_forecasts` (
    `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `blood_type`     ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    `region_id`      VARCHAR(30)      NOT NULL DEFAULT 'et-central',
    `period_start`   DATE             NOT NULL COMMENT 'Monday of forecast week',
    `predicted_units` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `actual_units`   SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Filled after the week closes',
    `confidence`     TINYINT UNSIGNED  NOT NULL DEFAULT 50 COMMENT '0-100 confidence %',
    `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_forecast_type_region_period` (`blood_type`, `region_id`, `period_start`),
    KEY `ix_forecast_period` (`period_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Donor propensity scores (de-identified, no name/email stored here) ──────
CREATE TABLE IF NOT EXISTS `donor_propensity_scores` (
    `donor_id`             INT          NOT NULL PRIMARY KEY,
    `score`                DECIMAL(5,4) NOT NULL DEFAULT 0.5000 COMMENT '0–1 propensity to donate next 30 days',
    `recency_days`         SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Days since last donation',
    `frequency_6m`         TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Donations in last 6 months',
    `response_rate`        DECIMAL(4,3) NOT NULL DEFAULT 0.500 COMMENT 'Accepted/contacted ratio',
    `cool_off_fraction`    DECIMAL(4,3) NOT NULL DEFAULT 0.000 COMMENT 'Fraction of 56-day window elapsed',
    `computed_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `dps_donor_fk` FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('019_forecasting');
