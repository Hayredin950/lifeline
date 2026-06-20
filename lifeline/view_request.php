<?php
require_once 'includes/functions.php';

$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$requestId) {
    setFlash('Invalid request.', 'danger');
    redirect(baseUrl() . '/index.php');
}

$stmt = $pdo->prepare("
    SELECT br.*, hp.hospital_name, hp.phone as hospital_phone, hp.city as h_city, hp.state as h_state,
           hp.is_verified
    FROM blood_requests br
    LEFT JOIN hospital_profiles hp ON br.hospital_id = hp.user_id
    WHERE br.id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    setFlash('Blood request not found.', 'danger');
    redirect(baseUrl() . '/index.php');
}

// Find compatible donors
$compatTypes = getCompatibleDonorBloodTypes($request['patient_blood_type']);
$matches = [];
if (!empty($compatTypes)) {
    $in = implode(',', array_fill(0, count($compatTypes), '?'));
    $sql = "SELECT dp.*, u.email FROM donor_profiles dp JOIN users u ON dp.user_id = u.id WHERE u.is_active = true AND dp.is_available = true AND dp.blood_type IN ($in)";
    $params = $compatTypes;
    // Optional location filter if request city/state provided
    if (!empty($request['city'])) {
        $sql .= " AND dp.city LIKE ?";
        $params[] = '%' . $request['city'] . '%';
    }
    $sql .= " ORDER BY dp.city, dp.full_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $matches = $stmt->fetchAll();
}

