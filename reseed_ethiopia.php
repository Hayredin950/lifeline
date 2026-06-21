<?php
/**
 * Ethiopian data reseed script.
 * Run from project root: php reseed_ethiopia.php
 * Deletes all non-admin data then seeds realistic Ethiopian donors, hospitals, and blood requests.
 */

$host = '127.0.0.1';
$port = '3307';
$db   = 'lifeline_db_mysql';
$user = 'lifeline_user';
$pass = 'lifeline_pass';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage() . "\n");
}

echo "Connected to DB.\n";

// ── 1. Clear all non-admin data ───────────────────────────────────────────────
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$tables = [
    'donor_matches', 'donation_history', 'donation_history_archive',
    'blood_requests', 'blood_unit_inventory', 'blood_unit_transfers',
    'achievements', 'notifications', 'notifications_archive',
    'notification_queue', 'messages', 'messages_archive',
    'testimonials', 'dsar_requests', 'email_change_requests',
    'password_resets', 'consent_log', 'audit_logs', 'audit_logs_archive',
    'rate_limits', 'donor_propensity_scores', 'donor_component_registrations',
    'donation_components',
];
foreach ($tables as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
    echo "Cleared $t\n";
}

// Delete non-admin profiles and users
$pdo->exec("DELETE FROM donor_profiles WHERE user_id != 1");
$pdo->exec("DELETE FROM hospital_profiles WHERE user_id != 1");
$pdo->exec("DELETE FROM users WHERE id != 1");

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "All non-admin data removed.\n\n";

// ── 2. Seed hospitals ─────────────────────────────────────────────────────────
$hospitals = [
    // email, password, hospital_name, phone, address, city, state, license, lat, lng
    ['tikuranbesa@hospital.et', 'BlackLion@2025!', 'Tikur Anbesa Specialized Hospital', '+251-11-551-7400', 'Siddist Kilo, Churchill Avenue', 'Addis Ababa', 'Addis Ababa', 'ETH-HOS-001', 9.0347, 38.7612],
    ['stpauls@hospital.et',    'StPauls@2025!',   "St. Paul's Hospital Millennium Medical College", '+251-11-275-3286', 'Suba City, Gulele Sub-City', 'Addis Ababa', 'Addis Ababa', 'ETH-HOS-002', 9.0478, 38.7329],
    ['yekatit12@hospital.et',  'Yekatit@2025!',   'Yekatit 12 Hospital Medical College', '+251-11-512-2516', 'Piazza Area, Arada Sub-City', 'Addis Ababa', 'Addis Ababa', 'ETH-HOS-003', 9.0430, 38.7469],
    ['hawassauh@hospital.et',  'HawassaUH@2025!', 'Hawassa University Comprehensive Specialized Hospital', '+251-46-220-5217', 'Hawassa University Campus', 'Hawassa', 'SNNPR', 'ETH-HOS-004', 7.0504, 38.4955],
    ['jimmamc@hospital.et',    'JimmaUMC@2025!',  'Jimma University Medical Center', '+251-47-111-4992', 'Jimma University Campus, Kito Furdisa', 'Jimma', 'Oromia', 'ETH-HOS-005', 7.6761, 36.8375],
    ['gondarhospital@hospital.et', 'GondarHosp@2025!', 'University of Gondar Comprehensive Specialized Hospital', '+251-58-111-4150', 'Gondar Town, Gondar Zuria', 'Gondar', 'Amhara', 'ETH-HOS-006', 12.6093, 37.4595],
];

$hospitalUserIds = [];
foreach ($hospitals as $h) {
    $pdo->prepare("INSERT INTO users (email, password, role, is_active, email_verified_at) VALUES (?, ?, 'hospital', 1, NOW())")
        ->execute([$h[0], password_hash($h[1], PASSWORD_DEFAULT)]);
    $uid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO hospital_profiles (user_id, hospital_name, phone, address, city, state, country, license_number, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, 'Ethiopia', ?, ?, ?)")
        ->execute([$uid, $h[2], $h[3], $h[4], $h[5], $h[6], $h[7], $h[8], $h[9]]);
    $hospitalUserIds[$h[2]] = $uid;
    echo "Added hospital: {$h[2]}\n";
}

