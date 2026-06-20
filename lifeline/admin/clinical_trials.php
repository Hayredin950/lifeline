<?php
/**
 * Admin: create and manage clinical trials / rare-blood recruitment campaigns.
 * Admins set eligibility criteria; the matching engine surfaces eligible donors.
 */
require_once '../includes/functions.php';
requireAdmin();

$adminId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title    = trim(substr($_POST['title'] ?? '', 0, 200));
        $desc     = trim(substr($_POST['description'] ?? '', 0, 2000));
        $notes    = trim(substr($_POST['eligibility_notes'] ?? '', 0, 2000));
        $bts      = array_filter(array_map('trim', explode(',', $_POST['blood_types'] ?? '')));
        $comps    = array_filter(array_map('trim', explode(',', $_POST['component_codes'] ?? '')));
        $minDon   = max(0, (int)($_POST['min_donations'] ?? 0));
        $until    = $_POST['recruiting_until'] ?: null;
        $target   = max(0, (int)($_POST['target_enrolment'] ?? 0));

        if (!$title) {
            setFlash('Title is required.', 'danger');
            redirect(baseUrl() . '/admin/clinical_trials.php');
        }

        $pdo->prepare("
            INSERT INTO clinical_trials (title, description, eligibility_notes, blood_types, component_codes,
                min_donations, recruiting_until, target_enrolment, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', ?)
        ")->execute([
            $title, $desc ?: null, $notes ?: null,
            $bts   ? implode(',', $bts)   : null,
            $comps ? implode(',', $comps) : null,
            $minDon, $until, $target, $adminId
        ]);
        auditLog($pdo, 'trial_created', 'clinical_trials', $adminId, null, ['title' => $title]);
        setFlash('Trial created.', 'success');
    }

    if ($action === 'setstatus') {
        $trialId   = (int)($_POST['trial_id'] ?? 0);
        $newStatus = in_array($_POST['status'] ?? '', ['open','closed','paused'], true) ? $_POST['status'] : 'paused';
        $pdo->prepare("UPDATE clinical_trials SET status = ? WHERE id = ?")->execute([$newStatus, $trialId]);
        auditLog($pdo, 'trial_status', 'clinical_trials', $adminId, null, ['trial_id' => $trialId, 'status' => $newStatus]);
        setFlash('Trial status updated.', 'success');
    }

    redirect(baseUrl() . '/admin/clinical_trials.php');
}

