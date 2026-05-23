<?php
require_once '../includes/functions.php';
requireHospital();

$userId = $_SESSION['user_id'];
$profile = getHospitalProfile($pdo, $userId);
$requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

$stmt = $pdo->prepare("
    SELECT * FROM blood_requests
    WHERE id = ? AND hospital_id = ?
");
$stmt->execute([$requestId, $userId]);
$request = $stmt->fetch();

if (!$request) {
    setFlash('Request not found or access denied.', 'danger');
    redirect(baseUrl() . '/hospital/dashboard.php');
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    // Update match status
    if (isset($_POST['match_id']) && isset($_POST['match_status'])) {
        $matchId = (int)$_POST['match_id'];
        $newStatus = $_POST['match_status'];
        
        // Get existing match info
        $stmt = $pdo->prepare("SELECT * FROM donor_matches WHERE id = ? AND request_id = ?");
        $stmt->execute([$matchId, $requestId]);
        $match = $stmt->fetch();
        
        if ($match) {
            $pdo->beginTransaction();
            try {
                // Update match status
                $stmt = $pdo->prepare("UPDATE donor_matches SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $matchId]);
                
                // If status changed to donated, record in history
                if ($newStatus === 'donated' && $match['status'] !== 'donated') {
                    // Record in donation history
                    $stmt = $pdo->prepare("
                        INSERT INTO donation_history (donor_id, request_id, hospital_id, donation_date, blood_type, units)
                        VALUES (?, ?, ?, CURDATE(), ?, ?)
                    ");
                    $stmt->execute([
                        $match['donor_id'],
                        $requestId,
                        $userId,
                        $request['patient_blood_type'],
                        (int)($request['units_needed'] ?? 1)
                    ]);
                    
                    // Update donor profile
                    $stmt = $pdo->prepare("
                        UPDATE donor_profiles 
                        SET last_donation_date = CURDATE(), 
                            total_donations = total_donations + 1,
                            donation_points = donation_points + 100
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$match['donor_id']]);
                    
                    // Update tier based on total donations
                    $stmt = $pdo->prepare("SELECT total_donations FROM donor_profiles WHERE user_id = ?");
                    $stmt->execute([$match['donor_id']]);
                    $total = $stmt->fetchColumn();
                    
                    $tier = 'bronze';
                    if ($total >= 20) $tier = 'platinum';
                    elseif ($total >= 10) $tier = 'gold';
                    elseif ($total >= 5) $tier = 'silver';
                    
                    $stmt = $pdo->prepare("UPDATE donor_profiles SET tier = ? WHERE user_id = ?");
                    $stmt->execute([$tier, $match['donor_id']]);

                    // Notify donor
                     $notifMessage = "Your recent donation at " . $profile['hospital_name'] . " has been recorded. Thank you for saving a life!";
                     $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'donation', 'Donation Recorded!', ?)");
                     $stmt->execute([$match['donor_id'], $notifMessage]);
                }
                
                $pdo->commit();
                setFlash('Match status updated.', 'success');
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('Error updating match status: ' . $e->getMessage(), 'danger');
            }
        }
        redirect(baseUrl() . '/hospital/request_matches.php?request_id=' . $requestId);
    }

    // Update request status
    if (isset($_POST['request_status'])) {
        $stmt = $pdo->prepare("UPDATE blood_requests SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['request_status'], $requestId]);
        setFlash('Request status updated.', 'success');
        redirect(baseUrl() . '/hospital/request_matches.php?request_id=' . $requestId);
    }
}

// Compatible donors
$compatTypes = getCompatibleDonorBloodTypes($request['patient_blood_type']);
$matches = [];
if (!empty($compatTypes)) {
    $in = implode(',', array_fill(0, count($compatTypes), '?'));
    $sql = "SELECT dp.*, u.email, dm.id as match_id, dm.status as match_status
            FROM donor_profiles dp
            JOIN users u ON dp.user_id = u.id
            LEFT JOIN donor_matches dm ON dm.donor_id = dp.user_id AND dm.request_id = ?
            WHERE u.is_active = true AND dp.is_available = true AND dp.blood_type IN ($in)";
    $params = [$requestId];
    $params = array_merge($params, $compatTypes);
    if (!empty($request['city'])) {
        $sql .= " AND dp.city LIKE ?";
        $params[] = '%' . $request['city'] . '%';
    }
    $sql .= " ORDER BY dp.city, dp.full_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $matches = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h1>Manage Matches for Request #<?php echo $requestId; ?></h1>
            <p style="margin:0;color:#6b7280;">Patient needs <strong><?php echo htmlspecialchars($request['patient_blood_type']); ?></strong> &middot; Urgency: <?php echo ucfirst($request['urgency']); ?></p>
        </div>
        <form method="POST" action="" style="display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <select name="request_status" class="form-control" style="padding:8px;border-radius:5px;border:1px solid #d1d5db;">
                <option value="open" <?php echo $request['status']==='open'?'selected':''; ?>>Open</option>
                <option value="fulfilled" <?php echo $request['status']==='fulfilled'?'selected':''; ?>>Fulfilled</option>
                <option value="cancelled" <?php echo $request['status']==='cancelled'?'selected':''; ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn btn-small">Update Status</button>
        </form>
    </div>
    <p><strong>Required By:</strong> <?php echo $request['required_date'] ? htmlspecialchars($request['required_date']) : 'ASAP'; ?> &middot; <strong>Units:</strong> <?php echo (int)$request['units_needed']; ?></p>
</div>

<div class="card">
    <h2>Compatible Donors (<?php echo count($matches); ?>)</h2>
    <?php if (count($matches) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Donor</th>
                    <th>Blood Type</th>
                    <th>Location</th>
                    <th>Availability</th>
                    <th>Phone</th>
                    <th>Action</th>
                    <th>Match Status</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $m): 
                    $donorStatus = getDonorCurrentStatus($pdo, $m['user_id']);
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                    <td><strong><?php echo htmlspecialchars($m['blood_type']); ?></strong></td>
                    <td><?php echo htmlspecialchars(($m['city'] ? $m['city'] . ', ' : '') . $m['state']); ?></td>
                    <td>
                        <?php
                        $color = '#6b7280';
                        if ($donorStatus['status'] === 'available') $color = '#15803d';
                        elseif ($donorStatus['status'] === 'busy') $color = '#b45309';
                        elseif ($donorStatus['status'] === 'cool_off') $color = '#b91c1c';
                        ?>
                        <span style="color:<?php echo $color; ?>;font-weight:600; font-size:0.85rem;">
                            <?php echo htmlspecialchars($donorStatus['label']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($m['phone']); ?></td>
                    <td>
                        <a href="<?php echo baseUrl(); ?>/messages.php?conversation=<?php echo $m['user_id']; ?>" class="btn btn-small btn-secondary">Message</a>
                    </td>
                    <td>
                        <?php $status = $m['match_status'] ?: 'pending'; ?>
                        <span style="
                            display:inline-block;padding:4px 8px;border-radius:4px;font-size:0.8rem;font-weight:600;
                            <?php echo $status === 'donated' ? 'background:#dcfce7;color:#166534;' : ($status === 'confirmed' ? 'background:#dcfce7;color:#166534;' : ($status === 'declined' ? 'background:#fee2e2;color:#991b1b;' : ($status === 'contacted' ? 'background:#dbeafe;color:#1e40af;' : 'background:#f3f4f6;color:#374151;'))); ?>
                        ">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="" style="display:flex;gap:6px;">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="match_id" value="<?php echo (int)($m['match_id'] ?? 0); ?>">
                            <select name="match_status" style="padding:6px;border-radius:4px;border:1px solid #d1d5db;">
                                <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
                                <option value="contacted" <?php echo $status==='contacted'?'selected':''; ?>>Contacted</option>
                                <option value="confirmed" <?php echo $status==='confirmed'?'selected':''; ?>>Confirmed</option>
                                <option value="donated" <?php echo $status==='donated'?'selected':''; ?>>Donated</option>
                                <option value="declined" <?php echo $status==='declined'?'selected':''; ?>>Declined</option>
                            </select>
                            <button type="submit" class="btn btn-small">Save</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No compatible donors found for this request at the moment.</p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
