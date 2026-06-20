<?php
/**
 * Admin: manage donation component type catalogue.
 * Enable/disable types, adjust cooloff days.
 */
require_once '../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $code   = preg_replace('/[^a-z_]/', '', $_POST['code'] ?? '');

    if ($action === 'toggle' && $code) {
        $pdo->prepare("UPDATE donation_components SET is_active = 1 - is_active WHERE code = ?")
            ->execute([$code]);
        auditLog($pdo, 'component_type_toggle', 'donation_components', 0, null, ['code' => $code]);
        setFlash('Component status updated.', 'success');
    }

    if ($action === 'update' && $code) {
        $cooloff = max(0, min(730, (int)($_POST['cooloff_days'] ?? 56)));
        $pdo->prepare("UPDATE donation_components SET cooloff_days = ? WHERE code = ?")
            ->execute([$cooloff, $code]);
        auditLog($pdo, 'component_type_updated', 'donation_components', 0, null, ['code' => $code, 'cooloff' => $cooloff]);
        setFlash('Component updated.', 'success');
    }

    redirect(baseUrl() . '/admin/component_types.php');
}

$components = $pdo->query("SELECT dc.*, COUNT(dcr.id) AS registrations
    FROM donation_components dc
    LEFT JOIN donor_component_registrations dcr ON dcr.component_code = dc.code AND dcr.is_active = 1
    GROUP BY dc.id ORDER BY dc.id")->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Blood Component Types</h1>
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back</a>
    </div>
    <p class="text-muted fs-90 mb-20">
        Manage the donation component catalogue. Disabling a type hides it from donor registration
        but does not remove existing enrolments. Adjust cooloff periods as clinical guidelines change.
    </p>

    <div class="table-wrapper">
        <table aria-label="Donation component types">
            <thead>
                <tr>
                    <th scope="col">Code</th>
                    <th scope="col">Label</th>
                    <th scope="col">Cooloff (days)</th>
                    <th scope="col">HLA Required</th>
                    <th scope="col">Hospital Link</th>
                    <th scope="col">Active Enrolments</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($components as $c): ?>
                <tr class="<?php echo $c['is_active'] ? '' : 'text-muted'; ?>">
                    <td><code><?php echo htmlspecialchars($c['code']); ?></code></td>
                    <td><?php echo htmlspecialchars($c['label']); ?></td>
                    <td>
                        <form method="POST" action="" style="display:flex;gap:6px;align-items:center">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action"    value="update">
                            <input type="hidden" name="code"      value="<?php echo htmlspecialchars($c['code']); ?>">
                            <input type="number" name="cooloff_days" value="<?php echo (int)$c['cooloff_days']; ?>"
                                   min="0" max="730" style="width:64px">
                            <button type="submit" class="btn btn-small btn-secondary">Save</button>
                        </form>
                    </td>
                    <td><?php echo $c['requires_hla']           ? '✓' : '—'; ?></td>
                    <td><?php echo $c['requires_hospital_link'] ? '✓' : '—'; ?></td>
                    <td><?php echo (int)$c['registrations']; ?></td>
                    <td><?php echo $c['is_active']
                        ? '<span class="text-success-dark fw-600">Active</span>'
                        : '<span class="text-muted">Disabled</span>'; ?></td>
                    <td>
                        <form method="POST" action="" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="code"   value="<?php echo htmlspecialchars($c['code']); ?>">
                            <button type="submit" class="btn btn-small <?php echo $c['is_active'] ? 'btn-secondary' : ''; ?>">
                                <?php echo $c['is_active'] ? 'Disable' : 'Enable'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
