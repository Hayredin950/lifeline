<?php
/**
 * Admin: hospital verification queue.
 * Lists pending submissions; admin can approve or reject with an optional note.
 * Approved hospitals get is_verified=1; rejected ones see the reason on their page.
 */
require_once '../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action   = $_POST['action']   ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);
    $note     = trim($_POST['note'] ?? '');

    if ($action === 'approve' && $targetId) {
        $pdo->prepare("
            UPDATE hospital_profiles
            SET verification_status = 'approved', is_verified = 1, verification_note = NULL
            WHERE user_id = ?
        ")->execute([$targetId]);
        auditLog($pdo, 'hospital_verified', 'hospital_profile', $targetId, null, ['admin' => $_SESSION['user_id']]);
        // Notify the hospital.
        $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, 'Hospital Verified', 'Your hospital has been verified. A verified badge now appears in listings.', 'system')
        ")->execute([$targetId]);
        setFlash('Hospital approved and marked verified.', 'success');
    }

    if ($action === 'reject' && $targetId) {
        $pdo->prepare("
            UPDATE hospital_profiles
            SET verification_status = 'rejected', is_verified = 0, verification_note = ?
            WHERE user_id = ?
        ")->execute([$note ?: null, $targetId]);
        auditLog($pdo, 'hospital_verification_rejected', 'hospital_profile', $targetId, null, ['note' => $note]);
        $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, 'Verification Not Approved', ?, 'system')
        ")->execute([$targetId, 'Your verification was not approved. Reason: ' . ($note ?: 'See admin for details.')]);
        setFlash('Hospital verification rejected.', 'info');
    }

    redirect(baseUrl() . '/admin/verify_hospitals.php');
}

$filter = $_GET['filter'] ?? 'pending';
$validFilters = ['pending', 'all', 'approved', 'rejected', 'unsubmitted'];
if (!in_array($filter, $validFilters, true)) $filter = 'pending';

if ($filter === 'all') {
    $where  = '1=1';
    $params = [];
} else {
    $where  = 'hp.verification_status = ?';
    $params = [$filter];
}

$stmt = $pdo->prepare("
    SELECT hp.user_id, hp.hospital_name, hp.city, hp.state, hp.country,
           hp.verification_status, hp.verification_doc, hp.verification_note,
           u.email, hp.created_at
    FROM hospital_profiles hp
    JOIN users u ON hp.user_id = u.id
    WHERE $where
    ORDER BY hp.created_at DESC
");
$stmt->execute($params);
$hospitals = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Hospital Verification Queue</h1>
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back</a>
    </div>

    <div class="flex gap-8 mb-20">
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'unsubmitted' => 'Unsubmitted', 'all' => 'All'] as $f => $label): ?>
        <a href="?filter=<?php echo $f; ?>"
           class="btn btn-small <?php echo $filter === $f ? '' : 'btn-secondary'; ?>">
            <?php echo $label; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$hospitals): ?>
        <p class="text-muted">No hospitals in this queue.</p>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Hospital</th><th>Location</th><th>Email</th>
                    <th>Status</th><th>Document</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($hospitals as $h): ?>
                <tr>
                    <td><?php echo htmlspecialchars($h['hospital_name']); ?></td>
                    <td class="fs-85"><?php echo htmlspecialchars($h['city'] . ', ' . $h['state']); ?></td>
                    <td class="fs-85"><?php echo htmlspecialchars($h['email']); ?></td>
                    <td>
                        <?php
                        $badges = [
                            'pending'      => 'badge-warning',
                            'approved'     => 'badge-success',
                            'rejected'     => 'badge-danger',
                            'unsubmitted'  => 'badge-secondary',
                        ];
                        $badgeClass = $badges[$h['verification_status']] ?? 'badge-secondary';
                        ?>
                        <span class="badge <?php echo $badgeClass; ?>">
                            <?php echo ucfirst($h['verification_status']); ?>
                        </span>
                    </td>
                    <td class="fs-85">
                        <?php if ($h['verification_doc']): ?>
                            <a href="<?php echo baseUrl(); ?>/admin/download_verification.php?user_id=<?php echo (int)$h['user_id']; ?>">
                                View doc
                            </a>
                        <?php else: ?>
                            <span class="text-muted">None</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($h['verification_status'] === 'pending'): ?>
                        <form method="POST" action="" class="flex gap-4 align-center" style="flex-wrap:wrap">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="user_id" value="<?php echo (int)$h['user_id']; ?>">
                            <button name="action" value="approve" type="submit" class="btn btn-small text-success-dark">Approve</button>
                            <input type="text" name="note" placeholder="Rejection reason (optional)" style="flex:1;min-width:160px">
                            <button name="action" value="reject" type="submit" class="btn btn-small bg-danger-dark"
                                    onclick="return confirm('Reject this hospital?')">Reject</button>
                        </form>
                        <?php elseif ($h['verification_status'] === 'approved'): ?>
                            <form method="POST" action="" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="user_id" value="<?php echo (int)$h['user_id']; ?>">
                                <input type="hidden" name="note" value="Re-review requested">
                                <button name="action" value="reject" type="submit" class="btn btn-small btn-secondary">Revoke</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
