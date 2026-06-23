<?php
require_once '../includes/functions.php';
requireDonor();

$userId = $_SESSION['user_id'];

// Fetch user data
$user = getUserById($pdo, $userId);
$profile = getDonorProfile($pdo, $userId);

// Fetch donation history
$stmt = $pdo->prepare("
    SELECT dh.*, br.patient_blood_type, br.units_needed, hp.hospital_name
    FROM donation_history dh
    LEFT JOIN blood_requests br ON dh.request_id = br.id
    LEFT JOIN hospital_profiles hp ON dh.hospital_id = hp.user_id
    WHERE dh.donor_id = ?
    ORDER BY dh.donation_date DESC
");
$stmt->execute([$userId]);
$donations = $stmt->fetchAll();

// Fetch achievements
$stmt = $pdo->prepare("SELECT * FROM achievements WHERE donor_id = ? ORDER BY earned_at DESC");
$stmt->execute([$userId]);
$achievements = $stmt->fetchAll();

// Prepare CSV data
$headers = ['Type', 'Field', 'Value'];
$rows = [];

// User account info
$rows[] = ['Account', 'User ID', $user['id']];
$rows[] = ['Account', 'Email', $user['email']];
$rows[] = ['Account', 'Role', $user['role']];
$rows[] = ['Account', 'Created At', $user['created_at']];

// Donor profile info
$rows[] = ['Profile', 'Full Name', $profile['full_name']];
$rows[] = ['Profile', 'Phone', $profile['phone']];
$rows[] = ['Profile', 'Blood Type', $profile['blood_type']];
$rows[] = ['Profile', 'Address', $profile['address']];
$rows[] = ['Profile', 'City', $profile['city']];
$rows[] = ['Profile', 'State', $profile['state']];
$rows[] = ['Profile', 'Country', $profile['country']];
$rows[] = ['Profile', 'Date of Birth', $profile['date_of_birth']];
$rows[] = ['Profile', 'Gender', $profile['gender']];
$rows[] = ['Profile', 'Total Donations', $profile['total_donations']];
$rows[] = ['Profile', 'Tier', $profile['tier']];
$rows[] = ['Profile', 'Donation Points', $profile['donation_points']];
$rows[] = ['Profile', 'Is Verified', $profile['is_verified'] ? 'Yes' : 'No'];

// Add donation history
if (!empty($donations)) {
    $rows[] = ['Donations', '', ''];
    $rows[] = ['Donations', 'Donation ID', 'Date', 'Hospital', 'Blood Type', 'Units'];
    foreach ($donations as $d) {
        $rows[] = ['Donations', $d['id'], $d['donation_date'], $d['hospital_name'] ?? 'Emergency', $d['blood_type'], $d['units']];
    }
}

// Add achievements
if (!empty($achievements)) {
    $rows[] = ['Achievements', '', ''];
    $rows[] = ['Achievements', 'Type', 'Title', 'Description', 'Earned At'];
    foreach ($achievements as $a) {
        $rows[] = ['Achievements', $a['type'], $a['title'], $a['description'], $a['earned_at']];
    }
}

// Export
exportToCsv($headers, $rows, 'lifeline_data_export_' . date('Y-m-d') . '.csv');
