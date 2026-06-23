<?php
require_once '../includes/functions.php';
requireAuth();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$receiverId = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$content = trim($_POST['content'] ?? '');

if (!$receiverId || $content === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Cap message length to prevent abuse / oversized payloads (DEF-04 / FR-35).
const MAX_MESSAGE_LENGTH = 4000;
if (mb_strlen($content) > MAX_MESSAGE_LENGTH) {
    echo json_encode(['success' => false, 'error' => 'Message is too long (max ' . MAX_MESSAGE_LENGTH . ' characters).']);
    exit;
}

// You cannot message yourself.
if ($receiverId === (int)$userId) {
    echo json_encode(['success' => false, 'error' => 'Invalid recipient']);
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Verify the recipient is a real, active user before storing the message.
$check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
$check->execute([$receiverId]);
if (!$check->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Recipient not found']);
    exit;
}

try {
    // Content is stored RAW and escaped on output (client renders via .text()); never trust on render.
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $receiverId, $content]);
    
    // Notification logic
    $senderName = '';
    if (isDonor()) {
        $profile = getDonorProfile($pdo, $userId);
        $senderName = $profile['full_name'] ?? 'Donor';
    } elseif (isHospital()) {
        $profile = getHospitalProfile($pdo, $userId);
        $senderName = $profile['hospital_name'] ?? 'Hospital';
    }
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $receiverId,
        'message',
        'New message from ' . ($senderName ?: 'Admin'),
        'You have received a new message.',
        "/messages.php?conversation=$userId"
    ]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
