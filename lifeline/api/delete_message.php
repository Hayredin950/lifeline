<?php
require_once '../includes/functions.php';
requireAuth();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;

if (!$messageId) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

try {
    // Only allow sender to delete their own message
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->execute([$messageId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Message not found or access denied']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
