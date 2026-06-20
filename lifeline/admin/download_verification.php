<?php
/**
 * Admin-only: streams a hospital verification document from the uploads directory.
 * Files are outside the web root (or behind .htaccess Deny) so they cannot be
 * fetched directly by URL — this endpoint checks session auth before serving.
 */
require_once '../includes/functions.php';
requireAdmin();

$userId = (int)($_GET['user_id'] ?? 0);
if (!$userId) {
    http_response_code(400);
    exit('Invalid request.');
}

$stmt = $pdo->prepare("SELECT verification_doc FROM hospital_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$doc = $stmt->fetchColumn();

if (!$doc) {
    http_response_code(404);
    exit('No document on file.');
}

// Strip any directory traversal from the stored filename.
$safe = basename($doc);
$path = __DIR__ . '/../../uploads/verification/' . $safe;

if (!file_exists($path) || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($path);
$allowed  = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
if (!isset($allowed[$mimeType])) {
    http_response_code(415);
    exit('Unsupported file type.');
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $safe . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
