<?php
require_once '../includes/functions.php';
requireDonor();

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    // Soft delete user (mark as deleted)
    $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = ?");
    $stmt->execute([$userId]);

    // Log the action
    auditLog($pdo, 'account_deleted', 'user', $userId);

    // Logout the user
    session_destroy();

    setFlash('Your account has been scheduled for deletion. We are sorry to see you go!', 'success');
    redirect(baseUrl() . '/index.php');
}

include '../includes/header.php';
?>

<div class="card max-w-lg mx-auto my-12">
    <h1 class="text-2xl font-bold mb-6">Delete Your Account</h1>

    <p class="mb-6 text-muted">
        This action will permanently delete your account and all associated data. This cannot be undone.
    </p>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <div class="mb-6">
            <label class="flex items-center gap-2">
                <input type="checkbox" required class="w-4 h-4">
                <span>I understand that this action is irreversible and all my data will be deleted.</span>
            </label>
        </div>

        <div class="flex gap-4">
            <a href="<?php echo baseUrl(); ?>/donor/dashboard.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn bg-danger-dark">Delete My Account</button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
