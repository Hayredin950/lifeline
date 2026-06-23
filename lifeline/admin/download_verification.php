<?php
require_once '../includes/functions.php';
requireAdmin();

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$userId) {
    setFlash('Invalid request', 'danger');
    redirect(baseUrl() . '/admin/verify_hospitals.php');
}

// Get verification document
$stmt = $pdo->prepare("SELECT verification_doc, hospital_name FROM hospital_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$hospital = $stmt->fetch();

if (!$hospital || empty($hospital['verification_doc'])) {
    setFlash('No document found', 'danger');
    redirect(baseUrl() . '/admin/verify_hospitals.php');
}

// Make sure the file exists (upload dir is probably ../uploads/verification/)
$filePath = __DIR__ . '/../uploads/verification/' . $hospital['verification_doc'];
if (!file_exists($filePath)) {
    setFlash('Document not found on disk', 'danger');
    redirect(baseUrl() . '/admin/verify_hospitals.php');
}

// Send the file
header('Content-Type: ' . mime_content_type($filePath));
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