// ── 3. Seed donors ────────────────────────────────────────────────────────────
$donors = [
    // email, full_name, phone, blood_type, city, state, dob, gender, lat, lng, total_donations, tier, last_donation
    ['abebe.girma@lifeline.et',     'Abebe Girma',        '+251-91-234-5678', 'O+',  'Addis Ababa', 'Addis Ababa', '1990-03-14', 'male',   9.0347, 38.7612, 8,  'gold',     '2025-09-10'],
    ['tigist.bekele@lifeline.et',   'Tigist Bekele',      '+251-91-345-6789', 'A+',  'Addis Ababa', 'Addis Ababa', '1995-07-22', 'female', 9.0198, 38.7468, 3,  'silver',   '2025-11-05'],
    ['dawit.tesfaye@lifeline.et',   'Dawit Tesfaye',      '+251-91-456-7890', 'B+',  'Addis Ababa', 'Addis Ababa', '1988-11-30', 'male',   9.0508, 38.7632, 12, 'platinum', '2025-10-20'],
    ['selamawit.haile@lifeline.et', 'Selamawit Haile',    '+251-91-567-8901', 'O+',  'Hawassa',     'SNNPR',       '1993-01-18', 'female', 7.0548, 38.4978, 5,  'silver',   '2025-08-15'],
    ['yohannes.solomon@lifeline.et','Yohannes Solomon',   '+251-91-678-9012', 'A-',  'Hawassa',     'SNNPR',       '1985-05-09', 'male',   7.0612, 38.4920, 2,  'bronze',   '2025-12-01'],
    ['mekdes.tadesse@lifeline.et',  'Mekdes Tadesse',     '+251-91-789-0123', 'O-',  'Jimma',       'Oromia',      '1997-09-25', 'female', 7.6817, 36.8352, 1,  'bronze',   '2025-07-30'],
    ['mulugeta.alem@lifeline.et',   'Mulugeta Alemayehu', '+251-91-890-1234', 'B+',  'Gondar',      'Amhara',      '1982-12-03', 'male',   12.6076, 37.4611, 7, 'gold',    '2025-10-08'],
    ['rahel.wondimu@lifeline.et',   'Rahel Wondimu',      '+251-91-901-2345', 'A+',  'Addis Ababa', 'Addis Ababa', '1991-04-17', 'female', 9.0251, 38.7534, 4,  'silver',   '2025-09-22'],
    ['getachew.berhane@lifeline.et','Getachew Berhane',   '+251-92-012-3456', 'AB+', 'Mekelle',     'Tigray',      '1986-08-11', 'male',   13.4967, 39.4757, 6, 'gold',    '2025-11-18'],
    ['birtukan.girma@lifeline.et',  'Birtukan Girma',     '+251-92-123-4567', 'O+',  'Dire Dawa',   'Dire Dawa',   '1994-02-28', 'female', 9.5930, 41.8591, 0,  'bronze',   null],
    ['hiwot.daniel@lifeline.et',    'Hiwot Daniel',       '+251-92-234-5678', 'A+',  'Bahir Dar',   'Amhara',      '1999-06-14', 'female', 11.5936, 37.3905, 2, 'bronze',  '2025-06-10'],
    ['tewodros.assefa@lifeline.et', 'Tewodros Assefa',    '+251-92-345-6789', 'O+',  'Addis Ababa', 'Addis Ababa', '1987-10-05', 'male',   9.0413, 38.7488, 15, 'platinum', '2025-12-14'],
    ['meseret.kebede@lifeline.et',  'Meseret Kebede',     '+251-92-456-7890', 'B-',  'Adama',       'Oromia',      '1996-03-20', 'female', 8.5408, 39.2703, 1,  'bronze',   '2025-05-25'],
    ['amanuel.gebre@lifeline.et',   'Amanuel Gebre',      '+251-92-567-8901', 'A+',  'Addis Ababa', 'Addis Ababa', '1983-07-07', 'male',   9.0287, 38.7671, 9,  'gold',     '2025-10-30'],
    ['azeb.muleta@lifeline.et',     'Azeb Muleta',        '+251-92-678-9012', 'O+',  'Hawassa',     'SNNPR',       '1992-11-19', 'female', 7.0491, 38.5012, 3,  'silver',   '2025-09-05'],
    ['fitsume.hailu@lifeline.et',   'Fitsume Hailu',      '+251-92-789-0123', 'B+',  'Mekelle',     'Tigray',      '1990-08-23', 'male',   13.5001, 39.4689, 4, 'silver',  '2025-08-28'],
    ['liya.tadesse@lifeline.et',    'Liya Tadesse',       '+251-93-012-3456', 'A+',  'Gondar',      'Amhara',      '2000-01-11', 'female', 12.6145, 37.4680, 0,  'bronze',   null],
    ['samuel.yilma@lifeline.et',    'Samuel Yilma',       '+251-93-123-4567', 'O+',  'Jimma',       'Oromia',      '1989-04-30', 'male',   7.6702, 36.8411, 6,  'gold',     '2025-11-02'],
];

