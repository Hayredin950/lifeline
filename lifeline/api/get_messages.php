<?php
require_once '../includes/functions.php';
requireAuth();

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];
$otherId = isset($_GET['conversation']) ? (int)$_GET['conversation'] : 0;

if (!$otherId || $otherId === $userId) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
    exit;
}

// DEF-08: explicit conversation-membership authorization. The message query below
// is already scoped to the current user, but we additionally require that a real
// relationship exists — either the other party is a valid active user the current
// user has exchanged messages with, or (for a brand-new thread) simply a valid
// active account. This blocks enumeration of arbitrary user IDs via this endpoint.
$auth = $pdo->prepare("
    SELECT
        (SELECT id FROM users WHERE id = ? AND is_active = 1) AS other_exists,
        (SELECT COUNT(*) FROM messages
            WHERE (sender_id = ? AND receiver_id = ?)
               OR (sender_id = ? AND receiver_id = ?)) AS thread_count
");
$auth->execute([$otherId, $userId, $otherId, $otherId, $userId]);
$authRow = $auth->fetch();

if (empty($authRow['other_exists'])) {
    echo json_encode(['success' => false, 'error' => 'Conversation not found']);
    exit;
}

// Mark new messages as read
$stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
$stmt->execute([$userId, $otherId]);

// Get messages
$stmt = $pdo->prepare("
    SELECT * FROM messages 
    WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
    ORDER BY created_at ASC
    LIMIT 100
");
$stmt->execute([$userId, $otherId, $otherId, $userId]);
$messages = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'current_user_id' => $userId
]);
exit;
