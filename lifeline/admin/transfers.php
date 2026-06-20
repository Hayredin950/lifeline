<?php
/**
 * Admin: oversight of all inter-facility cold-chain transfers.
 */
require_once '../includes/functions.php';
requireAdmin();

$status  = $_GET['status'] ?? '';
$validStatuses = ['requested', 'accepted', 'in_transit', 'received', 'rejected', 'cancelled'];
if (!in_array($status, $validStatuses, true)) $status = '';

$sql = "
    SELECT t.*,
           req.hospital_name AS req_name,
           sup.hospital_name AS sup_name,
           dc.label          AS component_label
    FROM blood_unit_transfers t
    JOIN hospital_profiles req ON req.user_id = t.requesting_hosp
    JOIN hospital_profiles sup ON sup.user_id = t.supplying_hosp
    LEFT JOIN donation_components dc ON dc.code = t.component_code
";
$params = [];
if ($status) {
    $sql .= " WHERE t.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY t.created_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transfers = $stmt->fetchAll();

$statusCounts = $pdo->query("SELECT status, COUNT(*) AS cnt FROM blood_unit_transfers GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$statusLabels = [
    'requested'  => 'Requested',
    'accepted'   => 'Accepted',
    'in_transit' => 'In Transit',
    'received'   => 'Received',
    'rejected'   => 'Rejected',
    'cancelled'  => 'Cancelled',
];
$pillMap = [
    'requested'  => 'pill--info',
    'accepted'   => 'pill--warning',
    'in_transit' => 'pill--info',
    'received'   => 'pill--success',
    'rejected'   => 'pill--danger',
    'cancelled'  => 'pill--neutral',
];

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Inter-Facility Transfers</h1>
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back</a>
    </div>
    <p class="text-muted fs-90 mb-12">Network-wide view of cold-chain transfer requests.</p>

    <div class="flex gap-8 flex-wrap mb-16">
        <a href="?status=" class="btn btn-small <?php echo $status === '' ? '' : 'btn-secondary'; ?>">
            All (<?php echo array_sum($statusCounts); ?>)
        </a>
        <?php foreach ($validStatuses as $s): ?>
        <a href="?status=<?php echo urlencode($s); ?>"
           class="btn btn-small <?php echo $status === $s ? '' : 'btn-secondary'; ?>">
            <?php echo htmlspecialchars($statusLabels[$s] ?? $s); ?>
            (<?php echo (int)($statusCounts[$s] ?? 0); ?>)
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($transfers): ?>
    <div class="table-wrapper">
        <table aria-label="All inter-facility transfers">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Requesting</th>
                    <th>Supplying</th>
                    <th>Type / Component</th>
                    <th>Units</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Dispatched</th>
                    <th>Received</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transfers as $t): ?>
                <tr>
                    <td><?php echo (int)$t['id']; ?></td>
                    <td class="fs-85"><?php echo htmlspecialchars($t['req_name']); ?></td>
                    <td class="fs-85"><?php echo htmlspecialchars($t['sup_name']); ?></td>
                    <td><strong><?php echo htmlspecialchars($t['blood_type']); ?></strong> / <?php echo htmlspecialchars($t['component_label'] ?: $t['component_code']); ?></td>
                    <td><?php echo (int)$t['units_requested'];
                        if ($t['units_confirmed'] !== null) echo ' (→ ' . (int)$t['units_confirmed'] . ')';
                    ?></td>
                    <td><?php echo ucfirst($t['urgency']); ?></td>
                    <td><span class="pill <?php echo $pillMap[$t['status']] ?? 'pill--neutral'; ?>"><?php echo $statusLabels[$t['status']] ?? ucfirst($t['status']); ?></span></td>
                    <td class="fs-80 text-muted"><?php echo $t['dispatched_at'] ? htmlspecialchars(substr($t['dispatched_at'], 0, 10)) : '—'; ?></td>
                    <td class="fs-80 text-muted"><?php echo $t['received_at']   ? htmlspecialchars(substr($t['received_at'],   0, 10)) : '—'; ?></td>
                    <td class="fs-80 text-muted"><?php echo $t['status_note'] ? htmlspecialchars($t['status_note']) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted">No transfers found<?php echo $status ? ' with status "' . htmlspecialchars($statusLabels[$status] ?? $status) . '"' : ''; ?>.</p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
