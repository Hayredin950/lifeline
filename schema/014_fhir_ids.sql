-- =============================================================================
-- Migration 014: Add FHIR R4 resource identifiers (P3 · Doc 06 FHIR integration)
--
-- Adds a stable fhir_id UUID column to each entity table that maps to a FHIR
-- resource type. The UUID is generated on first FHIR read (lazy), so existing
-- rows are fine — no backfill required.
--
-- Note: ADD COLUMN IF NOT EXISTS is not supported in MySQL (MariaDB only).
--       This migration is idempotent via the stored procedure below.
--
-- Apply:
--   mysql -u lifeline_user -p lifeline_db_mysql < schema/014_fhir_ids.sql
-- =============================================================================

DROP PROCEDURE IF EXISTS `_add_fhir_columns`;
DELIMITER //
CREATE PROCEDURE `_add_fhir_columns`()
BEGIN
    -- donors → Patient
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donor_profiles' AND COLUMN_NAME = 'fhir_id'
    ) THEN
        ALTER TABLE `donor_profiles`
          ADD COLUMN `fhir_id` CHAR(36) DEFAULT NULL COMMENT 'FHIR R4 Patient.id (UUID)' AFTER `id`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donor_profiles' AND INDEX_NAME = 'ix_donor_fhir_id'
    ) THEN
        ALTER TABLE `donor_profiles` ADD UNIQUE KEY `ix_donor_fhir_id` (`fhir_id`);
    END IF;

    -- hospitals → Organization
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hospital_profiles' AND COLUMN_NAME = 'fhir_id'
    ) THEN
        ALTER TABLE `hospital_profiles`
          ADD COLUMN `fhir_id` CHAR(36) DEFAULT NULL COMMENT 'FHIR R4 Organization.id (UUID)' AFTER `id`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hospital_profiles' AND INDEX_NAME = 'ix_hospital_fhir_id'
    ) THEN
        ALTER TABLE `hospital_profiles` ADD UNIQUE KEY `ix_hospital_fhir_id` (`fhir_id`);
    END IF;

    -- blood_requests → ServiceRequest
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blood_requests' AND COLUMN_NAME = 'fhir_id'
    ) THEN
        ALTER TABLE `blood_requests`
          ADD COLUMN `fhir_id` CHAR(36) DEFAULT NULL COMMENT 'FHIR R4 ServiceRequest.id (UUID)' AFTER `id`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blood_requests' AND INDEX_NAME = 'ix_request_fhir_id'
    ) THEN
        ALTER TABLE `blood_requests` ADD UNIQUE KEY `ix_request_fhir_id` (`fhir_id`);
    END IF;

    -- donation_history → Observation
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_history' AND COLUMN_NAME = 'fhir_id'
    ) THEN
        ALTER TABLE `donation_history`
          ADD COLUMN `fhir_id` CHAR(36) DEFAULT NULL COMMENT 'FHIR R4 Observation.id (UUID)' AFTER `id`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_history' AND INDEX_NAME = 'ix_donation_fhir_id'
    ) THEN
        ALTER TABLE `donation_history` ADD UNIQUE KEY `ix_donation_fhir_id` (`fhir_id`);
    END IF;
END //
DELIMITER ;

CALL `_add_fhir_columns`();
DROP PROCEDURE IF EXISTS `_add_fhir_columns`;

INSERT INTO `schema_migrations` (`version`) VALUES ('014_fhir_ids')
ON DUPLICATE KEY UPDATE `version` = `version`;
