<?php
require_once '../includes/functions.php';
requireHospital();

$userId = $_SESSION['user_id'];
$profile = getHospitalProfile($pdo, $userId);

$stmt = $pdo->prepare("
    SELECT * FROM blood_requests
    WHERE hospital_id = ?
    ORDER BY status = 'open' DESC, urgency = 'critical' DESC, created_at DESC
");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll();

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
                <div>
                    <h1><?php echo htmlspecialchars($profile['hospital_name']); ?></h1>
                    <p style="margin:0;color:#6b7280;">Hospital Dashboard</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="<?php echo baseUrl(); ?>/hospital/edit_profile.php" class="btn btn-secondary">Edit Profile</a>
                    <a href="<?php echo baseUrl(); ?>/hospital/create_request.php" class="btn">+ New Request</a>
                </div>
            </div>
            <div style="margin-top: 10px;">
                <p><strong>License:</strong> <?php echo htmlspecialchars($profile['license_number']); ?> &middot; <strong>Phone:</strong> <?php echo htmlspecialchars($profile['phone']); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars(($profile['address'] ? $profile['address'] . ', ' : '') . $profile['city'] . ', ' . $profile['state']); ?></p>
            </div>
        </div>

        <div class="card">
            <h2>Your Blood Requests</h2>
            <?php if (count($requests) > 0): ?>
                <div class="table-wrapper">
                    <table>
                    <thead>
                        <tr>
                            <th>Patient Type</th>
                            <th>Units</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($req['patient_blood_type']); ?></td>
                            <td><?php echo (int)$req['units_needed']; ?></td>
                            <td>
                                <span style="
                                    display:inline-block;padding:4px 8px;border-radius:4px;font-size:0.8rem;font-weight:600;
                                    <?php echo $req['urgency'] === 'critical' ? 'background:#fee2e2;color:#991b1b;' : ($req['urgency'] === 'urgent' ? 'background:#fef3c7;color:#92400e;' : 'background:#dbeafe;color:#1e40af;'); ?>
                                ">
                                    <?php echo ucfirst($req['urgency']); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight:600; <?php echo $req['status']==='open'?'color:#b91c1c;':($req['status']==='fulfilled'?'color:#15803d;':'color:#6b7280;'); ?>">
                                    <?php echo ucfirst($req['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo baseUrl(); ?>/hospital/request_matches.php?request_id=<?php echo (int)$req['id']; ?>" class="btn btn-small btn-secondary">Matches</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p style="margin-top: 10px;">You have not created any blood requests yet. <a href="<?php echo baseUrl(); ?>/hospital/create_request.php">Create one now</a>.</p>
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
                <a href="<?php echo baseUrl(); ?>/find_donors.php" class="btn btn-small btn-secondary" style="width: 100%; text-align: center;">Find Donors</a>
                <a href="<?php echo baseUrl(); ?>/blood_banks.php" class="btn btn-small btn-secondary" style="width: 100%; text-align: center;">Nearby Blood Banks</a>
                <a href="<?php echo baseUrl(); ?>/admin/activity.php" class="btn btn-small btn-secondary" style="width: 100%; text-align: center;">View Activity</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
