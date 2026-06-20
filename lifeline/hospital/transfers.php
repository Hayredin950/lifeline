<?php
/**
 * Hospital: inter-facility cold-chain transfer requests.
 * Requesters create transfer requests; suppliers accept/dispatch; requesters confirm receipt.
 * Matching: shows hospitals within network that have matching inventory lots.
 */
require_once '../includes/functions.php';
requireHospital();

$hospitalId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    // Create a new transfer request.
    if ($action === 'request') {
        $bt       = $_POST['blood_type']     ?? '';
        $comp     = preg_replace('/[^a-z_]/', '', $_POST['component_code'] ?? 'whole_blood');
        $units    = max(1, (int)($_POST['units_requested'] ?? 1));
        $temp     = round((float)($_POST['storage_temp_c'] ?? 4.0), 1);
        $urgency  = in_array($_POST['urgency'] ?? '', ['routine','urgent','critical'], true) ? $_POST['urgency'] : 'routine';
        $suppHosp = (int)($_POST['supplying_hosp'] ?? 0);
        $invId    = ($_POST['inventory_id'] ?? '') !== '' ? (int)$_POST['inventory_id'] : null;

        $validTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        if (!in_array($bt, $validTypes, true) || !$suppHosp || $suppHosp === $hospitalId) {
            setFlash('Select a valid blood type and a different supplying hospital.', 'danger');
            redirect(baseUrl() . '/hospital/transfers.php');
        }

        $pdo->prepare("
            INSERT INTO blood_unit_transfers
                (requesting_hosp, supplying_hosp, inventory_id, blood_type, component_code,
                 units_requested, storage_temp_c, urgency, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'requested')
        ")->execute([$hospitalId, $suppHosp, $invId, $bt, $comp, $units, $temp, $urgency]);

        $transferId = (int)$pdo->lastInsertId();
        auditLog($pdo, 'transfer_requested', 'blood_unit_transfers', $hospitalId, null, ['transfer_id' => $transferId]);

        // Notify the supplying hospital.
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'transfer', 'Transfer Request', ?)")
            ->execute([$suppHosp, "A hospital has requested {$units} units of {$bt} ({$comp}) — please review in Transfers."]);

        setFlash('Transfer request submitted.', 'success');
    }

    // Supplying hospital: accept / reject.
    if ($action === 'accept' || $action === 'reject') {
        $xferId = (int)($_POST['transfer_id'] ?? 0);
        $note   = trim(substr($_POST['note'] ?? '', 0, 500));
        $stmt   = $pdo->prepare("SELECT * FROM blood_unit_transfers WHERE id = ? AND supplying_hosp = ?");
        $stmt->execute([$xferId, $hospitalId]);
        $xfer   = $stmt->fetch();
        if ($xfer) {
            $newStatus  = $action === 'accept' ? 'accepted' : 'rejected';
            $confirmed  = $action === 'accept' ? max(1, (int)($_POST['units_confirmed'] ?? $xfer['units_requested'])) : null;
            $pdo->prepare("UPDATE blood_unit_transfers SET status = ?, units_confirmed = ?, status_note = ? WHERE id = ?")
                ->execute([$newStatus, $confirmed, $note ?: null, $xferId]);
            auditLog($pdo, "transfer_{$newStatus}", 'blood_unit_transfers', $hospitalId, null, ['transfer_id' => $xferId]);
            $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'transfer', ?, ?)")
                ->execute([$xfer['requesting_hosp'], 'Transfer ' . ucfirst($newStatus),
                           "Your transfer request #{$xferId} has been {$newStatus}." . ($note ? " Note: {$note}" : '')]);
            setFlash('Transfer ' . $newStatus . '.', 'success');
        }
    }

    // Supplying hospital: mark in transit.
    if ($action === 'dispatch') {
        $xferId = (int)($_POST['transfer_id'] ?? 0);
        $pdo->prepare("UPDATE blood_unit_transfers SET status = 'in_transit', dispatched_at = NOW() WHERE id = ? AND supplying_hosp = ? AND status = 'accepted'")
            ->execute([$xferId, $hospitalId]);
        auditLog($pdo, 'transfer_dispatched', 'blood_unit_transfers', $hospitalId, null, ['transfer_id' => $xferId]);
        setFlash('Transfer marked as dispatched.', 'success');
    }

    // Requesting hospital: confirm receipt.
    if ($action === 'receive') {
        $xferId = (int)($_POST['transfer_id'] ?? 0);
        $pdo->prepare("UPDATE blood_unit_transfers SET status = 'received', received_at = NOW() WHERE id = ? AND requesting_hosp = ? AND status = 'in_transit'")
            ->execute([$xferId, $hospitalId]);
        // Mark linked inventory lot as consumed.
        $xferRow = $pdo->prepare("SELECT inventory_id FROM blood_unit_transfers WHERE id = ?");
        $xferRow->execute([$xferId]);
        $invId = $xferRow->fetchColumn();
        if ($invId) {
            $pdo->prepare("UPDATE blood_unit_inventory SET is_available = 0 WHERE id = ?")->execute([$invId]);
        }
        auditLog($pdo, 'transfer_received', 'blood_unit_transfers', $hospitalId, null, ['transfer_id' => $xferId]);
        setFlash('Receipt confirmed. Transfer complete.', 'success');
    }

    // Requestor: cancel a pending request.
    if ($action === 'cancel') {
        $xferId = (int)($_POST['transfer_id'] ?? 0);
        $pdo->prepare("UPDATE blood_unit_transfers SET status = 'cancelled' WHERE id = ? AND requesting_hosp = ? AND status = 'requested'")
            ->execute([$xferId, $hospitalId]);
        setFlash('Transfer request cancelled.', 'info');
    }

    redirect(baseUrl() . '/hospital/transfers.php');
}

