<?php
require_once '../includes/functions.php';
requireAdmin();

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!in_array($type, ['donor', 'hospital', 'request']) || !$id) {
    setFlash('Invalid record type or ID.', 'danger');
    redirect(baseUrl() . '/admin/dashboard.php');
}

$label = '';
if ($type === 'donor') {
    $stmt = $pdo->prepare("SELECT full_name as label FROM donor_profiles WHERE user_id = ?");
    $stmt->execute([$id]);
    $label = $stmt->fetchColumn() ?: 'Donor #'.$id;
} elseif ($type === 'hospital') {
    $stmt = $pdo->prepare("SELECT hospital_name as label FROM hospital_profiles WHERE user_id = ?");
    $stmt->execute([$id]);
    $label = $stmt->fetchColumn() ?: 'Hospital #'.$id;
} elseif ($type === 'request') {
    $label = 'Blood Request #'.$id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    if ($type === 'donor' || $type === 'hospital') {
        // Deleting user cascades to profile and matches
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        auditLog($pdo, 'delete', $type, $id, ['label' => $label]);
        setFlash(ucfirst($type) . ' record deleted successfully.', 'success');
        redirect(baseUrl() . '/admin/manage_' . $type . 's.php');
    } elseif ($type === 'request') {
        $stmt = $pdo->prepare("DELETE FROM blood_requests WHERE id = ?");
        $stmt->execute([$id]);
        auditLog($pdo, 'delete', 'request', $id, ['label' => $label]);
        setFlash('Blood request deleted successfully.', 'success');
        redirect(baseUrl() . '/admin/manage_requests.php');
    }
}

include '../includes/header.php';
?>

<div class="card" style="max-width: 480px; margin: 60px auto; text-align:center;">
    <h1>Confirm Deletion</h1>
    <p>Are you sure you want to permanently delete <strong><?php echo htmlspecialchars($label); ?></strong>?</p>
    <p style="color:#991b1b; font-weight:600;">This action cannot be undone.</p>

    <form method="POST" action="" style="margin-top: 20px;">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <div style="display:flex; gap: 12px; justify-content:center;">
            <a href="<?php echo baseUrl(); ?>/admin/<?php echo $type==='request'?'manage_requests':('manage_'.$type.'s'); ?>.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn" style="background:#991b1b;">Delete</button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
