<?php
require_once '../includes/functions.php';
requireAdmin();

// Handle approve / reject POST actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($id && in_array($action, ['approve', 'reject'], true)) {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE testimonials SET is_approved = 1 WHERE id = ?")->execute([$id]);
            setFlash('Testimonial approved.', 'success');
        } else {
            $pdo->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$id]);
            setFlash('Testimonial rejected and removed.', 'success');
        }
    }

    redirect(baseUrl() . '/admin/testimonials.php');
}

// Pagination.
$pagination = getPaginationParams(20);
$page    = $pagination['page'];
$perPage = $pagination['per_page'];
$offset  = $pagination['offset'];

$filter = $_GET['filter'] ?? 'pending';
$filterSql = match($filter) {
    'approved' => 'WHERE t.is_approved = 1',
    'all'      => '',
    default    => 'WHERE t.is_approved = 0',
};

$total      = $pdo->query("SELECT COUNT(*) FROM testimonials t $filterSql")->fetchColumn();
$totalPages = (int)ceil($total / $perPage);

$stmt = $pdo->prepare("
    SELECT t.*, dp.full_name AS donor_name, u.email
    FROM testimonials t
    JOIN donor_profiles dp ON t.donor_id = dp.user_id
    JOIN users u ON t.donor_id = u.id
    $filterSql
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$rows = $stmt->fetchAll();

// Tab counts.
$pendingCount  = (int)$pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_approved = 0")->fetchColumn();
$approvedCount = (int)$pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_approved = 1")->fetchColumn();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Testimonials</h1>
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="flex gap-8 mb-16">
        <?php
        $tabs = ['pending' => "Pending ($pendingCount)", 'approved' => "Approved ($approvedCount)", 'all' => 'All'];
        foreach ($tabs as $key => $label):
            $active = ($filter === $key) ? 'btn' : 'btn btn-secondary';
        ?>
            <a href="?filter=<?php echo $key; ?>" class="<?php echo $active; ?> btn-small"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
        <p class="text-muted">No testimonials in this category.</p>
    <?php else: ?>
    <div class="flex flex-col gap-16">
        <?php foreach ($rows as $t): ?>
        <div class="card" style="--card-border-color: <?php echo $t['is_approved'] ? 'var(--color-success-dark)' : 'var(--color-amber)'; ?>">
            <div class="flex items-center gap-10 mb-8">
                <strong><?php echo htmlspecialchars($t['donor_name']); ?></strong>
                <span class="text-muted fs-85"><?php echo htmlspecialchars($t['email']); ?></span>
                <span class="ml-auto fs-85 text-muted"><?php echo date('M j, Y', strtotime($t['created_at'])); ?></span>
            </div>

            <?php if ($t['recipient_name']): ?>
                <p class="fs-90 text-muted mb-6">Recipient: <em><?php echo htmlspecialchars($t['recipient_name']); ?></em></p>
            <?php endif; ?>

            <div class="mb-8">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="<?php echo $i <= $t['rating'] ? 'text-warning' : 'text-muted'; ?>">★</span>
                <?php endfor; ?>
            </div>

            <p><?php echo nl2br(htmlspecialchars($t['story'])); ?></p>

            <?php if (!$t['is_approved']): ?>
            <form method="POST" action="" class="flex gap-8 mt-12">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                <button type="submit" name="action" value="approve" class="btn btn-small">Approve</button>
                <button type="submit" name="action" value="reject" class="btn btn-small bg-danger-dark"
                        onclick="return confirm('Reject and permanently delete this testimonial?')">Reject</button>
            </form>
            <?php else: ?>
                <span class="pill pill--success mt-12 d-inline-block">Published</span>
                <form method="POST" action="" class="d-inline ml-8">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                    <button type="submit" name="action" value="reject" class="btn btn-small bg-danger-dark"
                            onclick="return confirm('Remove this published testimonial?')">Remove</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php echo renderPagination($page, $totalPages, $perPage, baseUrl() . '/admin/testimonials.php?filter=' . urlencode($filter)); ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
