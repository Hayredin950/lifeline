<?php
/**
 * Admin: view enrolments for a specific clinical trial + run eligibility matching.
 */
require_once '../includes/functions.php';
requireAdmin();

$trialId = (int)($_GET['trial_id'] ?? 0);
$stmt    = $pdo->prepare("SELECT * FROM clinical_trials WHERE id = ?");
$stmt->execute([$trialId]);
$trial   = $stmt->fetch();

if (!$trial) {
    setFlash('Trial not found.', 'danger');
    redirect(baseUrl() . '/admin/clinical_trials.php');
}

$enrolments = $pdo->prepare("
    SELECT te.*, dp.full_name, dp.blood_type, dp.total_donations, u.email
    FROM trial_enrolments te
    JOIN donor_profiles dp ON dp.user_id = te.donor_id
    JOIN users u ON u.id = te.donor_id
    WHERE te.trial_id = ?
    ORDER BY te.consent_given_at DESC
");
$enrolments->execute([$trialId]);
$enrolmentRows = $enrolments->fetchAll();

// Eligibility matching: find eligible donors not yet enrolled.
$sql    = "SELECT dp.*, u.email FROM donor_profiles dp JOIN users u ON u.id = dp.user_id
           WHERE u.is_active = 1 AND dp.is_available = 1
             AND dp.user_id NOT IN (SELECT donor_id FROM trial_enrolments WHERE trial_id = ?)";
$params = [$trialId];

if ($trial['blood_types']) {
    $bts = array_filter(array_map('trim', explode(',', $trial['blood_types'])));
    if ($bts) {
        $in = implode(',', array_fill(0, count($bts), '?'));
        $sql .= " AND dp.blood_type IN ($in)";
        $params = array_merge($params, $bts);
    }
}
if ((int)$trial['min_donations'] > 0) {
    $sql .= " AND dp.total_donations >= ?";
    $params[] = (int)$trial['min_donations'];
}
if ($trial['component_codes']) {
    $comps = array_filter(array_map('trim', explode(',', $trial['component_codes'])));
    if ($comps) {
        $in = implode(',', array_fill(0, count($comps), '?'));
        $sql .= " AND EXISTS (SELECT 1 FROM donor_component_registrations dcr
                              WHERE dcr.donor_id = dp.user_id AND dcr.component_code IN ($in) AND dcr.is_active = 1)";
        $params = array_merge($params, $comps);
    }
}
$sql .= " ORDER BY dp.full_name LIMIT 100";

$eligibleStmt = $pdo->prepare($sql);
$eligibleStmt->execute($params);
$eligible = $eligibleStmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h1><?php echo htmlspecialchars($trial['title']); ?></h1>
            <p class="m-0 text-muted fs-85">
                Status: <?php echo ucfirst($trial['status']); ?>
                <?php if ($trial['recruiting_until']): ?> · Until <?php echo htmlspecialchars($trial['recruiting_until']); ?><?php endif; ?>
                <?php if ($trial['blood_types']): ?> · Blood types: <?php echo htmlspecialchars($trial['blood_types']); ?><?php endif; ?>
                <?php if ($trial['min_donations']): ?> · Min donations: <?php echo (int)$trial['min_donations']; ?><?php endif; ?>
            </p>
        </div>
        <a href="<?php echo baseUrl(); ?>/admin/clinical_trials.php" class="btn btn-secondary">Back</a>
    </div>
    <?php if ($trial['eligibility_notes']): ?>
    <p class="fs-85 mt-10"><?php echo nl2br(htmlspecialchars($trial['eligibility_notes'])); ?></p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Enrolments (<?php echo count($enrolmentRows); ?>)</h2>
    <?php if ($enrolmentRows): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Donor</th>
                    <th>Email</th>
                    <th>Blood Type</th>
                    <th>Donations</th>
                    <th>Consented</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($enrolmentRows as $e): ?>
            <tr>
                <td><?php echo htmlspecialchars($e['full_name']); ?></td>
                <td class="fs-85"><?php echo htmlspecialchars($e['email']); ?></td>
                <td><strong><?php echo htmlspecialchars($e['blood_type']); ?></strong></td>
                <td><?php echo (int)$e['total_donations']; ?></td>
                <td class="fs-85 text-muted"><?php echo htmlspecialchars(substr($e['consent_given_at'], 0, 10)); ?></td>
                <td><span class="pill <?php echo $e['status'] === 'enrolled' ? 'pill--success' : ($e['status'] === 'withdrawn' ? 'pill--danger' : 'pill--neutral'); ?>">
                    <?php echo ucfirst($e['status']); ?>
                </span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted">No enrolments yet.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Eligible Donors Not Yet Enrolled (<?php echo count($eligible); ?>)</h2>
    <p class="text-muted fs-85 mb-12">These donors match the trial criteria and have not yet opted in. Donors self-enrol via their dashboard.</p>
    <?php if ($eligible): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Name</th><th>Email</th><th>Blood Type</th><th>Donations</th></tr>
            </thead>
            <tbody>
            <?php foreach ($eligible as $d): ?>
            <tr>
                <td><?php echo htmlspecialchars($d['full_name']); ?></td>
                <td class="fs-85"><?php echo htmlspecialchars($d['email']); ?></td>
                <td><strong><?php echo htmlspecialchars($d['blood_type']); ?></strong></td>
                <td><?php echo (int)$d['total_donations']; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted">No additional eligible donors found.</p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
