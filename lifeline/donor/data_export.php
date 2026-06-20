<?php
/**
 * DSAR data export — downloads all personal data for the logged-in donor as JSON.
 * Doc 07 §6 / FR-49
 */
require_once '../includes/functions.php';
requireDonor();

$userId = $_SESSION['user_id'];

// --- Account ---
$stmt = $pdo->prepare("SELECT id, email, role, is_active, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Profile ---
$stmt = $pdo->prepare("
    SELECT full_name, phone, blood_type, address, city, state, country,
           date_of_birth, gender, is_available, total_donations, donation_points,
           tier, last_donation_date, latitude, longitude, email_notif_prefs, created_at
    FROM donor_profiles WHERE user_id = ?
");
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// --- Donation history ---
$stmt = $pdo->prepare("
    SELECT dh.donation_date, dh.blood_type, dh.units,
           hp.hospital_name, hp.city AS hospital_city
    FROM donation_history dh
    LEFT JOIN hospital_profiles hp ON dh.hospital_id = hp.user_id
    WHERE dh.donor_id = ?
    ORDER BY dh.donation_date DESC
");
$stmt->execute([$userId]);
$donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Match history ---
$stmt = $pdo->prepare("
    SELECT dm.status, dm.created_at,
           br.patient_blood_type, br.urgency,
           hp.hospital_name
    FROM donor_matches dm
    JOIN blood_requests br ON dm.request_id = br.id
    LEFT JOIN hospital_profiles hp ON br.hospital_id = hp.user_id
    WHERE dm.donor_id = ?
    ORDER BY dm.created_at DESC
");
$stmt->execute([$userId]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Messages (sent and received) ---
$stmt = $pdo->prepare("
    SELECT sender_id, receiver_id, content, created_at, is_edited
    FROM messages
    WHERE sender_id = ? OR receiver_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$userId, $userId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Notifications ---
$stmt = $pdo->prepare("
    SELECT type, title, message, is_read, created_at
    FROM notifications WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Achievements ---
$stmt = $pdo->prepare("SELECT type, title, description, earned_at FROM achievements WHERE donor_id = ?");
$stmt->execute([$userId]);
$achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Testimonials ---
$stmt = $pdo->prepare("SELECT recipient_name, story, rating, is_approved, created_at FROM testimonials WHERE donor_id = ?");
$stmt->execute([$userId]);
$testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Consent log ---
$stmt = $pdo->prepare("SELECT terms_version, ip_address, consented_at FROM consent_log WHERE user_id = ?");
$stmt->execute([$userId]);
$consents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compose export.
$export = [
    'exported_at'    => date('c'),
    'account'        => $account,
    'profile'        => $profile,
    'donations'      => $donations,
    'match_history'  => $matches,
    'messages'       => $messages,
    'notifications'  => $notifications,
    'achievements'   => $achievements,
    'testimonials'   => $testimonials,
    'consent_log'    => $consents,
];

// Log the export request.
auditLog($pdo, 'dsar_export', 'user', $userId, null, ['format' => 'json']);

// Stream as JSON download.
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="lifeline-data-export-' . $userId . '-' . date('Ymd') . '.json"');
header('Cache-Control: no-cache');
echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
