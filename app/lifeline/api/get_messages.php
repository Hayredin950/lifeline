<?php
require_once '../includes/functions.php';
requireAuth();

$userId = $_SESSION['user_id'];
$otherId = isset($_GET['conversation']) ? (int)$_GET['conversation'] : 0;

if (!$otherId) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
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

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'messages' => $messages,
    'current_user_id' => $userId
]);
exit;
