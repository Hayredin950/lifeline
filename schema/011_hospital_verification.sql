-- Migration 011: Hospital verification workflow
-- Adds document upload path and pending/approved/rejected status.

ALTER TABLE `hospital_profiles`
    ADD COLUMN `verification_status` ENUM('unsubmitted','pending','approved','rejected')
        NOT NULL DEFAULT 'unsubmitted',
    ADD COLUMN `verification_doc`    VARCHAR(500) DEFAULT NULL
        COMMENT 'Server path to uploaded evidence file (kept outside web root)',
    ADD COLUMN `verification_note`   VARCHAR(500) DEFAULT NULL
        COMMENT 'Admin note shown to hospital on rejection';

-- Back-fill: any row already marked is_verified=1 is considered approved.
UPDATE `hospital_profiles` SET `verification_status` = 'approved' WHERE `is_verified` = 1;

INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('011');