$donorTierPoints = ['bronze' => 0, 'silver' => 150, 'gold' => 500, 'platinum' => 1200];
$donorUserIds = [];

foreach ($donors as $d) {
    $pdo->prepare("INSERT INTO users (email, password, role, is_active, email_verified_at) VALUES (?, ?, 'donor', 1, NOW())")
        ->execute([$d[0], password_hash('Donor@2025!', PASSWORD_DEFAULT)]);
    $uid = (int)$pdo->lastInsertId();

    $points = $d[10] * 50 + ($donorTierPoints[$d[11]] ?? 0);
    $pdo->prepare("INSERT INTO donor_profiles
        (user_id, full_name, phone, blood_type, city, state, country, date_of_birth, gender,
         latitude, longitude, total_donations, tier, donation_points, is_available, is_verified, last_donation_date)
        VALUES (?, ?, ?, ?, ?, ?, 'Ethiopia', ?, ?, ?, ?, ?, ?, ?, 1, 1, ?)")
        ->execute([$uid, $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], $d[8], $d[9], $d[10], $d[11], $points, $d[12]]);

    $donorUserIds[$d[1]] = $uid;
    echo "Added donor: {$d[1]}\n";
}

// ── 4. Seed blood requests ────────────────────────────────────────────────────
$tikurId  = $hospitalUserIds['Tikur Anbesa Specialized Hospital'];
$stPaulId = $hospitalUserIds["St. Paul's Hospital Millennium Medical College"];
$hawassaId= $hospitalUserIds['Hawassa University Comprehensive Specialized Hospital'];
$jimmaId  = $hospitalUserIds['Jimma University Medical Center'];
$gondarId = $hospitalUserIds['University of Gondar Comprehensive Specialized Hospital'];

$requests = [
    [$tikurId,  'O+',  3, 'critical', 'Trauma surgery patient — road accident victim. Urgent need.', 'open', '2026-06-22'],
    [$tikurId,  'AB+', 1, 'critical', 'Post-operative bleeding — cardiac surgery patient.', 'open', '2026-06-21'],
    [$stPaulId, 'A+',  2, 'urgent',   'Scheduled major surgery. Patient has no relatives available to donate.', 'open', '2026-06-25'],
    [$stPaulId, 'O-',  4, 'critical', 'Rare blood type needed for maternal hemorrhage case.', 'open', '2026-06-21'],
    [$hawassaId,'B+',  2, 'urgent',   'Dialysis patient in need of regular transfusion.', 'open', '2026-07-01'],
    [$jimmaId,  'O+',  5, 'urgent',   'Sickle cell disease patients — monthly transfusion program.', 'open', '2026-07-05'],
    [$gondarId, 'A+',  1, 'normal',   'Planned elective surgery — blood bank top-up.', 'open', '2026-07-10'],
    [$hawassaId,'O+',  2, 'urgent',   'Pediatric patient with severe anemia.', 'open', '2026-06-28'],
];

