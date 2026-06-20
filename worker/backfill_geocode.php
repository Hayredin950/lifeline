<?php
/**
 * LifeLine Blood Network — Geocode backfill (DEF-09 / FR-13)
 *
 * Populates latitude/longitude on existing donor_profiles and hospital_profiles rows
 * that have a city but null coords. Safe to re-run: rows that already have coords are
 * skipped. Respects Nominatim's 1 req/s fair-use policy via sleep(1) between calls.
 *
 *   php worker/backfill_geocode.php            # dry-run (shows what would be updated)
 *   php worker/backfill_geocode.php --write    # apply updates to the DB
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../lifeline/includes/functions.php';

$write = in_array('--write', $argv, true);

function logLine(string $msg): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n");
}

function backfillTable(PDO $pdo, string $table, string $nameCol, bool $write): void {
    $stmt = $pdo->query("
        SELECT user_id, $nameCol AS display_name, city, state, country
        FROM $table
        WHERE city IS NOT NULL AND city <> ''
          AND (latitude IS NULL OR longitude IS NULL)
    ");
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        logLine("$table: nothing to backfill.");
        return;
    }

    logLine("$table: " . count($rows) . " rows to geocode" . ($write ? '' : ' (dry-run)') . '.');

    $updated = 0;
    $failed  = 0;
    foreach ($rows as $i => $row) {
        $coords = geocodeLocation(
            $row['city'],
            $row['state'] ?? '',
            $row['country'] ?? 'India'
        );

        if ($coords === null) {
            logLine("  MISS  [{$row['display_name']}] {$row['city']}, {$row['state']}");
            $failed++;
        } else {
            logLine(sprintf(
                '  %s [%s] %s, %s → %.5f, %.5f',
                $write ? 'OK  ' : 'DRYRUN',
                $row['display_name'],
                $row['city'],
                $row['state'],
                $coords['latitude'],
                $coords['longitude']
            ));
            if ($write) {
                $upd = $pdo->prepare("
                    UPDATE $table SET latitude = ?, longitude = ? WHERE user_id = ?
                ");
                $upd->execute([$coords['latitude'], $coords['longitude'], $row['user_id']]);
            }
            $updated++;
        }

        // Nominatim fair-use: max 1 req/s
        if ($i < count($rows) - 1) {
            sleep(1);
        }
    }

    logLine("$table: done. geocoded=$updated, missed=$failed.");
}

backfillTable($pdo, 'donor_profiles',   'full_name',    $write);
backfillTable($pdo, 'hospital_profiles', 'hospital_name', $write);

if (!$write) {
    logLine("Dry-run complete. Pass --write to apply changes.");
}
