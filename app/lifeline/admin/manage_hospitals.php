<?php
require_once '../includes/functions.php';
requireAdmin();

// Pagination
$pagination = getPaginationParams(25);
$page = $pagination['page'];
$perPage = $pagination['per_page'];
$offset = $pagination['offset'];

// Get total count
$totalCount = $pdo->query("SELECT COUNT(*) FROM hospital_profiles")->fetchColumn();
$totalPages = (int)ceil($totalCount / $perPage);

// Get paginated hospitals
$stmt = $pdo->prepare("
    SELECT hp.*, u.email, u.is_active, u.id as user_id
    FROM hospital_profiles hp
    JOIN users u ON hp.user_id = u.id
    ORDER BY hp.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$hospitals = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Manage Hospitals</h1>
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Hospital Name</th>
                <th>Email</th>
                <th>City</th>
                <th>License</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($hospitals as $h): ?>
            <tr>
                <td><?php echo (int)$h['id']; ?></td>
                <td><?php echo htmlspecialchars($h['hospital_name']); ?></td>
                <td><?php echo htmlspecialchars($h['email']); ?></td>
                <td><?php echo htmlspecialchars($h['city']); ?></td>
                <td><?php echo htmlspecialchars($h['license_number']); ?></td>
                <td><?php echo $h['is_active'] ? '<span style="color:#15803d;font-weight:600;">Active</span>' : '<span style="color:#991b1b;font-weight:600;">Inactive</span>'; ?></td>
                <td>
                    <a href="<?php echo baseUrl(); ?>/admin/edit_record.php?type=hospital&id=<?php echo (int)$h['user_id']; ?>" class="btn btn-small">Edit</a>
                    <a href="<?php echo baseUrl(); ?>/admin/delete_record.php?type=hospital&id=<?php echo (int)$h['user_id']; ?>" class="btn btn-small" style="background:#991b1b;">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php echo renderPagination($page, $totalPages, $perPage, baseUrl() . '/admin/manage_hospitals.php'); ?>
</div>

<?php include '../includes/footer.php'; ?>