$trials = $pdo->query("
    SELECT ct.*, u.email AS creator_email,
           (SELECT COUNT(*) FROM trial_enrolments te WHERE te.trial_id = ct.id AND te.status = 'enrolled') AS enrolled_count
    FROM clinical_trials ct
    JOIN users u ON u.id = ct.created_by
    ORDER BY ct.created_at DESC
")->fetchAll();

$components = $pdo->query("SELECT code, label FROM donation_components WHERE is_active = 1 ORDER BY id")->fetchAll();

// For each open trial count eligible donors.
function countEligibleDonors(PDO $pdo, array $trial): int {
    $sql = "SELECT COUNT(DISTINCT dp.user_id) FROM donor_profiles dp
            JOIN users u ON u.id = dp.user_id
            WHERE u.is_active = 1 AND dp.is_available = 1";
    $params = [];

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

    return (int)$pdo->prepare($sql)->execute($params) ? (int)$pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Clinical Trials &amp; Rare-Blood Recruitment</h1>
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back</a>
    </div>
    <p class="text-muted fs-90 mb-20">
        Create opt-in campaigns for donors who match specific blood types, component registrations,
        or donation history criteria. Donors consent explicitly before joining a trial.
    </p>
</div>

<div class="card">
    <h2>Create New Trial</h2>
    <form method="POST" action="" class="flex flex-col gap-12">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action"     value="create">
        <div class="form-group mb-0">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" required maxlength="200" placeholder="e.g. Rare Blood Type O- Plasma Study">
        </div>
        <div class="form-group mb-0">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3" placeholder="What is this trial / campaign about?"></textarea>
        </div>
        <div class="form-group mb-0">
            <label for="eligibility_notes">Eligibility Notes (shown to donors)</label>
            <textarea id="eligibility_notes" name="eligibility_notes" rows="2" placeholder="e.g. O- donors who have donated plasma at least twice"></textarea>
        </div>
        <div class="flex flex-wrap gap-12">
            <div class="form-group mb-0 flex-1 minw-180">
                <label for="blood_types">Blood Types (comma-separated, blank = any)</label>
                <input type="text" id="blood_types" name="blood_types" placeholder="O-,O+">
            </div>
            <div class="form-group mb-0 flex-1 minw-200">
                <label for="component_codes">Component Codes (comma-separated, blank = any)</label>
                <input type="text" id="component_codes" name="component_codes" placeholder="plasma,platelets">
                <div class="fs-80 text-muted">Available: <?php echo implode(', ', array_column($components, 'code')); ?></div>
            </div>
            <div class="form-group mb-0" style="width:140px">
                <label for="min_donations">Min Donations</label>
                <input type="number" id="min_donations" name="min_donations" value="0" min="0">
            </div>
            <div class="form-group mb-0" style="width:160px">
                <label for="target_enrolment">Target Enrolment</label>
                <input type="number" id="target_enrolment" name="target_enrolment" value="0" min="0">
            </div>
            <div class="form-group mb-0" style="width:160px">
                <label for="recruiting_until">Recruiting Until</label>
                <input type="date" id="recruiting_until" name="recruiting_until">
            </div>
        </div>
        <div>
            <button type="submit" class="btn">Create Trial</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Trials (<?php echo count($trials); ?>)</h2>
    <?php if ($trials): ?>
    <?php foreach ($trials as $t): ?>
    <div class="card mb-16 <?php echo $t['status'] === 'open' ? 'border-l-success' : ''; ?>">
        <div class="flex gap-12 items-start">
            <div style="flex:1">
                <div class="flex gap-8 items-center mb-4">
                    <h3 class="m-0"><?php echo htmlspecialchars($t['title']); ?></h3>
                    <span class="pill <?php echo $t['status'] === 'open' ? 'pill--success' : ($t['status'] === 'paused' ? 'pill--warning' : 'pill--neutral'); ?>">
                        <?php echo ucfirst($t['status']); ?>
                    </span>
                </div>
                <?php if ($t['description']): ?>
                <p class="fs-85 text-muted mb-4"><?php echo nl2br(htmlspecialchars($t['description'])); ?></p>
                <?php endif; ?>
                <div class="fs-80 text-muted flex flex-wrap gap-12">
                    <?php if ($t['blood_types']): ?><span>Blood types: <strong><?php echo htmlspecialchars($t['blood_types']); ?></strong></span><?php endif; ?>
                    <?php if ($t['component_codes']): ?><span>Components: <strong><?php echo htmlspecialchars($t['component_codes']); ?></strong></span><?php endif; ?>
                    <?php if ($t['min_donations']): ?><span>Min donations: <strong><?php echo (int)$t['min_donations']; ?></strong></span><?php endif; ?>
                    <?php if ($t['recruiting_until']): ?><span>Until: <strong><?php echo htmlspecialchars($t['recruiting_until']); ?></strong></span><?php endif; ?>
                    <span>Enrolled: <strong><?php echo (int)$t['enrolled_count']; ?><?php echo $t['target_enrolment'] ? '/' . (int)$t['target_enrolment'] : ''; ?></strong></span>
                    <span><a href="<?php echo baseUrl(); ?>/admin/trial_enrolments.php?trial_id=<?php echo (int)$t['id']; ?>" class="text-link">View enrolments</a></span>
                </div>
            </div>
            <form method="POST" action="" class="flex gap-6 flex-col" style="align-items:flex-end">
                <input type="hidden" name="csrf_token"  value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action"      value="setstatus">
                <input type="hidden" name="trial_id"   value="<?php echo (int)$t['id']; ?>">
                <select name="status">
                    <?php foreach (['open','paused','closed'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $t['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-small">Update</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <p class="text-muted">No trials yet. Create one above.</p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
