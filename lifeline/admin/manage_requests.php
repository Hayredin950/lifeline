<?php
require_once '../includes/functions.php';
requireAdmin();

// Pagination
$pagination = getPaginationParams(25);
$page = $pagination['page'];
$perPage = $pagination['per_page'];
$offset = $pagination['offset'];

// Get total count
$totalCount = $pdo->query("SELECT COUNT(*) FROM blood_requests")->fetchColumn();
$totalPages = (int)ceil($totalCount / $perPage);

// Get paginated requests
$stmt = $pdo->prepare("
    SELECT br.*,
           COALESCE(hp.hospital_name, br.hospital_address) AS hospital_name,
           (br.notes LIKE 'EMERGENCY SOS REQUEST%') AS is_sos
    FROM blood_requests br
    LEFT JOIN hospital_profiles hp ON br.hospital_id = hp.user_id
    ORDER BY (br.notes LIKE 'EMERGENCY SOS REQUEST%') DESC,
             br.urgency = 'critical' DESC,
             br.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$requests = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Manage Blood Requests</h1>
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Hospital</th>
                <th>Patient Type</th>
                <th>Units</th>
                <th>Urgency</th>
                <th>Status</th>
                <th>Required By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $r): ?>
            <tr <?php echo $r['is_sos'] ? 'style="background:rgba(220,38,38,.06)"' : ''; ?>>
                <td>#<?php echo (int)$r['id']; ?></td>
                <td>
                    <?php if ($r['is_sos']): ?>
                        <span class="badge badge-danger" style="font-size:.68rem;padding:2px 6px;margin-right:4px">&#9888; SOS</span>
                    <?php endif; ?>
                    <?php echo $r['hospital_name'] ? htmlspecialchars($r['hospital_name']) : '<span class="text-crimson fw-600">Emergency</span>'; ?>
                </td>
                <td><?php echo htmlspecialchars($r['patient_blood_type']); ?></td>
                <td><?php echo (int)$r['units_needed']; ?></td>
                <td><?php echo ucfirst($r['urgency']); ?></td>
                <td><?php echo ucfirst($r['status']); ?></td>
                <td><?php echo $r['required_date'] ? htmlspecialchars($r['required_date']) : 'ASAP'; ?></td>
                <td>
                    <a href="<?php echo baseUrl(); ?>/admin/edit_record.php?type=request&id=<?php echo (int)$r['id']; ?>" class="btn btn-small">Edit</a>
                    <a href="<?php echo baseUrl(); ?>/admin/delete_record.php?type=request&id=<?php echo (int)$r['id']; ?>" class="btn btn-small bg-danger-dark">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php echo renderPagination($page, $totalPages, $perPage, baseUrl() . '/admin/manage_requests.php'); ?>
</div>

<?php include '../includes/footer.php'; ?>
