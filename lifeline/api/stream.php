<?php
/**
 * LifeLine Blood Network — Server-Sent Events stream (FR-37 / Doc 06 §3)
 *
 * Replaces the 3-second jQuery setInterval poll with a server-push channel.
 * At Tier 0 scale this endpoint DB-polls on a short loop; a Redis pub/sub bridge
 * (Doc 12) can swap in at Tier 1 without changing the client contract.
 *
 *   GET /api/stream.php?conversation=<otherId>&since=<unix-ms>
 *
 * Emits SSE events:
 *   event: messages  — new messages in the conversation since `since`
 *   event: ping      — keepalive every 20 s (prevents proxy timeouts)
 *   event: error     — auth / param failures before stream opens
 *
 * The client passes `since` as the millisecond timestamp of the newest message
 * it already holds; only rows newer than that are returned. On first connection
 * `since` should be 0 to get the full thread.
 *
 * This endpoint runs for up to 30 s then closes with `event: close`. The client
 * reconnects automatically (EventSource standard retry). The short hang avoids
 * PHP's default 30-s max_execution_time on most shared hosts while still
 * dramatically reducing DB round-trips vs. polled AJAX.
 */

require_once '../includes/functions.php';

// Auth: must be logged in (any role).
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: text/event-stream');
    echo "event: error\ndata: {\"error\":\"unauthenticated\"}\n\n";
    exit;
}

$userId  = (int)$_SESSION['user_id'];
$otherId = isset($_GET['conversation']) ? (int)$_GET['conversation'] : 0;
$since   = isset($_GET['since']) ? (int)$_GET['since'] : 0;   // unix milliseconds

if (!$otherId || $otherId === $userId) {
    http_response_code(400);
    header('Content-Type: text/event-stream');
    echo "event: error\ndata: {\"error\":\"invalid_conversation\"}\n\n";
    exit;
}

// DEF-08: explicit membership check (mirrors get_messages.php).
$auth = $pdo->prepare("
    SELECT (SELECT id FROM users WHERE id = ? AND is_active = 1) AS other_exists
");
$auth->execute([$otherId]);
if (!$auth->fetchColumn()) {
    http_response_code(403);
    header('Content-Type: text/event-stream');
    echo "event: error\ndata: {\"error\":\"forbidden\"}\n\n";
    exit;
}

// SSE headers — disable all buffering so chunks reach the browser immediately.
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');     // nginx: disable proxy buffering
if (ob_get_level()) ob_end_clean();
if (function_exists('apache_setenv')) apache_setenv('no-gzip', 1);

set_time_limit(35);   // a little over the loop duration to be safe

$loopSeconds = 30;
$pollInterval = 1;    // poll DB every 1 s for faster message delivery
$start = time();
$lastPing = $start;

// Convert ms to a MySQL datetime string for comparison.
$sinceTs = date('Y-m-d H:i:s', (int)($since / 1000));

while (time() - $start < $loopSeconds) {
    if (connection_aborted()) break;

    // Fetch messages newer than $sinceTs in this conversation.
    $stmt = $pdo->prepare("
        SELECT m.*, u.email AS sender_email,
               UNIX_TIMESTAMP(m.created_at) * 1000 AS created_ms
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?)
            OR (m.sender_id = ? AND m.receiver_id = ?))
          AND m.created_at > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$userId, $otherId, $otherId, $userId, $sinceTs]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($rows)) {
        // Advance the cursor so we don't re-send the same rows next poll.
        $sinceTs = date('Y-m-d H:i:s', (int)(end($rows)['created_ms'] / 1000));
        $payload  = json_encode(['messages' => $rows]);
        echo "event: messages\n";
        echo "data: $payload\n\n";
        if (ob_get_level()) ob_flush();
        flush();
    }

    // Keepalive ping every 20 s.
    if (time() - $lastPing >= 20) {
        echo "event: ping\ndata: {}\n\n";
        if (ob_get_level()) ob_flush();
        flush();
        $lastPing = time();
    }

    sleep($pollInterval);
}

// Signal the client to reconnect (preserves the `since` cursor via query string).
echo "event: close\ndata: {}\n\n";
if (ob_get_level()) ob_flush();
flush();
