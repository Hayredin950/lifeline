<?php
require_once '../includes/functions.php';
requireAuth();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$receiverId = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$content = trim($_POST['content'] ?? '');

if (!$receiverId || empty($content)) {
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
        'New message from ' . $senderName, 
        'You have received a new message.', 
        "/messages.php?conversation=$userId"
    ]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
