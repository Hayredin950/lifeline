<?php
/**
 * LifeLine Blood Network — Notification queue worker (DEF-03)
 *
 * Drains `notification_queue` and delivers messages via EmailService, OFF the
 * web-request path. Run from cron every minute, or with --loop as a daemon.
 *
 *   php worker/process_notifications.php            # process all pending once, then exit
 *   php worker/process_notifications.php --loop     # run continuously (sleep between batches)
 *   php worker/process_notifications.php --limit=100 # cap rows processed this run
 *
 * Safe to run multiple instances: each row is claimed atomically (pending -> processing)
 * by exactly one worker before it is sent.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

require_once __DIR__ . '/../lifeline/includes/functions.php';
require_once __DIR__ . '/../lifeline/includes/email_service.php';

$opts = getopt('', ['loop', 'limit::', 'batch::', 'sleep::']);
$loop      = isset($opts['loop']);
$limit     = isset($opts['limit']) ? max(1, (int)$opts['limit']) : 0;     // 0 = unlimited
$batchSize = isset($opts['batch']) ? max(1, (int)$opts['batch']) : 25;
$sleepSecs = isset($opts['sleep']) ? max(1, (int)$opts['sleep']) : 5;
$maxAttempts = 5;

function logLine(string $msg): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n");
}

/** Deliver one claimed row. Returns true on success. */
function deliver(array $row): bool {
    $payload = json_decode($row['payload'], true) ?: [];

    switch ($row['template']) {
        case 'blood_request':
            return EmailService::sendBloodRequestNotification(
                $row['recipient'],
                $payload['donor_name'] ?? 'Donor',
                $payload['request'] ?? []
            );
        case 'donor_welcome':
            return EmailService::sendDonorWelcome($row['recipient'], $payload['name'] ?? '');
        case 'hospital_welcome':
            return EmailService::sendHospitalWelcome($row['recipient'], $payload['name'] ?? '');
        case 'password_reset':
            return EmailService::sendPasswordReset($row['recipient'], $payload['link'] ?? '');
        case 'email_change':
            // Verified email-change confirmation (DEF-07) — pre-composed subject/body.
            return EmailService::send($row['recipient'], $payload['subject'] ?? 'Confirm your email', $payload['body'] ?? '');
        default:
            // Generic fallback
            return EmailService::send($row['recipient'], $payload['subject'] ?? 'Notification', $payload['body'] ?? '');
    }
}

$processed = 0;

do {
    // Identify the exact IDs to claim before touching status, so concurrent workers
    // each operate on a disjoint set and cannot double-send.
    $idRows = $pdo->query("
        SELECT id FROM notification_queue
        WHERE status = 'pending'
        ORDER BY id ASC
        LIMIT {$batchSize}
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($idRows)) {
        if ($loop) { sleep($sleepSecs); continue; }
        break;
    }

    $placeholders = implode(',', array_fill(0, count($idRows), '?'));
    $claimStmt = $pdo->prepare("
        UPDATE notification_queue
        SET status = 'processing'
        WHERE id IN ($placeholders) AND status = 'pending'
    ");
    $claimStmt->execute($idRows);
    $claimed = $claimStmt->rowCount();

    if ($claimed === 0) {
        // Another worker snatched all of them; try the next iteration.
        if ($loop) { sleep($sleepSecs); continue; }
        break;
    }

    // Fetch only the rows we actually claimed (not any other worker's batch).
    $fetchStmt = $pdo->prepare("SELECT * FROM notification_queue WHERE id IN ($placeholders) AND status = 'processing' ORDER BY id ASC");
    $fetchStmt->execute($idRows);
    $rows = $fetchStmt->fetchAll();

    foreach ($rows as $row) {
        $ok = false;
        $err = null;
        try {
            $ok = deliver($row);
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }

        if ($ok) {
            $u = $pdo->prepare("UPDATE notification_queue SET status='sent', processed_at=NOW(), attempts=attempts+1 WHERE id=?");
            $u->execute([$row['id']]);
        } else {
            $attempts = (int)$row['attempts'] + 1;
            $status = $attempts >= $maxAttempts ? 'failed' : 'pending';
            $u = $pdo->prepare("UPDATE notification_queue SET status=?, attempts=?, last_error=? WHERE id=?");
            $u->execute([$status, $attempts, $err ?? 'delivery returned false', $row['id']]);
        }

        $processed++;
        if ($limit && $processed >= $limit) { break 2; }
    }

    logLine("processed {$claimed} (running total {$processed})");
} while ($loop || $claimed > 0);

logLine("done — {$processed} notification(s) processed");
