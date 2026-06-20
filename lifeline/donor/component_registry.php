<?php
/**
 * Donor: manage blood component registrations.
 * Allows enrolling in plasma, platelet, bone-marrow, organ donation programmes
 * in addition to whole blood.
 */
require_once '../includes/functions.php';
requireDonor();

$donorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $code   = preg_replace('/[^a-z_]/', '', $_POST['component_code'] ?? '');

    // Validate component exists.
    $compStmt = $pdo->prepare("SELECT * FROM donation_components WHERE code = ? AND is_active = 1");
    $compStmt->execute([$code]);
    $comp = $compStmt->fetch();
    if (!$comp) {
        setFlash('Invalid component.', 'danger');
        redirect(baseUrl() . '/donor/component_registry.php');
    }

    if ($action === 'register') {
        $consentDate = date('Y-m-d');
        $hla         = trim($_POST['hla_type'] ?? '');
        $hospitalId  = ($_POST['hospital_id'] ?? '') !== '' ? (int)$_POST['hospital_id'] : null;

        if ($comp['requires_hospital_link'] && !$hospitalId) {
            setFlash('A linked hospital is required for ' . htmlspecialchars($comp['label']) . '.', 'danger');
            redirect(baseUrl() . '/donor/component_registry.php');
        }

        $pdo->prepare("
            INSERT INTO donor_component_registrations
                (donor_id, component_code, hla_type, hospital_id, consent_date, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE is_active = 1, consent_date = VALUES(consent_date),
                hla_type = VALUES(hla_type), hospital_id = VALUES(hospital_id)
        ")->execute([$donorId, $code, $hla ?: null, $hospitalId, $consentDate]);

        auditLog($pdo, 'component_registered', 'donor_component', $donorId, null, ['code' => $code]);
        setFlash('Registered for ' . htmlspecialchars($comp['label']) . ' donation.', 'success');
    }

    if ($action === 'deregister') {
        $pdo->prepare("UPDATE donor_component_registrations SET is_active = 0 WHERE donor_id = ? AND component_code = ?")
            ->execute([$donorId, $code]);
        auditLog($pdo, 'component_deregistered', 'donor_component', $donorId, null, ['code' => $code]);
        setFlash('Removed from ' . htmlspecialchars($comp['label']) . ' programme.', 'info');
    }

    redirect(baseUrl() . '/donor/component_registry.php');
}

// Load all components and donor's current registrations.
$allComponents = $pdo->query("SELECT * FROM donation_components WHERE is_active = 1 ORDER BY id")->fetchAll();

$regStmt = $pdo->prepare("SELECT * FROM donor_component_registrations WHERE donor_id = ?");
$regStmt->execute([$donorId]);
$registered = [];
foreach ($regStmt->fetchAll() as $r) {
    $registered[$r['component_code']] = $r;
}

// Hospitals for the dropdown (organ/marrow link).
$hospitals = $pdo->query("SELECT user_id, hospital_name FROM hospital_profiles WHERE is_verified = 1 ORDER BY hospital_name")->fetchAll();

include '../includes/header.php';
?>

<div class="card" style="max-width:680px;margin:2rem auto">
    <div class="card-header">
        <h1>Donation Component Registry</h1>
        <a href="<?php echo baseUrl(); ?>/donor/dashboard.php" class="btn btn-secondary">Back</a>
    </div>
    <p class="text-muted fs-90 mb-20">
        Register for additional blood donation components beyond whole blood.
        Some programmes (bone marrow, organs) require HLA typing and a linked verified hospital.
    </p>

    <?php foreach ($allComponents as $comp): ?>
    <?php $reg = $registered[$comp['code']] ?? null; $isActive = $reg && $reg['is_active']; ?>
    <div class="card mb-16 <?php echo $isActive ? 'border-l-success' : ''; ?>">
        <div class="flex gap-12 items-start">
            <div style="flex:1">
                <h3 class="mb-4"><?php echo htmlspecialchars($comp['label']); ?></h3>
                <p class="fs-85 text-muted m-0">
                    Cooloff: <?php echo (int)$comp['cooloff_days']; ?> days
                    <?php if ($comp['requires_hla']): ?> · HLA typing required<?php endif; ?>
                    <?php if ($comp['requires_hospital_link']): ?> · Hospital link required<?php endif; ?>
                </p>
                <?php if ($isActive): ?>
                <p class="fs-85 text-success-dark mt-4">
                    Registered <?php echo htmlspecialchars($reg['consent_date']); ?>
                    <?php if ($reg['hla_type']): ?> · HLA: <code><?php echo htmlspecialchars($reg['hla_type']); ?></code><?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
            <?php if ($isActive): ?>
            <form method="POST" action="" style="flex-shrink:0">
                <input type="hidden" name="csrf_token"      value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action"          value="deregister">
                <input type="hidden" name="component_code"  value="<?php echo htmlspecialchars($comp['code']); ?>">
                <button type="submit" class="btn btn-small btn-secondary"
                        onclick="return confirm('Remove yourself from this programme?')">Deregister</button>
            </form>
            <?php else: ?>
            <form method="POST" action="" style="flex-shrink:0;display:flex;flex-direction:column;gap:6px;align-items:flex-end">
                <input type="hidden" name="csrf_token"      value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action"          value="register">
                <input type="hidden" name="component_code"  value="<?php echo htmlspecialchars($comp['code']); ?>">
                <?php if ($comp['requires_hla']): ?>
                <input type="text" name="hla_type" placeholder="HLA type (e.g. A*02:01)" style="font-size:0.8rem">
                <?php endif; ?>
                <?php if ($comp['requires_hospital_link'] && $hospitals): ?>
                <select name="hospital_id" required style="font-size:0.8rem">
                    <option value="">— Select hospital —</option>
                    <?php foreach ($hospitals as $h): ?>
                    <option value="<?php echo (int)$h['user_id']; ?>"><?php echo htmlspecialchars($h['hospital_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="submit" class="btn btn-small">Register</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>
