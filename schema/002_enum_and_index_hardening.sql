-- =============================================================================
-- LifeLine Blood Network — Schema Hardening
-- Migration: 002_enum_and_index_hardening
-- Depends on: 001_init
--
-- Converts closed-set VARCHAR columns to ENUM so the database rejects invalid
-- values at write time. This closes DEF-05 / DEF-06 (missing enum/state-machine
-- validation) at the data layer — a defense-in-depth complement to the
-- server-side validation tracked in docs/15 Phase 0.3.
--
-- ⚠️ APPLY-ORDER NOTE: run this only AFTER the application has been updated to
-- (a) validate blood_type/urgency/status against the same value sets, and
-- (b) write only canonical values. Any pre-existing row with an out-of-set
-- value must be cleaned first, or the ALTER will fail. On a fresh install
-- (001 + seeds only) it applies cleanly.
-- =============================================================================

SET SQL_MODE = "STRICT_ALL_TABLES";

-- -- Sanity: surface any rows that would block the ENUM conversion ------------
-- SELECT DISTINCT role        FROM users           WHERE role        NOT IN ('donor','hospital','admin');
-- SELECT DISTINCT blood_type  FROM donor_profiles  WHERE blood_type  NOT IN ('A+','A-','B+','B-','AB+','AB-','O+','O-');
-- SELECT DISTINCT urgency     FROM blood_requests  WHERE urgency     NOT IN ('normal','urgent','critical');
-- SELECT DISTINCT status      FROM blood_requests  WHERE status      NOT IN ('open','fulfilled','cancelled');
-- SELECT DISTINCT status      FROM donor_matches   WHERE status      NOT IN ('pending','contacted','confirmed','donated','declined');

-- -----------------------------------------------------------------------------
-- users.role
-- -----------------------------------------------------------------------------
ALTER TABLE `users`
  MODIFY `role` ENUM('donor','hospital','admin') NOT NULL DEFAULT 'donor';

-- -----------------------------------------------------------------------------
-- donor_profiles.blood_type, tier, gender
-- -----------------------------------------------------------------------------
ALTER TABLE `donor_profiles`
  MODIFY `blood_type` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  MODIFY `tier`       ENUM('bronze','silver','gold','platinum') NOT NULL DEFAULT 'bronze',
  MODIFY `gender`     ENUM('male','female','other','prefer_not_to_say') DEFAULT NULL;

-- -----------------------------------------------------------------------------
-- blood_requests.patient_blood_type, urgency, status   (DEF-05)
-- -----------------------------------------------------------------------------
ALTER TABLE `blood_requests`
  MODIFY `patient_blood_type` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  MODIFY `urgency` ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal',
  MODIFY `status`  ENUM('open','fulfilled','cancelled') NOT NULL DEFAULT 'open';

-- -----------------------------------------------------------------------------
-- donor_matches.status — the match state machine   (DEF-06)
-- -----------------------------------------------------------------------------
ALTER TABLE `donor_matches`
  MODIFY `status` ENUM('pending','contacted','confirmed','donated','declined') NOT NULL DEFAULT 'pending';

-- -----------------------------------------------------------------------------
-- donation_history.blood_type  (keep nullable; historical records)
-- -----------------------------------------------------------------------------
ALTER TABLE `donation_history`
  MODIFY `blood_type` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL;

-- -----------------------------------------------------------------------------
-- audit_logs: native JSON for structured before/after diffs
-- -----------------------------------------------------------------------------
-- ALTER TABLE `audit_logs`
--   MODIFY `old_values` JSON NULL,
--   MODIFY `new_values` JSON NULL;
-- (Left commented: enable once auditLog() is confirmed to write valid JSON only.)

-- -----------------------------------------------------------------------------
-- Spatial-ready geo (FR-20) — OPTIONAL, advanced.
-- Adds a generated POINT column + SPATIAL index for ST_Distance_Sphere ranking,
-- replacing app-side Haversine-over-full-scan. Requires SRID-aware MySQL 8.
-- -----------------------------------------------------------------------------
-- ALTER TABLE `donor_profiles`
--   ADD COLUMN `geo` POINT
--     GENERATED ALWAYS AS (ST_SRID(POINT(`longitude`,`latitude`),4326)) STORED,
--   ADD SPATIAL INDEX `sx_donor_geo` (`geo`);
-- ALTER TABLE `hospital_profiles`
--   ADD COLUMN `geo` POINT
--     GENERATED ALWAYS AS (ST_SRID(POINT(`longitude`,`latitude`),4326)) STORED,
--   ADD SPATIAL INDEX `sx_hospital_geo` (`geo`);

INSERT INTO `schema_migrations` (`version`) VALUES ('002_enum_and_index_hardening')
ON DUPLICATE KEY UPDATE `version` = `version`;