foreach ($requests as $r) {
    $pdo->prepare("INSERT INTO blood_requests (hospital_id, patient_blood_type, units_needed, urgency, notes, status, required_date)
        VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute($r);
    echo "Added blood request: {$r[3]} {$r[1]} for hospital #{$r[0]}\n";
}

// ── 5. Seed donation history for donors with past donations ───────────────────
$donationsToAdd = [
    ['Dawit Tesfaye',       '2024-03-10', $tikurId,  'verified'],
    ['Dawit Tesfaye',       '2024-06-25', $tikurId,  'verified'],
    ['Dawit Tesfaye',       '2024-10-15', $stPaulId, 'verified'],
    ['Abebe Girma',         '2024-04-20', $tikurId,  'verified'],
    ['Abebe Girma',         '2024-09-08', $stPaulId, 'verified'],
    ['Tewodros Assefa',     '2024-02-14', $tikurId,  'verified'],
    ['Tewodros Assefa',     '2024-05-30', $tikurId,  'verified'],
    ['Tewodros Assefa',     '2024-08-22', $stPaulId, 'verified'],
    ['Tewodros Assefa',     '2025-01-10', $tikurId,  'verified'],
    ['Amanuel Gebre',       '2024-07-03', $tikurId,  'verified'],
    ['Amanuel Gebre',       '2025-02-18', $stPaulId, 'verified'],
    ['Mulugeta Alemayehu',  '2024-04-14', $gondarId, 'verified'],
    ['Mulugeta Alemayehu',  '2024-11-09', $gondarId, 'verified'],
    ['Samuel Yilma',        '2024-05-27', $jimmaId,  'verified'],
    ['Samuel Yilma',        '2024-12-05', $jimmaId,  'verified'],
    ['Getachew Berhane',    '2024-03-30', $tikurId,  'verified'],
    ['Getachew Berhane',    '2024-08-18', $tikurId,  'verified'],
];

$donorInsert = $pdo->prepare("INSERT INTO donation_history (donor_id, hospital_id, donation_date) VALUES (?, ?, ?)");
foreach ($donationsToAdd as $don) {
    $donorId = $donorUserIds[$don[0]] ?? null;
    if ($donorId) {
        $donorInsert->execute([$donorId, $don[2], $don[1]]);
    }
}
echo "Seeded donation history.\n";

// ── 6. Seed testimonials ─────────────────────────────────────────────────────
$testimonials = [
    [$donorUserIds['Abebe Girma'],       'My blood donation saved a child\'s life during surgery. I donate every time I am eligible — it takes 30 minutes and can save up to three lives.', 'approved'],
    [$donorUserIds['Tigist Bekele'],     'After my brother survived a car accident thanks to donated blood, I registered immediately. Every donation matters.', 'approved'],
    [$donorUserIds['Selamawit Haile'],   'LifeLine made it easy to find hospitals near me. I donated in Hawassa and the staff were very professional. Highly recommend.', 'approved'],
    [$donorUserIds['Tewodros Assefa'],   'I have donated 15 times over 6 years. Each time I feel proud knowing I helped someone in need. Join us!', 'approved'],
    [$donorUserIds['Getachew Berhane'],  'As a rare blood type donor (AB+), I feel a special responsibility to donate. LifeLine notifies me instantly when hospitals in Mekelle need my type.', 'approved'],
];

$testimStmt = $pdo->prepare("INSERT INTO testimonials (donor_id, story, is_approved) VALUES (?, ?, 1)");
foreach ($testimonials as $t) {
    $testimStmt->execute([$t[0], $t[1]]);
}
echo "Seeded testimonials.\n";

echo "\nReseed complete! Summary:\n";
echo "  Hospitals: " . count($hospitals) . "\n";
echo "  Donors:    " . count($donors) . "\n";
echo "  Requests:  " . count($requests) . "\n";
echo "  Admin user preserved: admin@bloodsystem.com\n";
echo "\nAll donor accounts use password: Donor@2025!\n";
echo "Hospital accounts use their own passwords (see script).\n";
