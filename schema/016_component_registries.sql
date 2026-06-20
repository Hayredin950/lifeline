-- Migration 016: Plasma / platelet / bone-marrow / organ-donor registries (P4.2)
-- Extends the donor profile to declare which blood components they can donate,
-- separate from whole-blood eligibility.

-- ── Component type catalogue ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `donation_components` (
    `id`                TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`              VARCHAR(30)      NOT NULL UNIQUE COMMENT 'e.g. plasma, platelets, bone_marrow, organ_cornea',
    `label`             VARCHAR(80)      NOT NULL,
    `cooloff_days`      SMALLINT UNSIGNED NOT NULL DEFAULT 28,
    `requires_hla`      TINYINT(1)       NOT NULL DEFAULT 0 COMMENT 'Bone marrow / organ: HLA typing required',
    `requires_hospital_link` TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'Must be linked to a hospital for this type',
    `is_active`         TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `donation_components` (`code`, `label`, `cooloff_days`, `requires_hla`, `requires_hospital_link`) VALUES
('whole_blood', 'Whole Blood',             56,  0, 0),
('plasma',      'Plasma',                  28,  0, 0),
('platelets',   'Platelets (Apheresis)',   14,  0, 0),
('rbc',         'Red Blood Cells',         56,  0, 0),
('bone_marrow', 'Bone Marrow',            365,  1, 1),
('pbsc',        'Peripheral Blood Stem Cells (PBSC)', 365, 1, 1),
('organ_kidney','Organ — Kidney (living)',   0,  1, 1),
('organ_liver', 'Organ — Liver (partial)',  0,  1, 1),
('organ_cornea','Cornea',                   0,  0, 1);

-- ── Donor component registrations ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `donor_component_registrations` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `donor_id`       INT          NOT NULL,
    `component_code` VARCHAR(30)  NOT NULL,
    `hla_type`       VARCHAR(100) DEFAULT NULL COMMENT 'HLA typing result for marrow/organ donors',
    `hospital_id`    INT          DEFAULT NULL COMMENT 'Linked hospital for organ/marrow programmes',
    `consent_date`   DATE         NOT NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `last_donated_at` DATE        DEFAULT NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_donor_component` (`donor_id`, `component_code`),
    KEY `ix_comp_code_active`   (`component_code`, `is_active`),
    CONSTRAINT `dcr_donor_fk`   FOREIGN KEY (`donor_id`)   REFERENCES `users`             (`id`) ON DELETE CASCADE,
    CONSTRAINT `dcr_hosp_fk`    FOREIGN KEY (`hospital_id`) REFERENCES `hospital_profiles` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Component blood requests (hospitals can request specific components) ────────
-- Guard: only add if not already present.
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blood_requests' AND COLUMN_NAME = 'component_code'
);
SET @alter_sql = IF(@col_exists = 0,
    "ALTER TABLE `blood_requests` ADD COLUMN `component_code` VARCHAR(30) DEFAULT 'whole_blood' COMMENT 'Which blood component is requested'",
    'SELECT 1'
);
PREPARE _stmt FROM @alter_sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('016_component_registries');