// ── Data loading ──────────────────────────────────────────────────────────────

// All transfers involving this hospital.
$xferStmt = $pdo->prepare("
    SELECT t.*,
           req.hospital_name AS req_name,
           sup.hospital_name AS sup_name,
           dc.label          AS component_label
    FROM blood_unit_transfers t
    JOIN hospital_profiles req ON req.user_id = t.requesting_hosp
    JOIN hospital_profiles sup ON sup.user_id = t.supplying_hosp
    LEFT JOIN donation_components dc ON dc.code = t.component_code
    WHERE t.requesting_hosp = ? OR t.supplying_hosp = ?
    ORDER BY FIELD(t.status,'requested','accepted','in_transit','received','rejected','cancelled'),
             t.created_at DESC
    LIMIT 100
");
$xferStmt->execute([$hospitalId, $hospitalId]);
$transfers = $xferStmt->fetchAll();

// Network hospitals (verified, not self) for the request form.
$hospitals = $pdo->prepare("SELECT user_id, hospital_name FROM hospital_profiles WHERE is_verified = 1 AND user_id != ? ORDER BY hospital_name");
$hospitals->execute([$hospitalId]);
$networkHospitals = $hospitals->fetchAll();

// Available inventory across the network (all hospitals except self).
$invStmt = $pdo->prepare("
    SELECT i.*, hp.hospital_name, dc.label AS component_label
    FROM blood_unit_inventory i
    JOIN hospital_profiles hp ON hp.user_id = i.hospital_id
    LEFT JOIN donation_components dc ON dc.code = i.component_code
    WHERE i.is_available = 1 AND i.hospital_id != ? AND i.expiry_date >= CURDATE()
    ORDER BY i.expiry_date ASC
");
$invStmt->execute([$hospitalId]);
$networkInventory = $invStmt->fetchAll();

// Group network inventory by blood_type+component.
$invByType = [];
foreach ($networkInventory as $inv) {
    $key = $inv['blood_type'] . '/' . $inv['component_code'];
    $invByType[$key][] = $inv;
}

$components = $pdo->query("SELECT code, label FROM donation_components WHERE is_active = 1 ORDER BY id")->fetchAll();

$statusLabels = [
    'requested'  => ['label' => 'Requested',   'pill' => 'pill--info'],
    'accepted'   => ['label' => 'Accepted',     'pill' => 'pill--warning'],
    'in_transit' => ['label' => 'In Transit',   'pill' => 'pill--info'],
    'received'   => ['label' => 'Received',     'pill' => 'pill--success'],
    'rejected'   => ['label' => 'Rejected',     'pill' => 'pill--danger'],
    'cancelled'  => ['label' => 'Cancelled',    'pill' => 'pill--neutral'],
];

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Inter-Facility Transfers</h1>
        <div class="flex gap-8">
            <a href="<?php echo baseUrl(); ?>/hospital/inventory.php" class="btn btn-secondary">Manage My Inventory</a>
            <a href="<?php echo baseUrl(); ?>/hospital/dashboard.php" class="btn btn-secondary">Back</a>
        </div>
    </div>
    <p class="text-muted fs-90">
        Request blood units from other verified hospitals when your supply is insufficient.
        Track cold-chain transfers from dispatch to receipt.
    </p>
</div>

<?php if (!empty($networkInventory)): ?>
<div class="card">
    <h2>Network Availability</h2>
    <p class="text-muted fs-85 mb-12">Units available at other hospitals right now (sorted by soonest expiry).</p>
    <div class="table-wrapper">
        <table aria-label="Network blood unit availability">
            <thead>
                <tr>
                    <th>Blood Type</th>
                    <th>Component</th>
                    <th>Hospital</th>
                    <th>Units</th>
                    <th>Temp (°C)</th>
                    <th>Expires</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($networkInventory as $inv):
                $daysLeft = (int)ceil((strtotime($inv['expiry_date']) - time()) / 86400);
                $expClass = $daysLeft <= 3 ? 'text-crimson fw-600' : ($daysLeft <= 7 ? 'text-amber' : '');
            ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($inv['blood_type']); ?></strong></td>
                    <td><?php echo htmlspecialchars($inv['component_label'] ?: $inv['component_code']); ?></td>
                    <td><?php echo htmlspecialchars($inv['hospital_name']); ?></td>
                    <td><?php echo (int)$inv['units']; ?></td>
                    <td><?php echo $inv['storage_temp_c']; ?>°C</td>
                    <td class="<?php echo $expClass; ?>"><?php echo htmlspecialchars($inv['expiry_date']); ?> <span class="fs-80">(<?php echo $daysLeft; ?>d)</span></td>
                    <td>
                        <!-- Quick-request prefill via query string -->
                        <a href="#request-form?bt=<?php echo urlencode($inv['blood_type']); ?>&comp=<?php echo urlencode($inv['component_code']); ?>&hosp=<?php echo (int)$inv['hospital_id']; ?>&inv=<?php echo (int)$inv['id']; ?>"
                           class="btn btn-small"
                           onclick="prefillRequest('<?php echo htmlspecialchars($inv['blood_type'],ENT_QUOTES); ?>','<?php echo htmlspecialchars($inv['component_code'],ENT_QUOTES); ?>',<?php echo (int)$inv['hospital_id']; ?>,<?php echo (int)$inv['id']; ?>);return false;">Request</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card" id="request-form">
    <h2>New Transfer Request</h2>
    <form method="POST" action="" class="flex flex-wrap gap-12 items-end">
        <input type="hidden" name="csrf_token"   value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action"        value="request">
        <input type="hidden" id="req_inventory_id" name="inventory_id" value="">
        <div class="form-group mb-0 minw-120">
            <label for="req_blood_type">Blood Type *</label>
            <select id="req_blood_type" name="blood_type" required>
                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                    <option value="<?php echo $bt; ?>"><?php echo $bt; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0 minw-160">
            <label for="req_component">Component</label>
            <select id="req_component" name="component_code">
                <?php foreach ($components as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['code']); ?>"><?php echo htmlspecialchars($c['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0" style="width:80px">
            <label for="req_units">Units *</label>
            <input type="number" id="req_units" name="units_requested" value="1" min="1" max="999" required>
        </div>
        <div class="form-group mb-0" style="width:100px">
            <label for="req_temp">Temp (°C)</label>
            <input type="number" id="req_temp" name="storage_temp_c" value="4.0" step="0.1" min="-80" max="37">
        </div>
        <div class="form-group mb-0 minw-140">
            <label for="req_urgency">Urgency</label>
            <select id="req_urgency" name="urgency">
                <option value="routine">Routine</option>
                <option value="urgent">Urgent</option>
                <option value="critical">Critical</option>
            </select>
        </div>
        <div class="form-group mb-0 minw-200">
            <label for="req_supplier">Supplying Hospital *</label>
            <select id="req_supplier" name="supplying_hosp" required>
                <option value="">— Select —</option>
                <?php foreach ($networkHospitals as $h): ?>
                    <option value="<?php echo (int)$h['user_id']; ?>"><?php echo htmlspecialchars($h['hospital_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn mb-0">Submit Request</button>
    </form>
</div>

<div class="card">
    <h2>Transfer History</h2>
    <?php if ($transfers): ?>
    <div class="table-wrapper">
        <table aria-label="Transfer history">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Direction</th>
                    <th>Type / Component</th>
                    <th>Units</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transfers as $t):
                $isRequester = ((int)$t['requesting_hosp'] === $hospitalId);
                $isSupplier  = ((int)$t['supplying_hosp']  === $hospitalId);
                $s           = $statusLabels[$t['status']] ?? ['label' => ucfirst($t['status']), 'pill' => 'pill--neutral'];
            ?>
                <tr>
                    <td><?php echo (int)$t['id']; ?></td>
                    <td class="fs-85">
                        <?php if ($isRequester): ?>
                            <span class="text-muted">← from</span> <?php echo htmlspecialchars($t['sup_name']); ?>
                        <?php else: ?>
                            <span class="text-muted">→ to</span> <?php echo htmlspecialchars($t['req_name']); ?>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($t['blood_type']); ?></strong> / <?php echo htmlspecialchars($t['component_label'] ?: $t['component_code']); ?></td>
                    <td><?php echo (int)$t['units_requested'];
                        if ($t['units_confirmed'] !== null) echo ' → ' . (int)$t['units_confirmed'] . ' confirmed';
                    ?></td>
                    <td><?php echo ucfirst($t['urgency']); ?></td>
                    <td><span class="pill <?php echo $s['pill']; ?>"><?php echo $s['label']; ?></span></td>
                    <td>
                        <?php if ($isSupplier && $t['status'] === 'requested'): ?>
                        <form method="POST" action="" class="flex gap-6 items-center flex-wrap" style="font-size:0.8rem">
                            <input type="hidden" name="csrf_token"   value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="transfer_id" value="<?php echo (int)$t['id']; ?>">
                            <input type="number"  name="units_confirmed" value="<?php echo (int)$t['units_requested']; ?>" min="1" max="999" style="width:56px">
                            <input type="text"    name="note" placeholder="Note (optional)" style="width:120px">
                            <button name="action" value="accept" class="btn btn-small">Accept</button>
                            <button name="action" value="reject" class="btn btn-small btn-secondary">Reject</button>
                        </form>
                        <?php elseif ($isSupplier && $t['status'] === 'accepted'): ?>
                        <form method="POST" action="" style="display:inline">
                            <input type="hidden" name="csrf_token"   value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action"       value="dispatch">
                            <input type="hidden" name="transfer_id" value="<?php echo (int)$t['id']; ?>">
                            <button type="submit" class="btn btn-small">Mark Dispatched</button>
                        </form>
                        <?php elseif ($isRequester && $t['status'] === 'in_transit'): ?>
                        <form method="POST" action="" style="display:inline">
                            <input type="hidden" name="csrf_token"   value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action"       value="receive">
                            <input type="hidden" name="transfer_id" value="<?php echo (int)$t['id']; ?>">
                            <button type="submit" class="btn btn-small btn-secondary">Confirm Receipt</button>
                        </form>
                        <?php elseif ($isRequester && $t['status'] === 'requested'): ?>
                        <form method="POST" action="" style="display:inline">
                            <input type="hidden" name="csrf_token"   value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action"       value="cancel">
                            <input type="hidden" name="transfer_id" value="<?php echo (int)$t['id']; ?>">
                            <button type="submit" class="btn btn-small btn-secondary"
                                    onclick="return confirm('Cancel this transfer request?')">Cancel</button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted fs-85">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted">No transfers yet. Use the form above to request units from the network.</p>
    <?php endif; ?>
</div>

<script>
function prefillRequest(bt, comp, hospId, invId) {
    document.getElementById('req_blood_type').value  = bt;
    document.getElementById('req_component').value   = comp;
    document.getElementById('req_supplier').value    = hospId;
    document.getElementById('req_inventory_id').value = invId;
    document.getElementById('request-form').scrollIntoView({behavior:'smooth'});
}
</script>

<?php include '../includes/footer.php'; ?>
