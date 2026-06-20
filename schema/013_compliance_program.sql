-- =============================================================================
-- Migration 013: DPDP + HIPAA-style compliance program tables (P3 · Doc 07)
--
-- Provides the data-layer for:
--   • Breach incident management (72-h notification obligation)
--   • Business Associate Agreement (BAA) tracking
--   • Data Protection Impact Assessment (DPIA) register
--   • DSAR (Data Subject Access Request) queue (extends consent_log)
--
-- Apply:
--   mysql -u lifeline_user -p lifeline_db_mysql < schema/013_compliance_program.sql
-- =============================================================================

-- ---------------------------------------------------------------------------
-- breach_incidents — DPDP Article 8 / HIPAA §164.400 breach log
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `breach_incidents` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(255) NOT NULL,
  `severity`         ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status`           ENUM('open','contained','remediated','closed') NOT NULL DEFAULT 'open',
  `description`      TEXT NOT NULL,
  `affected_tables`  VARCHAR(500) DEFAULT NULL COMMENT 'CSV of affected table names',
  `estimated_users`  INT(11) DEFAULT NULL     COMMENT 'Estimated number of affected data subjects',
  `discovered_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `contained_at`     TIMESTAMP NULL DEFAULT NULL,
  `notified_at`      TIMESTAMP NULL DEFAULT NULL COMMENT 'When authorities / subjects were notified',
  `authority_ref`    VARCHAR(255) DEFAULT NULL  COMMENT 'Reference from INSA / data-protection authority',
  `reported_by`      INT(11) DEFAULT NULL       COMMENT 'FK → users.id of reporter (admin)',
  `closed_at`        TIMESTAMP NULL DEFAULT NULL,
  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_breach_status`   (`status`, `discovered_at`),
  KEY `ix_breach_severity` (`severity`, `status`),
  CONSTRAINT `breach_reporter_fkey`
    FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='DPDP/HIPAA breach incident register';

-- ---------------------------------------------------------------------------
-- breach_timeline — audit trail for each incident update
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `breach_timeline` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `incident_id` INT(11) NOT NULL,
  `actor_id`    INT(11) DEFAULT NULL,
  `event`       VARCHAR(255) NOT NULL,
  `detail`      TEXT,
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_breach_timeline_incident` (`incident_id`, `created_at`),
  CONSTRAINT `breach_timeline_incident_fkey`
    FOREIGN KEY (`incident_id`) REFERENCES `breach_incidents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `breach_timeline_actor_fkey`
    FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- baa_agreements — Business Associate Agreement tracking
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `baa_agreements` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `partner_name`     VARCHAR(255) NOT NULL,
  `partner_type`     ENUM('hospital','blood_bank','lab','health_authority','insurer','other') NOT NULL DEFAULT 'other',
  `contact_name`     VARCHAR(255) DEFAULT NULL,
  `contact_email`    VARCHAR(255) DEFAULT NULL,
  `services_covered` TEXT         DEFAULT NULL COMMENT 'What PHI/data the partner touches',
  `signed_at`        DATE         NOT NULL,
  `expires_at`       DATE         DEFAULT NULL,
  `renewal_alert_at` DATE         DEFAULT NULL COMMENT 'Date to trigger renewal reminder',
  `status`           ENUM('draft','active','expired','terminated') NOT NULL DEFAULT 'active',
  `document_ref`     VARCHAR(500) DEFAULT NULL COMMENT 'File path or document-management URL',
  `notes`            TEXT         DEFAULT NULL,
  `created_by`       INT(11)      DEFAULT NULL,
  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_baa_status`   (`status`, `expires_at`),
  KEY `ix_baa_partner`  (`partner_name`),
  CONSTRAINT `baa_creator_fkey`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Business Associate Agreement register';

-- ---------------------------------------------------------------------------
-- dpia_records — Data Protection Impact Assessment register (DPDP Art 3)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dpia_records` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `process_name`      VARCHAR(255) NOT NULL  COMMENT 'e.g. Geo-matching, Donation history analytics',
  `description`       TEXT         DEFAULT NULL,
  `data_types`        VARCHAR(1000) DEFAULT NULL COMMENT 'CSV: blood_type, location, DOB, ...',
  `legal_basis`       VARCHAR(255)  DEFAULT NULL COMMENT 'e.g. Legitimate interest, Consent',
  `risk_level`        ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `risks_identified`  TEXT          DEFAULT NULL,
  `mitigations`       TEXT          DEFAULT NULL,
  `dpo_sign_off`      TINYINT(1)    NOT NULL DEFAULT 0,
  `reviewed_by`       VARCHAR(255)  DEFAULT NULL,
  `reviewed_at`       DATE          DEFAULT NULL,
  `next_review_at`    DATE          DEFAULT NULL,
  `status`            ENUM('draft','in_review','approved','retired') NOT NULL DEFAULT 'draft',
  `created_by`        INT(11)       DEFAULT NULL,
  `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_dpia_status`   (`status`, `next_review_at`),
  KEY `ix_dpia_risk`     (`risk_level`, `status`),
  CONSTRAINT `dpia_creator_fkey`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='DPIA register per data-processing activity';

-- ---------------------------------------------------------------------------
-- dsar_requests — Data Subject Access Request queue (extends consent_log workflow)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dsar_requests` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`         INT(11) DEFAULT NULL         COMMENT 'NULL if request via email only',
  `requester_email` VARCHAR(255) NOT NULL,
  `request_type`    ENUM('access','rectification','erasure','portability','restriction','objection') NOT NULL DEFAULT 'access',
  `description`     TEXT         DEFAULT NULL,
  `status`          ENUM('received','in_progress','completed','rejected') NOT NULL DEFAULT 'received',
  `handler_id`      INT(11) DEFAULT NULL          COMMENT 'Admin who owns this request',
  `due_at`          DATE    NOT NULL              COMMENT '30-day statutory deadline',
  `completed_at`    TIMESTAMP NULL DEFAULT NULL,
  `rejection_reason` TEXT   DEFAULT NULL,
  `notes`           TEXT    DEFAULT NULL,
  `created_at`      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_dsar_status`   (`status`, `due_at`),
  KEY `ix_dsar_user`     (`user_id`),
  CONSTRAINT `dsar_user_fkey`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dsar_handler_fkey`
    FOREIGN KEY (`handler_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='DSAR / rights-request queue';

-- ---------------------------------------------------------------------------
-- Seed: two example DPIAs so the dashboard is not empty on first boot
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `dpia_records`
  (`id`, `process_name`, `data_types`, `legal_basis`, `risk_level`,
   `risks_identified`, `mitigations`, `dpo_sign_off`, `status`,
   `reviewed_at`, `next_review_at`)
VALUES
  (1, 'Geo-proximity donor matching',
   'GPS coordinates, blood type, availability status',
   'Legitimate interest (life-critical matching)',
   'high',
   'Location exposure to matched hospitals; blood-type inference from request metadata.',
   'Coordinate precision rounded to city-level for non-critical requests; blood type visible only to matched parties; audit log on every match.',
   0, 'in_review',
   NULL, DATE_ADD(CURDATE(), INTERVAL 90 DAY)),
  (2, 'Donation history analytics (de-identified)',
   'Donation count, blood type, region (no PII)',
   'Legitimate interest (public health)',
   'low',
   'Re-identification risk if cohort size < 5.',
   'Suppress cohorts < 5 persons; no names, emails, or device IDs exported.',
   0, 'approved',
   CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY));

-- Record migration.
INSERT INTO `schema_migrations` (`version`) VALUES ('013_compliance_program')
ON DUPLICATE KEY UPDATE `version` = `version`;
