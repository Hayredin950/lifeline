<?php
require_once '../includes/functions.php';
requireAuth();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$content = trim($_POST['content'] ?? '');

if (!$messageId || empty($content)) {
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
    // Only allow sender to edit their own message
    $stmt = $pdo->prepare("UPDATE messages SET content = ?, is_edited = 1 WHERE id = ? AND sender_id = ?");
    $stmt->execute([$content, $messageId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Message not found or access denied']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
