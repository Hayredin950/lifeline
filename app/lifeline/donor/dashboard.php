<?php
require_once '../includes/functions.php';
requireDonor();

$userId = $_SESSION['user_id'];
$profile = getDonorProfile($pdo, $userId);
if (!$profile) {
    setFlash('Profile not found.', 'danger');
    redirect(baseUrl() . '/index.php');
}

$statusInfo = getDonorCurrentStatus($pdo, $userId);

// Get active engagements (confirmed or contacted)
$stmt = $pdo->prepare("
    SELECT dm.*, br.patient_blood_type, br.urgency, hp.hospital_name, hp.phone as h_phone
    FROM donor_matches dm
    JOIN blood_requests br ON dm.request_id = br.id
    LEFT JOIN hospital_profiles hp ON br.hospital_id = hp.user_id
    WHERE dm.donor_id = ? AND br.status = 'open' AND dm.status IN ('confirmed', 'contacted')
    ORDER BY dm.created_at DESC
");
$stmt->execute([$userId]);
$engagements = $stmt->fetchAll();

// Open requests this donor can help with
$patientTypes = getPatientBloodTypesForDonor($profile['blood_type']);
$requests = [];
if (!empty($patientTypes)) {
    $in = implode(',', array_fill(0, count($patientTypes), '?'));
    $sql = "
        SELECT br.*, hp.hospital_name
        FROM blood_requests br
        JOIN hospital_profiles hp ON br.hospital_id = hp.user_id
        WHERE br.status = 'open' AND br.patient_blood_type IN ($in)
        ORDER BY br.urgency = 'critical' DESC, br.urgency = 'urgent' DESC, br.created_at DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($patientTypes);
    $requests = $stmt->fetchAll();
}

// Get notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="dashboard-layout">
    <div>
        <div class="card">
            <div class="card-header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <img src="<?php echo getProfilePic($profile); ?>" alt="Profile" 
                         style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--crimson);">
                    <div>
                        <h1>Welcome, <?php echo htmlspecialchars($profile['full_name']); ?></h1>
                        <p style="margin:0;color:#6b7280;">Donor Dashboard</p>
                    </div>
                </div>
                <a href="<?php echo baseUrl(); ?>/donor/edit_profile.php" class="btn btn-secondary">Edit Profile</a>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-top: 10px;">
                <div>
                    <strong>Blood Type</strong>
                    <p style="font-size:1.2rem; color:#b91c1c; margin:4px 0;"><strong><?php echo htmlspecialchars($profile['blood_type']); ?></strong></p>
                </div>
                <div>
                    <strong>Location</strong>
                    <p style="margin:4px 0;"><?php echo htmlspecialchars(($profile['city'] ? $profile['city'] . ', ' : '') . $profile['state']); ?></p>
                </div>
                <div>
                    <strong>Availability</strong>
                    <p style="margin:4px 0;">
                        <?php
                        $color = '#6b7280';
                        if ($statusInfo['status'] === 'available') $color = '#15803d';
                        elseif ($statusInfo['status'] === 'busy') $color = '#b45309';
                        elseif ($statusInfo['status'] === 'cool_off') $color = '#b91c1c';
                        ?>
                        <span style="color:<?php echo $color; ?>;font-weight:600;">
                            <?php echo htmlspecialchars($statusInfo['label']); ?>
                        </span>
                    </p>
                    <?php if ($statusInfo['status'] === 'cool_off'): ?>
                        <div style="font-size:0.8rem; color:#6b7280;">Eligible on: <?php echo $statusInfo['available_on']; ?> (<?php echo $statusInfo['days_remaining']; ?> days left)</div>
                    <?php endif; ?>
                </div>
                <div>
                    <strong>Donation Points</strong>
                    <p style="margin:4px 0; font-size:1.2rem; color:#f59e0b;"><strong><?php echo (int)($profile['donation_points'] ?? 0); ?></strong></p>
                    <div style="font-size:0.75rem; color:#9ca3af;">Tier: <?php echo ucfirst($profile['tier'] ?? 'bronze'); ?></div>
                </div>
            </div>
        </div>

        <?php if (count($engagements) > 0): ?>
        <div class="card" style="border-left: 5px solid #b45309;">
        <h2>Active Engagements</h2>
        <p style="color:#6b7280; font-size:0.9rem; margin-bottom: 12px;">Hospitals you are currently connecting with.</p>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="hide-mobile">Phone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($engagements as $e): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($e['hospital_name'] ?: 'Emergency Request'); ?></strong></td>
                        <td><span class="blood-badge" style="padding: 2px 8px; font-size: 0.75rem;"><?php echo htmlspecialchars($e['patient_blood_type']); ?></span></td>
                        <td>
                            <span style="display:inline-block;padding:4px 8px;border-radius:4px;font-size:0.75rem;font-weight:600;background:#fef3c7;color:#92400e;">
                                <?php echo ucfirst($e['status']); ?>
                            </span>
                        </td>
                        <td class="hide-mobile"><?php echo htmlspecialchars($e['h_phone'] ?: 'N/A'); ?></td>
                        <td><a href="<?php echo baseUrl(); ?>/view_request.php?id=<?php echo (int)$e['request_id']; ?>" class="btn btn-small" style="padding: 6px 12px; font-size: 0.8rem;">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Open Requests You Can Help With</h2>
        <?php if (count($requests) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Type</th>
                        <th class="hide-xs">Urgency</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($req['hospital_name']); ?></strong></td>
                        <td><span class="blood-badge" style="padding: 2px 8px; font-size: 0.75rem;"><?php echo htmlspecialchars($req['patient_blood_type']); ?></span></td>
                        <td class="hide-xs">
                            <span style="
                                display:inline-block;padding:4px 8px;border-radius:4px;font-size:0.75rem;font-weight:600;
                                <?php echo $req['urgency'] === 'critical' ? 'background:#fee2e2;color:#991b1b;' : ($req['urgency'] === 'urgent' ? 'background:#fef3c7;color:#92400e;' : 'background:#dbeafe;color:#1e40af;'); ?>
                            ">
                                <?php echo ucfirst($req['urgency']); ?>
                            </span>
                        </td>
                        <td><a href="<?php echo baseUrl(); ?>/view_request.php?id=<?php echo (int)$req['id']; ?>" class="btn btn-small" style="padding: 6px 12px; font-size: 0.8rem;">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        <?php else: ?>
                <p style="margin-top: 10px;">There are currently no open requests matching your blood type.</p>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Notifications</h2>
            <?php if (count($notifications) > 0): ?>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 10px;">
                    <?php foreach ($notifications as $n): ?>
                        <div style="padding: 10px; border-bottom: 1px solid #f3f4f6; <?php echo $n['is_read'] ? 'opacity: 0.7;' : 'border-left: 3px solid #b91c1c;'; ?>">
                            <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars($n['title']); ?></div>
                            <div style="font-size: 0.8rem; color: #6b7280;"><?php echo htmlspecialchars($n['message']); ?></div>
                            <div style="font-size: 0.7rem; color: #9ca3af; margin-top: 4px;"><?php echo date('M j, g:i A', strtotime($n['created_at'])); ?></div>
                            <?php if ($n['link']): ?>
                                <a href="<?php echo baseUrl() . $n['link']; ?>" style="font-size: 0.75rem; color: #b91c1c; text-decoration: none; display: block; margin-top: 4px;">View Details &rarr;</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="margin-top: 10px; color: #6b7280; font-size: 0.9rem;">No new notifications.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 10px;">
                <a href="<?php echo baseUrl(); ?>/find_donors.php" class="btn btn-small btn-secondary" style="width: 100%; text-align: center;">Find Other Donors</a>
                <a href="<?php echo baseUrl(); ?>/eligibility.php" class="btn btn-small btn-secondary" style="width: 100%; text-align: center;">Check Eligibility</a>
                <a href="<?php echo baseUrl(); ?>/leaderboard.php" class="btn btn-small btn-secondary" style="width: 100%; text-align: center;">View Leaderboard</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
