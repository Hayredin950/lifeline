#!/usr/bin/env php
<?php
/**
 * LifeLine — Cold-archive worker (P3 · Doc 12 Tier 3)
 *
 * Moves rows older than ARCHIVE_RETENTION_MONTHS (default 24) from the four
 * hot tables into their _archive mirrors, then deletes from live tables.
 * Safe to re-run (idempotent: INSERT IGNORE on archive, DELETE only after copy).
 *
 * Usage:
 *   php worker/archive_old_data.php               # dry-run: shows row counts
 *   php worker/archive_old_data.php --write        # execute moves
 *   php worker/archive_old_data.php --write --table=audit_logs
 *   php worker/archive_old_data.php --write --months=36
 *
 * Cron (nightly at 02:17):
 *   17 2 * * * php /path/to/worker/archive_old_data.php --write >> /var/log/lifeline-archive.log 2>&1
 */

define('APP_ROOT', dirname(__DIR__) . '/lifeline');
define('WORKER_MODE', true);

require_once APP_ROOT . '/includes/functions.php';

// ── CLI argument parsing ─────────────────────────────────────────────────────
$opts   = getopt('', ['write', 'table:', 'months:']);
$dryRun = !isset($opts['write']);
$only   = isset($opts['table']) ? $opts['table'] : null;
$months = isset($opts['months']) ? max(1, (int)$opts['months'])
        : Config::getInt('ARCHIVE_RETENTION_MONTHS', 24);

$cutoff = date('Y-m-d', strtotime("-{$months} months"));

echo date('[Y-m-d H:i:s]') . " Archive worker start"
   . ($dryRun ? ' [DRY-RUN — pass --write to commit]' : ' [WRITE]') . "\n";
echo date('[Y-m-d H:i:s]') . " Cutoff: rows created before {$cutoff} ({$months} months)\n";

// ── Tables to archive ───────────────────────────────────────────────────────
// Each entry: [live_table, archive_table, date_col, extra_where]
$TABLES = [
    [
        'live'    => 'audit_logs',
        'archive' => 'audit_logs_archive',
        'col'     => 'created_at',
        'where'   => '',
    ],
    [
        'live'    => 'messages',
        'archive' => 'messages_archive',
        'col'     => 'created_at',
        // Only archive soft-deleted messages; undeleted messages are still active
        'where'   => ' AND deleted_at IS NOT NULL',
    ],
    [
        'live'    => 'notifications',
        'archive' => 'notifications_archive',
        'col'     => 'created_at',
        // Only archive already-read notifications
        'where'   => ' AND is_read = 1',
    ],
    [
        'live'    => 'donation_history',
        'archive' => 'donation_history_archive',
        'col'     => 'created_at',
        'where'   => '',
    ],
];

$totalMoved = 0;
$errors     = 0;
$BATCH      = 1000;  // rows per INSERT…SELECT to avoid lock spikes

foreach ($TABLES as $spec) {
    if ($only !== null && $only !== $spec['live']) {
        continue;
    }

    $live    = $spec['live'];
    $archive = $spec['archive'];
    $col     = $spec['col'];
    $extra   = $spec['where'];

    // ── Count rows eligible ──────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$live}` WHERE `{$col}` < ?{$extra}"
        );
        $stmt->execute([$cutoff]);
        $eligible = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        echo date('[Y-m-d H:i:s]') . " ERROR counting {$live}: " . $e->getMessage() . "\n";
        $errors++;
        continue;
    }

    echo date('[Y-m-d H:i:s]') . " {$live}: {$eligible} rows eligible\n";

    if ($eligible === 0 || $dryRun) {
        continue;
    }

    // ── Log run start ────────────────────────────────────────────────────────
    $runId = null;
    try {
        $ins = $pdo->prepare(
            "INSERT INTO archive_runs (table_name, rows_moved, cutoff_date, status)
             VALUES (?, 0, ?, 'running')"
        );
        $ins->execute([$live, $cutoff]);
        $runId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        // Non-fatal: run without a log row if archive_runs table not yet migrated.
    }

    $moved = 0;

    // ── Batch copy → delete loop ─────────────────────────────────────────────
    try {
        while (true) {
            $pdo->beginTransaction();

            // Step 1: copy batch to archive (INSERT IGNORE for idempotency)
            $copy = $pdo->prepare(
                "INSERT IGNORE INTO `{$archive}`
                 SELECT *, NOW() AS archived_at
                 FROM   `{$live}`
                 WHERE  `{$col}` < ?{$extra}
                 ORDER BY `id`
                 LIMIT {$BATCH}"
            );
            $copy->execute([$cutoff]);
            $copied = $copy->rowCount();

            if ($copied === 0) {
                $pdo->rollBack();
                break;
            }

            // Step 2: delete the same batch (join to archive to be precise)
            $del = $pdo->prepare(
                "DELETE l FROM `{$live}` l
                 INNER JOIN `{$archive}` a ON a.id = l.id
                 WHERE l.`{$col}` < ?{$extra}
                 LIMIT {$BATCH}"
            );
            $del->execute([$cutoff]);

            $pdo->commit();
            $moved += $copied;
            echo date('[Y-m-d H:i:s]') . "   {$live}: moved batch, total {$moved}/{$eligible}\n";
        }

        $totalMoved += $moved;

        // ── Update run log ───────────────────────────────────────────────────
        if ($runId !== null) {
            $pdo->prepare(
                "UPDATE archive_runs
                 SET rows_moved = ?, finished_at = NOW(), status = 'done'
                 WHERE id = ?"
            )->execute([$moved, $runId]);
        }

        echo date('[Y-m-d H:i:s]') . " {$live}: done, {$moved} rows moved\n";

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo date('[Y-m-d H:i:s]') . " ERROR archiving {$live}: " . $e->getMessage() . "\n";
        $errors++;

        if ($runId !== null) {
            try {
                $pdo->prepare(
                    "UPDATE archive_runs
                     SET finished_at = NOW(), status = 'error', error_msg = ?
                     WHERE id = ?"
                )->execute([substr($e->getMessage(), 0, 500), $runId]);
            } catch (PDOException $ignored) {}
        }
    }
}

$status = $errors > 0 ? 'FINISHED WITH ERRORS' : 'FINISHED OK';
echo date('[Y-m-d H:i:s]') . " {$status}: {$totalMoved} total rows moved, {$errors} error(s)\n";
exit($errors > 0 ? 1 : 0);
