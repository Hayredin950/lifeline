<?php
require_once '../includes/functions.php';
requireAdmin();

// Pagination
$pagination = getPaginationParams(25);
$page = $pagination['page'];
$perPage = $pagination['per_page'];
$offset = $pagination['offset'];

// Get total count
$totalCount = $pdo->query("SELECT COUNT(*) FROM donor_profiles")->fetchColumn();
$totalPages = (int)ceil($totalCount / $perPage);

// Get paginated donors
$stmt = $pdo->prepare("
    SELECT dp.*, u.email, u.is_active, u.id as user_id
    FROM donor_profiles dp
    JOIN users u ON dp.user_id = u.id
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
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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
                <td><?php echo $d['is_active'] ? '<span style="color:#15803d;font-weight:600;">Active</span>' : '<span style="color:#991b1b;font-weight:600;">Inactive</span>'; ?></td>
                <td>
                    <a href="<?php echo baseUrl(); ?>/admin/edit_record.php?type=donor&id=<?php echo (int)$d['user_id']; ?>" class="btn btn-small">Edit</a>
                    <a href="<?php echo baseUrl(); ?>/admin/delete_record.php?type=donor&id=<?php echo (int)$d['user_id']; ?>" class="btn btn-small" style="background:#991b1b;">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php echo renderPagination($page, $totalPages, $perPage, baseUrl() . '/admin/manage_donors.php'); ?>
</div>

<?php include '../includes/footer.php'; ?>
