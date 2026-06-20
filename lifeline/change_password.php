<?php
/**
 * Forced / self-service password change (Doc 07).
 * Reached automatically right after login when `must_change_password` is set
 * (e.g. the seeded admin's one-time password), and confined to here until the
 * credential is rotated. Also usable by any logged-in user to change a password.
 */
require_once 'includes/functions.php';
requireAuth();

$userId = (int)$_SESSION['user_id'];
$forced = !empty($_SESSION['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = [];

    // Verify the current password.
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($current, $hash)) {
        $errors[] = 'Your current password is incorrect.';
    }

    // Strength + confirmation.
    $errors = array_merge($errors, validatePassword($new));
    if ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    }
    // Don't allow reusing the same password (important when rotating off a default).
    if ($hash && password_verify($new, $hash)) {
        $errors[] = 'Please choose a password different from your current one.';
    }

    if (empty($errors)) {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?")
            ->execute([$newHash, $userId]);
        $_SESSION['must_change_password'] = 0;
        auditLog($pdo, 'password_changed', 'user', $userId);

        setFlash('Your password has been updated.', 'success');
        $role = $_SESSION['role'] ?? '';
        if ($role === 'admin') {
            redirect(baseUrl() . '/admin/dashboard.php');
        } elseif ($role === 'hospital') {
            redirect(baseUrl() . '/hospital/dashboard.php');
        } else {
            redirect(baseUrl() . '/donor/dashboard.php');
        }
    } else {
        setFlash(implode('<br>', $errors), 'danger');
        redirect(baseUrl() . '/change_password.php');
    }
}

$pageTitle = 'Change Password';
include 'includes/header.php';
?>

<div class="card maxw-480 mx-auto my-40">
    <h1 class="text-center">Change Password</h1>
    <?php if ($forced): ?>
        <p class="text-center text-secondary">
            You're using a temporary password. Please set a new one to continue.
        </p>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required
                   placeholder="<?php echo $forced ? 'Your one-time password' : 'Current password'; ?>">
        </div>
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" minlength="8" required
                   placeholder="Min 8 characters">
            <small class="field-hint">
                Must contain: 8+ chars, uppercase, lowercase, number, special character.
            </small>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required
                   placeholder="Re-enter new password">
        </div>
        <button type="submit" class="btn w-full">Update Password</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
