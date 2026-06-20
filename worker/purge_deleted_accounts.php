<?php
/**
 * LifeLine Blood Network — Retention purge job (FR-49)
 *
 * Hard-deletes user accounts that were soft-deleted more than RETENTION_YEARS
 * ago (default 7 years). The FK CASCADE on users → profiles/matches etc.
 * removes all associated rows automatically.
 *
 *   php worker/purge_deleted_accounts.php            # dry-run (no writes)
 *   php worker/purge_deleted_accounts.php --write    # actually purge
 *
 * Schedule: cron monthly is sufficient (e.g. "0 2 1 * *").
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

require_once __DIR__ . '/../lifeline/includes/functions.php';

$write = in_array('--write', $argv ?? [], true);

if (!$write) {
    fwrite(STDOUT, "[DRY-RUN] Pass --write to perform actual deletions.\n");
}

$cutoff = date('Y-m-d H:i:s', strtotime('-' . RETENTION_YEARS . ' years'));
fwrite(STDOUT, "Purge cutoff: $cutoff (RETENTION_YEARS=" . RETENTION_YEARS . ")\n");

// Preview candidates.
$stmt = $pdo->prepare("
    SELECT id, email, role, deleted_at
    FROM users
    WHERE deleted_at IS NOT NULL AND deleted_at < ?
    ORDER BY deleted_at ASC
");
$stmt->execute([$cutoff]);
$candidates = $stmt->fetchAll();

if (empty($candidates)) {
    fwrite(STDOUT, "No accounts eligible for purge.\n");
    exit(0);
}

fwrite(STDOUT, count($candidates) . " account(s) eligible for purge:\n");
foreach ($candidates as $u) {
    fwrite(STDOUT, "  id={$u['id']} role={$u['role']} deleted_at={$u['deleted_at']}\n");
}

if (!$write) {
    fwrite(STDOUT, "[DRY-RUN] No changes made. Re-run with --write to purge.\n");
    exit(0);
}

// Hard delete — FK CASCADE removes profiles, matches, notifications, etc.
$ids = array_column($candidates, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$del = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
$del->execute($ids);
$purged = $del->rowCount();

fwrite(STDOUT, "Purged $purged account(s).\n");
