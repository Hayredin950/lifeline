<?php
/**
 * Donor: browse open clinical trials and manage consent-based enrolment.
 */
require_once '../includes/functions.php';
requireDonor();

$donorId = (int)$_SESSION['user_id'];
$profile = getDonorProfile($pdo, $donorId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action  = $_POST['action']   ?? '';
    $trialId = (int)($_POST['trial_id'] ?? 0);

    // Validate trial exists and is open.
    $stmt = $pdo->prepare("SELECT * FROM clinical_trials WHERE id = ? AND status = 'open'");
    $stmt->execute([$trialId]);
    $trial = $stmt->fetch();
    if (!$trial) {
        setFlash('Trial not found or no longer accepting enrolments.', 'danger');
        redirect(baseUrl() . '/donor/clinical_trials.php');
    }

    if ($action === 'enrol') {
        // Check not already enrolled.
        $chk = $pdo->prepare("SELECT id FROM trial_enrolments WHERE trial_id = ? AND donor_id = ? AND status = 'enrolled'");
        $chk->execute([$trialId, $donorId]);
        if ($chk->fetch()) {
            setFlash('You are already enrolled in this trial.', 'info');
            redirect(baseUrl() . '/donor/clinical_trials.php');
        }

        $pdo->prepare("
            INSERT INTO trial_enrolments (trial_id, donor_id, consent_given_at, consent_version, status)
            VALUES (?, ?, NOW(), '1.0', 'enrolled')
            ON DUPLICATE KEY UPDATE status = 'enrolled', consent_given_at = NOW()
        ")->execute([$trialId, $donorId]);

        auditLog($pdo, 'trial_enrol', 'trial_enrolments', $donorId, null, ['trial_id' => $trialId]);
        setFlash('You have successfully enrolled in "' . htmlspecialchars($trial['title']) . '". Thank you!', 'success');
    }

    if ($action === 'withdraw') {
        $pdo->prepare("UPDATE trial_enrolments SET status = 'withdrawn', withdrawn_at = NOW() WHERE trial_id = ? AND donor_id = ? AND status = 'enrolled'")
            ->execute([$trialId, $donorId]);
        auditLog($pdo, 'trial_withdraw', 'trial_enrolments', $donorId, null, ['trial_id' => $trialId]);
        setFlash('You have withdrawn from "' . htmlspecialchars($trial['title']) . '".', 'info');
    }

    redirect(baseUrl() . '/donor/clinical_trials.php');
}

// Load open trials with this donor's enrolment status.
$openTrials = $pdo->prepare("
    SELECT ct.*,
           te.id        AS enrolment_id,
           te.status    AS enrolment_status,
           te.consent_given_at,
           (SELECT COUNT(*) FROM trial_enrolments te2 WHERE te2.trial_id = ct.id AND te2.status = 'enrolled') AS enrolled_count
    FROM clinical_trials ct
    LEFT JOIN trial_enrolments te ON te.trial_id = ct.id AND te.donor_id = ?
    WHERE ct.status = 'open'
      AND (ct.recruiting_until IS NULL OR ct.recruiting_until >= CURDATE())
    ORDER BY ct.created_at DESC
");
$openTrials->execute([$donorId]);
$trials = $openTrials->fetchAll();

// Filter to show which ones the donor is eligible for.
function donorEligibleForTrial(array $profile, array $trial, PDO $pdo): bool {
    if ($trial['blood_types']) {
        $bts = array_filter(array_map('trim', explode(',', $trial['blood_types'])));
        if ($bts && !in_array($profile['blood_type'], $bts, true)) return false;
    }
    if ((int)$trial['min_donations'] > 0 && (int)($profile['total_donations'] ?? 0) < (int)$trial['min_donations']) {
        return false;
    }
    if ($trial['component_codes']) {
        $comps = array_filter(array_map('trim', explode(',', $trial['component_codes'])));
        if ($comps) {
            $in   = implode(',', array_fill(0, count($comps), '?'));
            $chk  = $pdo->prepare("SELECT COUNT(*) FROM donor_component_registrations WHERE donor_id = ? AND component_code IN ($in) AND is_active = 1");
            $args = array_merge([(int)$profile['user_id']], $comps);
            $chk->execute($args);
            if ((int)$chk->fetchColumn() === 0) return false;
        }
    }
    return true;
}

include '../includes/header.php';
?>

<div class="card" style="max-width:720px;margin:2rem auto">
    <div class="card-header">
        <h1>Clinical Trials &amp; Rare-Blood Studies</h1>
        <a href="<?php echo baseUrl(); ?>/donor/dashboard.php" class="btn btn-secondary">Back</a>
    </div>
    <p class="text-muted fs-90 mb-20">
        Participate in consented clinical research or rare-blood recruitment.
        Your data is used only for the specific study you join, with your explicit consent.
        You may withdraw at any time.
    </p>

    <?php if (!$trials): ?>
    <p class="text-muted">No open trials at the moment. Check back later.</p>
    <?php else: ?>
    <?php foreach ($trials as $t):
        $eligible    = donorEligibleForTrial($profile, $t, $pdo);
        $enrolled    = ($t['enrolment_status'] === 'enrolled');
        $withdrawn   = ($t['enrolment_status'] === 'withdrawn');
    ?>
    <div class="card mb-16 <?php echo $enrolled ? 'border-l-success' : ($eligible ? '' : 'opacity-70'); ?>">
        <div class="flex gap-12 items-start">
            <div style="flex:1">
                <h3 class="mb-4"><?php echo htmlspecialchars($t['title']); ?></h3>
                <?php if ($t['description']): ?>
                <p class="fs-85 text-muted mb-6"><?php echo nl2br(htmlspecialchars($t['description'])); ?></p>
                <?php endif; ?>
                <?php if ($t['eligibility_notes']): ?>
                <p class="fs-85 mb-6"><strong>Eligibility:</strong> <?php echo nl2br(htmlspecialchars($t['eligibility_notes'])); ?></p>
                <?php endif; ?>
                <div class="fs-80 text-muted flex flex-wrap gap-10">
                    <?php if ($t['blood_types']): ?><span>Blood: <strong><?php echo htmlspecialchars($t['blood_types']); ?></strong></span><?php endif; ?>
                    <?php if ($t['min_donations']): ?><span>Min donations: <strong><?php echo (int)$t['min_donations']; ?></strong></span><?php endif; ?>
                    <?php if ($t['recruiting_until']): ?><span>Until <?php echo htmlspecialchars($t['recruiting_until']); ?></span><?php endif; ?>
                    <span><?php echo (int)$t['enrolled_count']; ?> enrolled<?php echo $t['target_enrolment'] ? '/' . (int)$t['target_enrolment'] : ''; ?></span>
                </div>
                <?php if (!$eligible && !$enrolled): ?>
                <p class="fs-80 text-muted mt-6"><em>You do not currently meet the eligibility criteria for this trial.</em></p>
                <?php endif; ?>
            </div>
            <div style="flex-shrink:0;text-align:right">
                <?php if ($enrolled): ?>
                <span class="pill pill--success mb-8" style="display:block">Enrolled</span>
                <p class="fs-75 text-muted mb-8">Since <?php echo htmlspecialchars(substr($t['consent_given_at'], 0, 10)); ?></p>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token"  value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action"      value="withdraw">
                    <input type="hidden" name="trial_id"   value="<?php echo (int)$t['id']; ?>">
                    <button type="submit" class="btn btn-small btn-secondary"
                            onclick="return confirm('Withdraw from this trial? Your participation data will be retained but marked withdrawn.')">Withdraw</button>
                </form>
                <?php elseif ($eligible): ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token"  value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action"      value="enrol">
                    <input type="hidden" name="trial_id"   value="<?php echo (int)$t['id']; ?>">
                    <p class="fs-80 text-muted mb-8">By clicking Enrol I consent to participate in this study.</p>
                    <button type="submit" class="btn btn-small">Enrol (I Consent)</button>
                </form>
                <?php elseif ($withdrawn): ?>
                <span class="pill pill--neutral">Withdrawn</span>
                <?php else: ?>
                <span class="pill pill--neutral">Not eligible</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
