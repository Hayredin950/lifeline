<?php
require_once '../includes/functions.php';
requireAdmin();

// Pagination
$pagination = getPaginationParams(25);
$page = $pagination['page'];
$perPage = $pagination['per_page'];
$offset = $pagination['offset'];

$showDeleted = isset($_GET['show_deleted']);
$deletedFilter = $showDeleted ? '' : 'AND u.deleted_at IS NULL';

// Get total count
$totalCount = $pdo->query("SELECT COUNT(*) FROM donor_profiles dp JOIN users u ON dp.user_id = u.id WHERE 1=1 $deletedFilter")->fetchColumn();
$totalPages  = (int)ceil($totalCount / $perPage);

$stmt = $pdo->prepare("
    SELECT dp.*, u.email, u.is_active, u.deleted_at, u.id as user_id
    FROM donor_profiles dp
    JOIN users u ON dp.user_id = u.id
    WHERE 1=1 $deletedFilter
    ORDER BY dp.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$donors = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Manage Donors</h1>
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
                <th>Name</th>
                <th>Email</th>
                <th>Blood Type</th>
                <th>City</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($donors as $d): ?>
            <tr>
                <td><?php echo (int)$d['id']; ?></td>
                <td><?php echo htmlspecialchars($d['full_name']); ?></td>
                <td><?php echo htmlspecialchars($d['email']); ?></td>
                <td><strong><?php echo htmlspecialchars($d['blood_type']); ?></strong></td>
                <td><?php echo htmlspecialchars($d['city']); ?></td>
                <td><?php echo htmlspecialchars($d['phone']); ?></td>
                <td>
                    <?php if ($d['deleted_at']): ?>
                        <span class="text-muted fw-600">Deleted <?php echo date('M j Y', strtotime($d['deleted_at'])); ?></span>
                    <?php elseif ($d['is_active']): ?>
                        <span class="text-success-dark fw-600">Active</span>
                    <?php else: ?>
                        <span class="text-danger-dark fw-600">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo baseUrl(); ?>/admin/edit_record.php?type=donor&id=<?php echo (int)$d['user_id']; ?>" class="btn btn-small">Edit</a>
                    <a href="<?php echo baseUrl(); ?>/admin/delete_record.php?type=donor&id=<?php echo (int)$d['user_id']; ?>" class="btn btn-small bg-danger-dark">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php echo renderPagination($page, $totalPages, $perPage, baseUrl() . '/admin/manage_donors.php'); ?>
</div>

<?php include '../includes/footer.php'; ?>
