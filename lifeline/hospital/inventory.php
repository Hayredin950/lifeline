<?php
/**
 * Hospital: manage blood unit inventory available for inter-facility transfers.
 * Hospitals register surplus units; other hospitals can request a transfer.
 */
require_once '../includes/functions.php';
requireHospital();

$hospitalId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $bt      = $_POST['blood_type']     ?? '';
        $comp    = preg_replace('/[^a-z_]/', '', $_POST['component_code'] ?? 'whole_blood');
        $units   = max(1, (int)($_POST['units'] ?? 1));
        $temp    = round((float)($_POST['storage_temp_c'] ?? 4.0), 1);
        $expiry  = $_POST['expiry_date'] ?? '';

        $validTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        if (!in_array($bt, $validTypes, true) || !$expiry) {
            setFlash('Blood type and expiry date are required.', 'danger');
            redirect(baseUrl() . '/hospital/inventory.php');
        }

        $pdo->prepare("
            INSERT INTO blood_unit_inventory (hospital_id, blood_type, component_code, units, storage_temp_c, expiry_date, is_available)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ")->execute([$hospitalId, $bt, $comp, $units, $temp, $expiry]);

        auditLog($pdo, 'inventory_add', 'blood_unit_inventory', $hospitalId, null, ['blood_type' => $bt, 'units' => $units]);
        setFlash('Inventory lot added.', 'success');
    }

    if ($action === 'withdraw') {
        $invId = (int)($_POST['inv_id'] ?? 0);
        $pdo->prepare("UPDATE blood_unit_inventory SET is_available = 0 WHERE id = ? AND hospital_id = ?")
            ->execute([$invId, $hospitalId]);
        setFlash('Lot withdrawn from availability.', 'info');
    }

    redirect(baseUrl() . '/hospital/inventory.php');
}

$inventory = $pdo->prepare("
    SELECT i.*, dc.label AS component_label
    FROM blood_unit_inventory i
    LEFT JOIN donation_components dc ON dc.code = i.component_code
    WHERE i.hospital_id = ? AND i.is_available = 1
    ORDER BY i.expiry_date ASC
");
$inventory->execute([$hospitalId]);
$lots = $inventory->fetchAll();

$components = $pdo->query("SELECT code, label FROM donation_components WHERE is_active = 1 ORDER BY id")->fetchAll();

include '../includes/header.php';
?>

<div class="card" style="max-width:760px;margin:2rem auto">
    <div class="card-header">
        <h1>Blood Unit Inventory</h1>
        <a href="<?php echo baseUrl(); ?>/hospital/dashboard.php" class="btn btn-secondary">Back</a>
    </div>
    <p class="text-muted fs-90 mb-20">
        Register surplus blood units available for inter-facility cold-chain transfers.
        Other hospitals can request these units when their own supply is insufficient.
    </p>

    <h2 class="fs-105 mb-12">Add Inventory Lot</h2>
    <form method="POST" action="" class="flex flex-wrap gap-12 items-end mb-24">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action"     value="add">
        <div class="form-group mb-0 minw-120">
            <label for="blood_type">Blood Type *</label>
            <select id="blood_type" name="blood_type" required>
                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                    <option value="<?php echo $bt; ?>"><?php echo $bt; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0 minw-160">
            <label for="component_code">Component</label>
            <select id="component_code" name="component_code">
                <?php foreach ($components as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['code']); ?>"><?php echo htmlspecialchars($c['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0" style="width:80px">
            <label for="units">Units *</label>
            <input type="number" id="units" name="units" value="1" min="1" max="999" required>
        </div>
        <div class="form-group mb-0" style="width:100px">
            <label for="storage_temp_c">Temp (°C)</label>
            <input type="number" id="storage_temp_c" name="storage_temp_c" value="4.0" step="0.1" min="-80" max="37">
        </div>
        <div class="form-group mb-0 minw-140">
            <label for="expiry_date">Expiry Date *</label>
            <input type="date" id="expiry_date" name="expiry_date" required>
        </div>
        <button type="submit" class="btn mb-0">Add Lot</button>
    </form>

    <h2 class="fs-105 mb-12">Available Lots (<?php echo count($lots); ?>)</h2>
    <?php if ($lots): ?>
    <div class="table-wrapper">
        <table aria-label="Your available blood unit inventory">
            <thead>
                <tr>
                    <th scope="col">Blood Type</th>
                    <th scope="col">Component</th>
                    <th scope="col">Units</th>
                    <th scope="col">Temp (°C)</th>
                    <th scope="col">Expires</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lots as $l):
                $daysLeft = (int)ceil((strtotime($l['expiry_date']) - time()) / 86400);
                $expClass = $daysLeft <= 3 ? 'text-crimson fw-600' : ($daysLeft <= 7 ? 'text-amber' : '');
            ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($l['blood_type']); ?></strong></td>
                    <td><?php echo htmlspecialchars($l['component_label'] ?: $l['component_code']); ?></td>
                    <td><?php echo (int)$l['units']; ?></td>
                    <td><?php echo $l['storage_temp_c']; ?>°C</td>
                    <td class="<?php echo $expClass; ?>">
                        <?php echo htmlspecialchars($l['expiry_date']); ?>
                        <span class="fs-80">(<?php echo $daysLeft; ?> days)</span>
                    </td>
                    <td>
                        <form method="POST" action="" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action"     value="withdraw">
                            <input type="hidden" name="inv_id"     value="<?php echo (int)$l['id']; ?>">
                            <button type="submit" class="btn btn-small btn-secondary"
                                    onclick="return confirm('Withdraw this lot from availability?')">Withdraw</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted">No lots registered. Add inventory above to participate in inter-facility transfers.</p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
