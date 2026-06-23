<?php
require_once '../includes/functions.php';
requireHospital();

$userId  = $_SESSION['user_id'];
$profile = getHospitalProfile($pdo, $userId);

$stmt = $pdo->prepare("
    SELECT *, (notes LIKE 'EMERGENCY SOS REQUEST%') AS is_sos
    FROM blood_requests
    WHERE hospital_id = ?
    ORDER BY (notes LIKE 'EMERGENCY SOS REQUEST%') DESC,
             status = 'open' DESC,
             urgency = 'critical' DESC,
             created_at DESC
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
                    <p class="m-0 text-muted">Hospital Dashboard</p>
                </div>
                <div class="flex gap-10">
                    <a href="<?php echo baseUrl(); ?>/hospital/edit_profile.php" class="btn btn-secondary">Edit Profile</a>
                    <a href="<?php echo baseUrl(); ?>/hospital/create_request.php" class="btn">+ New Request</a>
                </div>
            </div>
            <div class="mt-10">
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
                            <th>Type</th>
                            <th>Units</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr <?php echo $req['is_sos'] ? 'style="background:rgba(220,38,38,.06)"' : ''; ?>>
                            <td>
                                <?php if ($req['is_sos']): ?>
                                    <span class="badge badge-danger" style="font-size:.68rem;padding:2px 6px;margin-right:4px">&#9888; SOS</span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($req['patient_blood_type']); ?>
                            </td>
                            <td><?php echo (int)$req['units_needed']; ?></td>
                            <td>
                                <?php $urgencyPill = $req['urgency'] === 'critical' ? 'pill--danger' : ($req['urgency'] === 'urgent' ? 'pill--warning' : 'pill--info'); ?>
                                <span class="pill <?php echo $urgencyPill; ?>">
                                    <?php echo ucfirst($req['urgency']); ?>
                                </span>
                            </td>
                            <td>
                                <?php $statusClass = $req['status']==='open' ? 'text-crimson' : ($req['status']==='fulfilled' ? 'text-success-dark' : 'text-muted'); ?>
                                <span class="fw-600 <?php echo $statusClass; ?>">
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
                <p class="mt-10">You have not created any blood requests yet. <a href="<?php echo baseUrl(); ?>/hospital/create_request.php">Create one now</a>.</p>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Notifications</h2>
            <?php if (count($notifications) > 0): ?>
                <div class="flex flex-col gap-12 mt-10">
                    <?php foreach ($notifications as $n): ?>
                        <div class="notif-row <?php echo $n['is_read'] ? 'is-read' : 'is-unread'; ?>">
                            <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                            <div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                            <div class="notif-time"><?php echo date('M j, g:i A', strtotime($n['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="mt-10 text-muted fs-90">No new notifications.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <div class="flex flex-col gap-8 mt-10">
                <a href="<?php echo baseUrl(); ?>/find_donors.php" class="btn btn-small btn-secondary w-full text-center">Find Donors</a>
                <a href="<?php echo baseUrl(); ?>/messages.php" class="btn btn-small btn-secondary w-full text-center">Messages</a>
                <a href="<?php echo baseUrl(); ?>/hospital/submit_verification.php" class="btn btn-small btn-secondary w-full text-center">
                    Hospital Verification
                </a>
                <a href="<?php echo baseUrl(); ?>/hospital/inventory.php" class="btn btn-small btn-secondary w-full text-center">Blood Inventory</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