// Handle Express Interest (for donors)
if (isLoggedIn() && isDonor() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['express_interest'])) {
    validateCsrf();
    $userId = $_SESSION['user_id'];

    // DEF-20: re-check state at POST to close the time-of-check/time-of-use gap.
    // The page may have been rendered minutes ago; the request could have closed
    // or the donor could have entered cool-off / become busy since then.
    $donorProfile = getDonorProfile($pdo, $userId);
    $compatibleTypes = getCompatibleDonorBloodTypes($request['patient_blood_type']);
    $donorStatus = getDonorCurrentStatus($pdo, $userId);

    if ($request['status'] !== 'open') {
        setFlash('This request is no longer open.', 'danger');
        redirect(baseUrl() . "/view_request.php?id={$requestId}");
    } elseif ($donorStatus['status'] !== 'available') {
        setFlash('You cannot express interest right now: ' . $donorStatus['label'] . '.', 'danger');
        redirect(baseUrl() . "/view_request.php?id={$requestId}");
    } elseif (in_array($donorProfile['blood_type'], $compatibleTypes)) {
        // Record interest
        $stmt = $pdo->prepare("
            INSERT INTO donor_matches (request_id, donor_id, status) 
            VALUES (?, ?, 'contacted') 
            ON DUPLICATE KEY UPDATE status = 'contacted'
        ");
        $stmt->execute([$requestId, $userId]);
        
        // Notify hospital
        if ($request['hospital_id']) {
            $notifTitle = "Donor Expressed Interest!";
            $notifMsg = "{$donorProfile['full_name']} ({$donorProfile['blood_type']}) has expressed interest in your blood request #{$requestId}.";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'interest', ?, ?, ?)");
            $stmt->execute([$request['hospital_id'], $notifTitle, $notifMsg, "/hospital/request_matches.php?request_id={$requestId}"]);
        }
        
        setFlash('Thank you! Your interest has been shared with the hospital. They will contact you if needed.', 'success');
        redirect(baseUrl() . "/view_request.php?id={$requestId}");
    } else {
        setFlash('Sorry, your blood type is not compatible with this request.', 'danger');
    }
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h1>Blood Request #<?php echo $requestId; ?></h1>
            <p class="m-0 text-muted">Posted by
                <?php if ($request['hospital_name']): ?>
                    <?php echo htmlspecialchars($request['hospital_name']); ?>
                    <?php if ($request['is_verified']): ?>
                        <span class="badge badge-success fs-75" title="Verified hospital">&#10003; Verified</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-crimson fw-600">Emergency SOS</span>
                <?php endif; ?>
                &middot; <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
            </p>
        </div>
        <div class="flex gap-12 items-center">
            <?php $urgencyPill = $request['urgency'] === 'critical' ? 'pill--danger' : ($request['urgency'] === 'urgent' ? 'pill--warning' : 'pill--info'); ?>
            <span class="badge-lg <?php echo $urgencyPill; ?>">
                <?php echo ucfirst($request['urgency']); ?>
            </span>
            
            <?php if (isLoggedIn() && isDonor() && $request['status'] === 'open'): 
                $donorStatus = getDonorCurrentStatus($pdo, $_SESSION['user_id']);
                $isCompat = in_array(getDonorProfile($pdo, $_SESSION['user_id'])['blood_type'], getCompatibleDonorBloodTypes($request['patient_blood_type']));
                
                if ($isCompat):
            ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <?php if ($donorStatus['status'] === 'available'): ?>
                        <button type="submit" name="express_interest" class="btn">Express Interest</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" disabled title="<?php echo htmlspecialchars($donorStatus['label']); ?>">Currently Unavailable</button>
                    <?php endif; ?>
                </form>
            <?php endif; endif; ?>
        </div>
    </div>

    <div class="grid-autofit-200">
        <div>
            <strong>Patient Blood Type</strong>
            <p class="fs-130 text-crimson my-4"><?php echo htmlspecialchars($request['patient_blood_type']); ?></p>
        </div>
        <div>
            <strong>Units Needed</strong>
            <p class="my-4"><?php echo (int)$request['units_needed']; ?></p>
        </div>
        <div>
            <strong>Required By</strong>
            <p class="my-4"><?php echo $request['required_date'] ? htmlspecialchars($request['required_date']) : 'ASAP'; ?></p>
        </div>
        <div>
            <strong>Location</strong>
            <p class="my-4"><?php echo htmlspecialchars(($request['city'] ? $request['city'] . ', ' : '') . $request['state']); ?></p>
        </div>
    </div>
    <?php if ($request['notes']): ?>
        <div class="mt-12">
            <strong>Additional Notes</strong>
            <p class="my-4"><?php echo nl2br(htmlspecialchars($request['notes'])); ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Compatible Donors (<?php echo count($matches); ?>)</h2>
    <p class="mt-4 text-muted fs-90">
        Donors with blood types compatible for a <strong><?php echo htmlspecialchars($request['patient_blood_type']); ?></strong> patient.
    </p>

    <?php if (count($matches) > 0): ?>
        <div class="table-wrapper mt-16">
        <table>
            <thead>
                <tr>
                    <th>Donor Name</th>
                    <th>Blood Type</th>
                    <th>Location</th>
                    <th>Last Donation</th>
                    <?php if (isLoggedIn()): ?>
                        <th>Phone</th>
                        <th>Email</th>
                    <?php else: ?>
                        <th>Contact</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $m): ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-10">
                            <img src="<?php echo getProfilePic($m); ?>" alt="Avatar" class="avatar-img-sm">
                            <?php echo htmlspecialchars($m['full_name']); ?>
                        </div>
                    </td>
                    <td><strong><?php echo htmlspecialchars($m['blood_type']); ?></strong></td>
                    <td><?php echo htmlspecialchars(($m['city'] ? $m['city'] . ', ' : '') . $m['state']); ?></td>
                    <td><?php echo $m['last_donation_date'] ? htmlspecialchars($m['last_donation_date']) : 'N/A'; ?></td>
                    <?php if (isLoggedIn()): ?>
                        <td><?php echo htmlspecialchars($m['phone']); ?></td>
                        <td><?php echo htmlspecialchars($m['email']); ?></td>
                    <?php else: ?>
                        <td><em>Login to view</em></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if (!isLoggedIn()): ?>
            <p class="mt-16 fs-90 text-muted"><em>Hospitals and registered users can view full donor contact information after logging in.</em></p>
        <?php endif; ?>
    <?php else: ?>
        <p class="mt-12">No compatible donors found for this request at the moment.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
