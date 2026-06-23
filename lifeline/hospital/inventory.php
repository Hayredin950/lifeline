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

    // Request transfer from another hospital's inventory lot
    if ($action === 'request_transfer') {
        $invId    = (int)($_POST['inv_id'] ?? 0);
        $unitsReq = max(1, (int)($_POST['units_requested'] ?? 1));
        $urgency  = in_array($_POST['urgency'] ?? '', ['routine','urgent','critical'], true) ? $_POST['urgency'] : 'routine';

        // Verify the inventory lot exists, belongs to another hospital, and is available
        $lot = $pdo->prepare("SELECT * FROM blood_unit_inventory WHERE id = ? AND is_available = 1 AND hospital_id != ?");
        $lot->execute([$invId, $hospitalId]);
        $lot = $lot->fetch();

        if (!$lot) {
            setFlash('Inventory lot not found or no longer available.', 'danger');
            redirect(baseUrl() . '/hospital/inventory.php');
        }

        $unitsReq = min($unitsReq, (int)$lot['units']);

        $pdo->prepare("
            INSERT INTO blood_unit_transfers
                (requesting_hosp, supplying_hosp, inventory_id, blood_type, component_code,
                 units_requested, storage_temp_c, urgency, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'requested')
        ")->execute([
            $hospitalId, $lot['hospital_id'], $invId,
            $lot['blood_type'], $lot['component_code'],
            $unitsReq, $lot['storage_temp_c'], $urgency
        ]);

        // Notify the supplying hospital
        $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link)
            VALUES (?, 'Transfer Request Received', ?, 'system', '/hospital/inventory.php')
        ")->execute([
            $lot['hospital_id'],
            "A hospital has requested {$unitsReq} unit(s) of {$lot['blood_type']} from your inventory."
        ]);

        auditLog($pdo, 'transfer_requested', 'blood_unit_inventory', $invId, null, ['from' => $hospitalId, 'units' => $unitsReq]);
        setFlash('Transfer request sent to the supplying hospital.', 'success');
    }

    // Supplying hospital accepts a transfer
    if ($action === 'accept_transfer') {
        $transferId    = (int)($_POST['transfer_id'] ?? 0);
        $unitsConfirmed = max(1, (int)($_POST['units_confirmed'] ?? 1));

        $xfer = $pdo->prepare("SELECT * FROM blood_unit_transfers WHERE id = ? AND supplying_hosp = ? AND status = 'requested'");
        $xfer->execute([$transferId, $hospitalId]);
        $xfer = $xfer->fetch();

        if ($xfer) {
            $unitsConfirmed = min($unitsConfirmed, (int)$xfer['units_requested']);
            $pdo->prepare("
                UPDATE blood_unit_transfers
                SET status = 'accepted', units_confirmed = ?, dispatched_at = NOW()
                WHERE id = ?
            ")->execute([$unitsConfirmed, $transferId]);

            // Reduce or mark inventory lot
            if ($xfer['inventory_id']) {
                $pdo->prepare("
                    UPDATE blood_unit_inventory
                    SET units = GREATEST(0, units - ?),
                        is_available = IF(units - ? <= 0, 0, 1)
                    WHERE id = ?
                ")->execute([$unitsConfirmed, $unitsConfirmed, $xfer['inventory_id']]);
            }

            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link)
                VALUES (?, 'Transfer Accepted', ?, 'system', '/hospital/inventory.php')
            ")->execute([
                $xfer['requesting_hosp'],
                "Your transfer request for {$unitsConfirmed} unit(s) of {$xfer['blood_type']} has been accepted and dispatched."
            ]);

            auditLog($pdo, 'transfer_accepted', 'blood_unit_transfers', $transferId, null, ['units' => $unitsConfirmed]);
            setFlash('Transfer accepted and marked as dispatched.', 'success');
        }
    }

    // Requesting hospital marks transfer as received
    if ($action === 'mark_received') {
        $transferId = (int)($_POST['transfer_id'] ?? 0);
        $pdo->prepare("
            UPDATE blood_unit_transfers
            SET status = 'received', received_at = NOW()
            WHERE id = ? AND requesting_hosp = ? AND status = 'accepted'
        ")->execute([$transferId, $hospitalId]);
        setFlash('Transfer marked as received.', 'success');
    }

    // Reject a transfer request (supplier side)
    if ($action === 'reject_transfer') {
        $transferId = (int)($_POST['transfer_id'] ?? 0);
        $note       = trim($_POST['status_note'] ?? '');
        $pdo->prepare("
            UPDATE blood_unit_transfers
            SET status = 'rejected', status_note = ?
            WHERE id = ? AND supplying_hosp = ? AND status = 'requested'
        ")->execute([$note ?: null, $transferId, $hospitalId]);
        setFlash('Transfer request rejected.', 'info');
    }

    redirect(baseUrl() . '/hospital/inventory.php');
}

try {
    $inventory = $pdo->prepare("
        SELECT i.*, dc.label AS component_label
        FROM blood_unit_inventory i
        LEFT JOIN donation_components dc ON dc.code = i.component_code
        WHERE i.hospital_id = ? AND i.is_available = 1
        ORDER BY i.expiry_date ASC
    ");
    $inventory->execute([$hospitalId]);
    $lots = $inventory->fetchAll();
} catch (PDOException $e) {
    $inventory = $pdo->prepare("
        SELECT i.*, NULL AS component_label
        FROM blood_unit_inventory i
        WHERE i.hospital_id = ? AND i.is_available = 1
        ORDER BY i.expiry_date ASC
    ");
    $inventory->execute([$hospitalId]);
    $lots = $inventory->fetchAll();
}

try {
    $components = $pdo->query("SELECT code, label FROM donation_components WHERE is_active = 1 ORDER BY id")->fetchAll();
} catch (PDOException $e) {
    $components = [];
}

// Transfer requests pending my action (I am the supplier)
try {
    $incomingXfers = $pdo->prepare("
        SELECT t.*, hp.hospital_name AS requesting_name, hp.phone AS requesting_phone
        FROM blood_unit_transfers t
        JOIN hospital_profiles hp ON hp.user_id = t.requesting_hosp
        WHERE t.supplying_hosp = ? AND t.status = 'requested'
        ORDER BY t.created_at DESC
    ");
    $incomingXfers->execute([$hospitalId]);
    $incoming = $incomingXfers->fetchAll();
} catch (PDOException $e) {
    $incoming = [];
}

// My outgoing transfer requests
try {
    $outgoingXfers = $pdo->prepare("
        SELECT t.*, hp.hospital_name AS supplying_name
        FROM blood_unit_transfers t
        JOIN hospital_profiles hp ON hp.user_id = t.supplying_hosp
        WHERE t.requesting_hosp = ? AND t.status IN ('requested','accepted','in_transit')
        ORDER BY t.created_at DESC
    ");
    $outgoingXfers->execute([$hospitalId]);
    $outgoing = $outgoingXfers->fetchAll();
} catch (PDOException $e) {
    $outgoing = [];
}

// Other hospitals' available inventory (browse + request)
try {
    $otherInvStmt = $pdo->prepare("
        SELECT i.*, hp.hospital_name, hp.city, hp.state, dc.label AS component_label
        FROM blood_unit_inventory i
        JOIN hospital_profiles hp ON hp.user_id = i.hospital_id
        LEFT JOIN donation_components dc ON dc.code = i.component_code
        WHERE i.hospital_id != ? AND i.is_available = 1 AND i.expiry_date >= CURDATE()
        ORDER BY i.blood_type, i.expiry_date ASC
    ");
    $otherInvStmt->execute([$hospitalId]);
    $otherLots = $otherInvStmt->fetchAll();
} catch (PDOException $e) {
    $otherLots = [];
}

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
                <option value="whole_blood">Whole Blood</option>
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

<?php if ($incoming): ?>
<div class="card" style="max-width:760px;margin:1.5rem auto">
    <h2 class="fs-105 mb-12">&#128228; Incoming Transfer Requests (<?php echo count($incoming); ?>)</h2>
    <p class="text-muted fs-90 mb-16">Other hospitals requesting units from your inventory. Accept to confirm dispatch.</p>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>From Hospital</th><th>Blood Type</th><th>Units Req.</th><th>Urgency</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($incoming as $xf): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($xf['requesting_name']); ?></strong>
                    <?php if ($xf['requesting_phone']): ?><div class="fs-80 text-muted"><?php echo htmlspecialchars($xf['requesting_phone']); ?></div><?php endif; ?>
                </td>
                <td><strong><?php echo htmlspecialchars($xf['blood_type']); ?></strong></td>
                <td><?php echo (int)$xf['units_requested']; ?></td>
                <td><?php echo ucfirst($xf['urgency']); ?></td>
                <td>
                    <form method="POST" action="" class="flex gap-4 flex-wrap items-center">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="transfer_id" value="<?php echo (int)$xf['id']; ?>">
                        <input type="number" name="units_confirmed" value="<?php echo (int)$xf['units_requested']; ?>"
                               min="1" max="<?php echo (int)$xf['units_requested']; ?>" style="width:60px">
                        <button name="action" value="accept_transfer" type="submit" class="btn btn-small text-success-dark">Accept</button>
                        <button name="action" value="reject_transfer" type="submit" class="btn btn-small bg-danger-dark"
                                onclick="return confirm('Reject this transfer request?')">Reject</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($outgoing): ?>
<div class="card" style="max-width:760px;margin:1.5rem auto">
    <h2 class="fs-105 mb-12">&#128228; My Transfer Requests</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Supplying Hospital</th><th>Blood Type</th><th>Units</th><th>Urgency</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($outgoing as $xf): ?>
            <tr>
                <td><?php echo htmlspecialchars($xf['supplying_name']); ?></td>
                <td><strong><?php echo htmlspecialchars($xf['blood_type']); ?></strong></td>
                <td><?php echo (int)$xf['units_requested']; ?></td>
                <td><?php echo ucfirst($xf['urgency']); ?></td>
                <td><span class="pill <?php echo $xf['status']==='accepted'?'pill--success':($xf['status']==='requested'?'pill--warning':'pill--info'); ?>"><?php echo ucfirst($xf['status']); ?></span></td>
                <td>
                    <?php if ($xf['status'] === 'accepted'): ?>
                    <form method="POST" action="" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="transfer_id" value="<?php echo (int)$xf['id']; ?>">
                        <button name="action" value="mark_received" type="submit" class="btn btn-small text-success-dark">Mark Received</button>
                    </form>
                    <?php else: ?>
                        <span class="text-muted fs-85">Awaiting supplier</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($otherLots): ?>
<div class="card" style="max-width:760px;margin:1.5rem auto">
    <h2 class="fs-105 mb-12">&#128270; Available Network Inventory</h2>
    <p class="text-muted fs-90 mb-16">Blood units registered as available by other hospitals. Request a transfer below.</p>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Hospital</th><th>Blood Type</th><th>Component</th><th>Units</th><th>Temp</th><th>Expires</th><th>Request</th></tr>
            </thead>
            <tbody>
            <?php foreach ($otherLots as $l):
                $daysLeft = (int)ceil((strtotime($l['expiry_date']) - time()) / 86400);
                $expClass = $daysLeft <= 3 ? 'text-crimson fw-600' : ($daysLeft <= 7 ? 'text-amber' : '');
            ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($l['hospital_name']); ?></strong>
                    <div class="fs-80 text-muted"><?php echo htmlspecialchars($l['city'] . ', ' . $l['state']); ?></div>
                </td>
                <td><strong><?php echo htmlspecialchars($l['blood_type']); ?></strong></td>
                <td><?php echo htmlspecialchars($l['component_label'] ?: $l['component_code']); ?></td>
                <td><?php echo (int)$l['units']; ?></td>
                <td><?php echo $l['storage_temp_c']; ?>°C</td>
                <td class="<?php echo $expClass; ?>"><?php echo htmlspecialchars($l['expiry_date']); ?> <span class="fs-80">(<?php echo $daysLeft; ?>d)</span></td>
                <td>
                    <form method="POST" action="" class="flex gap-4 items-center flex-wrap">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action"     value="request_transfer">
                        <input type="hidden" name="inv_id"     value="<?php echo (int)$l['id']; ?>">
                        <input type="number" name="units_requested" value="1" min="1" max="<?php echo (int)$l['units']; ?>" style="width:55px">
                        <select name="urgency" style="min-width:90px">
                            <option value="routine">Routine</option>
                            <option value="urgent">Urgent</option>
                            <option value="critical">Critical</option>
                        </select>
                        <button type="submit" class="btn btn-small">Request</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card" style="max-width:760px;margin:1.5rem auto">
    <h2 class="fs-105 mb-12">&#128270; Available Network Inventory</h2>
    <p class="text-muted fs-90">No other hospitals have available inventory at this time.</p>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
