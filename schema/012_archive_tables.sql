-- =============================================================================
-- Migration 012: Cold-archive shadow tables for time-bounded data (P3 · Doc 12 Tier 3)
--
-- Strategy: create _archive mirrors (no FK constraints, RANGE-partitioned by year).
-- The companion worker/archive_old_data.php moves rows older than the retention
-- threshold from the live tables into these archives on a nightly schedule.
--
-- Actual RANGE partitioning of live tables requires removing FK constraints and
-- a maintenance window (documented in docs/12 Tier-3). The archive tables are
-- pre-partitioned so the oldest data is immediately shardable.
--
-- Apply:
--   mysql -u lifeline_user -p lifeline_db_mysql < schema/012_archive_tables.sql
-- =============================================================================

-- Guard: idempotent via CREATE TABLE IF NOT EXISTS.
-- NOTE: created_at in archive tables is NOT NULL (required for RANGE partition key).

-- ---------------------------------------------------------------------------
-- audit_logs_archive
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs_archive` (
  `id`          INT(11)      NOT NULL,
  `user_id`     INT(11)      DEFAULT NULL,
  `action`      VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(100) DEFAULT NULL,
  `entity_id`   INT(11)      DEFAULT NULL,
  `old_values`  TEXT,
  `new_values`  TEXT,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `user_agent`  VARCHAR(500) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archived_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`, `created_at`),
  KEY `ix_arch_audit_action` (`action`, `created_at`),
  KEY `ix_arch_audit_user`   (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY RANGE (YEAR(`created_at`)) (
  PARTITION p2023 VALUES LESS THAN (2024),
  PARTITION p2024 VALUES LESS THAN (2025),
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION p2026 VALUES LESS THAN (2027),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- ---------------------------------------------------------------------------
-- messages_archive
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages_archive` (
  `id`          INT(11)      NOT NULL,
  `sender_id`   INT(11)      NOT NULL,
  `receiver_id` INT(11)      NOT NULL,
  `subject`     VARCHAR(255) DEFAULT NULL,
  `content`     TEXT         NOT NULL,
  `is_read`     TINYINT(1)   DEFAULT '0',
  `is_edited`   TINYINT(1)   DEFAULT '0',
  `deleted_at`  TIMESTAMP    NULL DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archived_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`, `created_at`),
  KEY `ix_arch_msg_thread` (`sender_id`, `receiver_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY RANGE (YEAR(`created_at`)) (
  PARTITION p2023 VALUES LESS THAN (2024),
  PARTITION p2024 VALUES LESS THAN (2025),
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION p2026 VALUES LESS THAN (2027),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- ---------------------------------------------------------------------------
-- notifications_archive
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications_archive` (
  `id`          INT(11)      NOT NULL,
  `user_id`     INT(11)      NOT NULL,
  `type`        VARCHAR(50)  NOT NULL,
  `title`       VARCHAR(255) NOT NULL,
  `message`     TEXT,
  `link`        VARCHAR(255) DEFAULT NULL,
  `is_read`     TINYINT(1)   DEFAULT '0',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archived_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`, `created_at`),
  KEY `ix_arch_notif_user` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY RANGE (YEAR(`created_at`)) (
  PARTITION p2023 VALUES LESS THAN (2024),
  PARTITION p2024 VALUES LESS THAN (2025),
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION p2026 VALUES LESS THAN (2027),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- ---------------------------------------------------------------------------
-- donation_history_archive
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donation_history_archive` (
  `id`            INT(11)    NOT NULL,
  `donor_id`      INT(11)    DEFAULT NULL,
  `request_id`    INT(11)    DEFAULT NULL,
  `hospital_id`   INT(11)    DEFAULT NULL,
  `donation_date` DATE       NOT NULL,
  `blood_type`    VARCHAR(5) DEFAULT NULL,
  `units`         INT(11)    DEFAULT '1',
  `created_at`    DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archived_at`   DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`, `created_at`),
  KEY `ix_arch_hist_donor` (`donor_id`, `donation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY RANGE (YEAR(`created_at`)) (
  PARTITION p2023 VALUES LESS THAN (2024),
  PARTITION p2024 VALUES LESS THAN (2025),
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION p2026 VALUES LESS THAN (2027),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- ---------------------------------------------------------------------------
-- archive_runs — operational log for the archive worker
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `archive_runs` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `table_name`  VARCHAR(100) NOT NULL,
  `rows_moved`  INT(11) NOT NULL DEFAULT 0,
  `cutoff_date` DATE NOT NULL,
  `started_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` TIMESTAMP NULL DEFAULT NULL,
  `status`      ENUM('running','done','error') NOT NULL DEFAULT 'running',
  `error_msg`   TEXT,
  PRIMARY KEY (`id`),
  KEY `ix_archive_run_table` (`table_name`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Record migration.
INSERT INTO `schema_migrations` (`version`) VALUES ('012_archive_tables')
ON DUPLICATE KEY UPDATE `version` = `version`;
