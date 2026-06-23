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

// Get user's testimonials
$stmt = $pdo->prepare("SELECT * FROM testimonials WHERE donor_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$userTestimonials = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="dashboard-layout">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="flex items-center gap-15">
                    <img src="<?php echo getProfilePic($profile); ?>" alt="Profile" class="avatar-img-md">
                    <div>
                        <h1>Welcome, <?php echo htmlspecialchars($profile['full_name']); ?></h1>
                        <p class="m-0 text-muted">Donor Dashboard</p>
                    </div>
                </div>
                <a href="<?php echo baseUrl(); ?>/donor/edit_profile.php" class="btn btn-secondary">Edit Profile</a>
            </div>

            <div class="grid-autofit-180 mt-10">
                <div>
                    <strong>Blood Type</strong>
                    <p class="fs-120 text-crimson my-4"><strong><?php echo htmlspecialchars($profile['blood_type']); ?></strong></p>
                </div>
                <div>
                    <strong>Location</strong>
                    <p class="my-4"><?php echo htmlspecialchars(($profile['city'] ? $profile['city'] . ', ' : '') . $profile['state']); ?></p>
                </div>
                <div>
                    <strong>Availability</strong>
                    <p class="my-4">
                        <?php
                        $statusClass = 'text-muted';
                        if ($statusInfo['status'] === 'available') $statusClass = 'text-success-dark';
                        elseif ($statusInfo['status'] === 'busy') $statusClass = 'text-amber';
                        elseif ($statusInfo['status'] === 'cool_off') $statusClass = 'text-crimson';
                        ?>
                        <span class="fw-600 <?php echo $statusClass; ?>">
                            <?php echo htmlspecialchars($statusInfo['label']); ?>
                        </span>
                    </p>
                    <?php if ($statusInfo['status'] === 'cool_off'): ?>
                        <div class="fs-80 text-muted">Eligible on: <?php echo $statusInfo['available_on']; ?> (<?php echo $statusInfo['days_remaining']; ?> days left)</div>
                    <?php endif; ?>
                </div>
                <div>
                    <strong>Donation Points</strong>
                    <p class="my-4 fs-120 text-warning"><strong><?php echo (int)($profile['donation_points'] ?? 0); ?></strong></p>
                    <div class="fs-75 text-muted">Tier: <?php echo ucfirst($profile['tier'] ?? 'bronze'); ?></div>
                </div>
            </div>
        </div>

        <?php if (count($engagements) > 0): ?>
        <div class="card border-l-amber">
        <h2>Active Engagements</h2>
        <p class="text-muted fs-90 mb-12">Hospitals you are currently connecting with.</p>
        <div class="table-wrapper">
            <table aria-label="Your active donation engagements">
                <thead>
                    <tr>
                        <th scope="col">Hospital</th>
                        <th scope="col">Type</th>
                        <th scope="col">Status</th>
                        <th class="hide-mobile">Phone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($engagements as $e): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($e['hospital_name'] ?: 'Emergency Request'); ?></strong></td>
                        <td><span class="blood-badge py-2 px-8 fs-75"><?php echo htmlspecialchars($e['patient_blood_type']); ?></span></td>
                        <td>
                            <span class="pill pill--warning">
                                <?php echo ucfirst($e['status']); ?>
                            </span>
                        </td>
                        <td class="hide-mobile"><?php echo htmlspecialchars($e['h_phone'] ?: 'N/A'); ?></td>
                        <td><a href="<?php echo baseUrl(); ?>/view_request.php?id=<?php echo (int)$e['request_id']; ?>" class="btn btn-small py-6 px-12 fs-80">View</a></td>
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
                        <td><span class="blood-badge py-2 px-8 fs-75"><?php echo htmlspecialchars($req['patient_blood_type']); ?></span></td>
                        <td class="hide-xs">
                            <?php $urgencyPill = $req['urgency'] === 'critical' ? 'pill--danger' : ($req['urgency'] === 'urgent' ? 'pill--warning' : 'pill--info'); ?>
                            <span class="pill <?php echo $urgencyPill; ?>">
                                <?php echo ucfirst($req['urgency']); ?>
                            </span>
                        </td>
                        <td><a href="<?php echo baseUrl(); ?>/view_request.php?id=<?php echo (int)$req['id']; ?>" class="btn btn-small py-6 px-12 fs-80">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        <?php else: ?>
                <p class="mt-10">There are currently no open requests matching your blood type.</p>
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

        <?php if (count($userTestimonials) > 0): ?>
        <div class="card">
            <h3>Your Stories</h3>
            <div class="flex flex-col gap-12 mt-10">
                <?php foreach ($userTestimonials as $t): ?>
                    <div class="border border-glass-border rounded-lg p-12">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex gap-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span style="color: <?php echo $i > (int)$t['rating'] ? 'rgba(255,255,255,.15)' : '#f59e0b'; ?>">&#9733;</span>
                                <?php endfor; ?>
                            </div>
                            <span class="badge <?php echo $t['is_approved'] ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo $t['is_approved'] ? 'Published' : 'Pending Review'; ?>
                            </span>
                        </div>
                        <p class="text-muted mb-8"><?php echo htmlspecialchars($t['story']); ?></p>
                        <div class="flex gap-8">
                            <a href="<?php echo baseUrl(); ?>/donor/submit_testimonial.php?edit=<?php echo (int)$t['id']; ?>" class="btn btn-small btn-secondary">Edit</a>
                            <form method="POST" action="<?php echo baseUrl(); ?>/donor/submit_testimonial.php" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="testimonial_id" value="<?php echo (int)$t['id']; ?>">
                                <button type="submit" name="delete" value="1" class="btn btn-small bg-danger-dark" onclick="return confirm('Are you sure you want to delete this story?');">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3>Quick Actions</h3>
            <div class="flex flex-col gap-8 mt-10">
                <a href="<?php echo baseUrl(); ?>/eligibility.php" class="btn btn-small btn-secondary w-full text-center">Check Eligibility</a>
                <a href="<?php echo baseUrl(); ?>/blood_banks.php" class="btn btn-small btn-secondary w-full text-center">Hospitals</a>
                <a href="<?php echo baseUrl(); ?>/messages.php" class="btn btn-small btn-secondary w-full text-center">Messages</a>
                <a href="<?php echo baseUrl(); ?>/leaderboard.php" class="btn btn-small btn-secondary w-full text-center">View Leaderboard</a>
                <a href="<?php echo baseUrl(); ?>/donor/edit_profile.php" class="btn btn-small btn-secondary w-full text-center">Edit Profile</a>
                <a href="<?php echo baseUrl(); ?>/donor/submit_testimonial.php" class="btn btn-small btn-secondary w-full text-center">Share Your Story</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
