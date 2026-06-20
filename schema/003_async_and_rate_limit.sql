-- =============================================================================
-- LifeLine Blood Network — Async fan-out + DB-backed rate limiting
-- Migration: 003_async_and_rate_limit
-- Depends on: 001_init
--
-- Adds the infrastructure that closes DEF-02 / DEF-03 (Emergency-SOS abuse and
-- synchronous email fan-out) and DEF-12 (session-only rate limiting) using only
-- the mandated PHP + MySQL stack — no Redis required for the pilot tier.
--
--   * rate_limits        — atomic per-key fixed-window counters (per-IP, per-phone)
--   * notification_queue — durable outbox; a CLI worker drains it off the request path
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- -----------------------------------------------------------------------------
-- rate_limits — fixed-window counter, one row per limited key (DEF-12)
--   rate_key examples: 'sos_ip:203.0.113.7', 'sos_phone:+9198…', 'login:foo@bar'
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `rate_key`          VARCHAR(191) NOT NULL,
  `hits`              INT NOT NULL DEFAULT 0,
  `window_started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rate_key`),
  KEY `ix_rl_window` (`window_started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- notification_queue — durable outbox drained by worker/process_notifications.php
--   Closes DEF-03: requests enqueue (fast) instead of sending email inline.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notification_queue` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `channel`      VARCHAR(20) NOT NULL DEFAULT 'email',
  `template`     VARCHAR(50) NOT NULL,                 -- e.g. 'blood_request'
  `recipient`    VARCHAR(255) NOT NULL,                -- email address
  `payload`      TEXT NOT NULL,                        -- JSON: template variables
  `status`       VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending|processing|sent|failed
  `attempts`     INT NOT NULL DEFAULT 0,
  `last_error`   TEXT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_queue_status` (`status`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `schema_migrations` (`version`) VALUES ('003_async_and_rate_limit')
ON DUPLICATE KEY UPDATE `version` = `version`;
