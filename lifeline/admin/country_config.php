<?php
/**
 * Admin: per-country compliance and configuration management.
 * Allows enabling countries, adjusting cooloff days, and toggling GDPR/HIPAA modes.
 */
require_once '../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $iso  = strtoupper(trim($_POST['iso2'] ?? ''));
    $act  = $_POST['action'] ?? '';

    if ($act === 'toggle' && preg_match('/^[A-Z]{2}$/', $iso)) {
        $pdo->prepare("UPDATE country_config SET is_active = 1 - is_active WHERE iso2 = ?")->execute([$iso]);
        auditLog($pdo, 'country_toggle', 'country_config', 0, null, ['iso2' => $iso]);
        setFlash('Country status updated.', 'success');
    }

    if ($act === 'update' && preg_match('/^[A-Z]{2}$/', $iso)) {
        $cooloff = max(28, min(365, (int)($_POST['cooloff_days'] ?? 56)));
        $gdpr    = isset($_POST['gdpr_mode'])  ? 1 : 0;
        $hipaa   = isset($_POST['hipaa_mode']) ? 1 : 0;
        $pdo->prepare("
            UPDATE country_config
            SET donation_cooloff_days = ?, gdpr_mode = ?, hipaa_mode = ?
            WHERE iso2 = ?
        ")->execute([$cooloff, $gdpr, $hipaa, $iso]);
        auditLog($pdo, 'country_updated', 'country_config', 0, null, ['iso2' => $iso, 'cooloff' => $cooloff]);
        setFlash('Country configuration saved.', 'success');
    }

    redirect(baseUrl() . '/admin/country_config.php');
}

$countries = $pdo->query("SELECT * FROM country_config ORDER BY is_active DESC, name")->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Country Configuration</h1>
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back</a>
    </div>
    <p class="text-muted fs-90 mb-20">
        Controls which countries are active and their per-country compliance rules.
        The active <strong>COUNTRY_ISO2</strong> env var selects which country this node serves.
        Current node: <code><?php echo htmlspecialchars(getCountryIso()); ?></code>
    </p>

    <div class="table-wrapper">
        <table aria-label="Per-country configuration">
            <thead>
                <tr>
                    <th scope="col">ISO</th>
                    <th scope="col">Country</th>
                    <th scope="col">Locale</th>
                    <th scope="col">Currency</th>
                    <th scope="col">Cooloff (days)</th>
                    <th scope="col">GDPR</th>
                    <th scope="col">HIPAA</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($countries as $c): ?>
                <tr class="<?php echo $c['is_active'] ? '' : 'text-muted'; ?>">
                    <td><code><?php echo htmlspecialchars($c['iso2']); ?></code></td>
                    <td><?php echo htmlspecialchars($c['name']); ?></td>
                    <td><?php echo htmlspecialchars($c['default_locale']); ?></td>
                    <td><?php echo htmlspecialchars($c['currency']); ?></td>
                    <td>
                        <form method="POST" action="" style="display:flex;gap:6px;align-items:center">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action"    value="update">
                            <input type="hidden" name="iso2"      value="<?php echo htmlspecialchars($c['iso2']); ?>">
                            <input type="number" name="cooloff_days" value="<?php echo (int)$c['donation_cooloff_days']; ?>"
                                   min="28" max="365" style="width:64px">
                            <label style="display:flex;align-items:center;gap:4px;font-size:0.8rem">
                                <input type="checkbox" name="gdpr_mode"  <?php echo $c['gdpr_mode']  ? 'checked' : ''; ?>> GDPR
                            </label>
                            <label style="display:flex;align-items:center;gap:4px;font-size:0.8rem">
                                <input type="checkbox" name="hipaa_mode" <?php echo $c['hipaa_mode'] ? 'checked' : ''; ?>> HIPAA
                            </label>
                            <button type="submit" class="btn btn-small btn-secondary">Save</button>
                        </form>
                    </td>
                    <td><?php echo $c['gdpr_mode']  ? '<span class="badge badge-warning">GDPR</span>'  : '—'; ?></td>
                    <td><?php echo $c['hipaa_mode'] ? '<span class="badge badge-warning">HIPAA</span>' : '—'; ?></td>
                    <td><?php echo $c['is_active'] ? '<span class="text-success-dark fw-600">Active</span>' : '<span class="text-muted">Inactive</span>'; ?></td>
                    <td>
                        <form method="POST" action="" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="iso2"   value="<?php echo htmlspecialchars($c['iso2']); ?>">
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
