-- Migration 017: Inter-facility cold-chain blood unit transfer matching + tracking (P4.3)
-- Hospitals register available units; other hospitals request transfer; lifecycle tracked.

-- ── Blood unit inventory (available units at a hospital) ────────────────────────
CREATE TABLE IF NOT EXISTS `blood_unit_inventory` (
    `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `hospital_id`    INT              NOT NULL COMMENT 'Owning hospital (hospital_profiles.user_id)',
    `blood_type`     ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    `component_code` VARCHAR(30)      NOT NULL DEFAULT 'whole_blood',
    `units`          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `storage_temp_c` DECIMAL(4,1)     NOT NULL DEFAULT 4.0 COMMENT 'Required storage temp °C',
    `expiry_date`    DATE             NOT NULL,
    `is_available`   TINYINT(1)       NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_inv_hosp_avail`   (`hospital_id`, `is_available`),
    KEY `ix_inv_type_comp`    (`blood_type`, `component_code`, `is_available`),
    CONSTRAINT `bui_hosp_fk`  FOREIGN KEY (`hospital_id`) REFERENCES `hospital_profiles` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Inter-facility transfer requests ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `blood_unit_transfers` (
    `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `requesting_hosp` INT              NOT NULL COMMENT 'Hospital requesting units',
    `supplying_hosp`  INT              NOT NULL COMMENT 'Hospital supplying units (may be NULL until matched)',
    `inventory_id`    INT UNSIGNED     DEFAULT NULL COMMENT 'Linked inventory row if matched to a specific lot',
    `blood_type`      ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    `component_code`  VARCHAR(30)      NOT NULL DEFAULT 'whole_blood',
    `units_requested` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `units_confirmed` SMALLINT UNSIGNED DEFAULT NULL,
    `storage_temp_c`  DECIMAL(4,1)     NOT NULL DEFAULT 4.0,
    `urgency`         ENUM('routine','urgent','critical') NOT NULL DEFAULT 'routine',
    `status`          ENUM('requested','accepted','in_transit','received','rejected','cancelled') NOT NULL DEFAULT 'requested',
    `status_note`     VARCHAR(500)     DEFAULT NULL,
    `dispatched_at`   TIMESTAMP        NULL DEFAULT NULL,
    `received_at`     TIMESTAMP        NULL DEFAULT NULL,
    `created_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_xfer_req_hosp`  (`requesting_hosp`, `status`),
    KEY `ix_xfer_sup_hosp`  (`supplying_hosp`,  `status`),
    CONSTRAINT `xfer_req_fk` FOREIGN KEY (`requesting_hosp`) REFERENCES `hospital_profiles` (`user_id`),
    CONSTRAINT `xfer_sup_fk` FOREIGN KEY (`supplying_hosp`)  REFERENCES `hospital_profiles` (`user_id`),
    CONSTRAINT `xfer_inv_fk` FOREIGN KEY (`inventory_id`)    REFERENCES `blood_unit_inventory` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('017_cold_chain_transfers');
