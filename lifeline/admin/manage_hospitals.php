<?php
require_once '../includes/functions.php';
requireAdmin();

// Pagination
$pagination = getPaginationParams(25);
$page = $pagination['page'];
$perPage = $pagination['per_page'];
$offset = $pagination['offset'];

$showDeleted  = isset($_GET['show_deleted']);
$deletedFilter = $showDeleted ? '' : 'AND u.deleted_at IS NULL';

$totalCount = $pdo->query("SELECT COUNT(*) FROM hospital_profiles hp JOIN users u ON hp.user_id = u.id WHERE 1=1 $deletedFilter")->fetchColumn();
$totalPages = (int)ceil($totalCount / $perPage);

$stmt = $pdo->prepare("
    SELECT hp.*, u.email, u.is_active, u.deleted_at, u.id as user_id
    FROM hospital_profiles hp
    JOIN users u ON hp.user_id = u.id
    WHERE 1=1 $deletedFilter
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
        <div class="flex gap-8">
            <?php if ($showDeleted): ?>
                <a href="?page=<?php echo $page; ?>" class="btn btn-secondary btn-small">Hide Deleted</a>
            <?php else: ?>
                <a href="?show_deleted=1&page=<?php echo $page; ?>" class="btn btn-secondary btn-small">Show Deleted</a>
            <?php endif; ?>
            <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
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
                <td>
                    <?php if ($h['deleted_at']): ?>
                        <span class="text-muted fw-600">Deleted <?php echo date('M j Y', strtotime($h['deleted_at'])); ?></span>
                    <?php elseif ($h['is_active']): ?>
                        <span class="text-success-dark fw-600">Active</span>
                    <?php else: ?>
                        <span class="text-danger-dark fw-600">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo baseUrl(); ?>/admin/edit_record.php?type=hospital&id=<?php echo (int)$h['user_id']; ?>" class="btn btn-small">Edit</a>
                    <a href="<?php echo baseUrl(); ?>/admin/delete_record.php?type=hospital&id=<?php echo (int)$h['user_id']; ?>" class="btn btn-small bg-danger-dark">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php echo renderPagination($page, $totalPages, $perPage, baseUrl() . '/admin/manage_hospitals.php'); ?>
</div>

<?php include '../includes/footer.php'; ?>
