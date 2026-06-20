<?php
/**
 * DSAR erasure request — self-service account deletion with PII anonymization.
 * Requires password confirmation. Soft-deletes the account + zeroes PII fields
 * so no personal data is retained beyond what audit/safety requires.
 * Doc 07 §6 / FR-49
 */
require_once '../includes/functions.php';
requireDonor();

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT email, password FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $password = $_POST['password'] ?? '';
    if (!$password || !password_verify($password, $user['password'])) {
        setFlash('Incorrect password. Erasure request cancelled.', 'danger');
        redirect(baseUrl() . '/donor/request_erasure.php');
    }

    $pdo->beginTransaction();
    try {
        // Anonymize PII in donor profile.
        $pdo->prepare("
            UPDATE donor_profiles SET
                full_name       = 'DELETED',
                phone           = NULL,
                address         = NULL,
                city            = NULL,
                state           = NULL,
                country         = NULL,
                date_of_birth   = NULL,
                gender          = NULL,
                latitude        = NULL,
                longitude       = NULL,
                profile_pic     = NULL,
                email_notif_prefs = NULL
            WHERE user_id = ?
        ")->execute([$userId]);

        // Anonymize email in users and soft-delete.
        $placeholder = 'deleted_' . $userId . '@lifeline.invalid';
        $pdo->prepare("
            UPDATE users
            SET email       = ?,
                is_active   = 0,
                deleted_at  = NOW(),
                unsubscribe_token = NULL
            WHERE id = ?
        ")->execute([$placeholder, $userId]);

        auditLog($pdo, 'dsar_erasure', 'user', $userId, ['email' => $user['email']], ['anonymized' => true]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        setFlash('An error occurred processing your request. Please try again.', 'danger');
        redirect(baseUrl() . '/donor/request_erasure.php');
    }

    // Destroy session after erasure.
    session_unset();
    session_destroy();

    // Show confirmation on a public page.
    include '../includes/header.php';
    ?>
    <div class="card maxw-480 mx-auto my-40 text-center">
        <h1>Account Erased</h1>
        <p class="text-muted">Your personal information has been anonymized and your account deactivated.</p>
        <p class="text-muted">Any remaining data will be purged after the retention period.</p>
        <p class="mt-20"><a href="<?php echo baseUrl(); ?>/index.php" class="btn btn-secondary">Home</a></p>
    </div>
    <?php
    include '../includes/footer.php';
    exit;
}

include '../includes/header.php';
?>

<div class="card maxw-560 mx-auto my-30">
    <h1 class="text-danger-dark">Delete My Account</h1>
    <p class="text-muted mb-16">
        This will <strong>permanently anonymize</strong> your personal data and deactivate your account.
        Your donation records are retained for regulatory purposes but your name, contact details,
        and other identifying information will be removed immediately.
    </p>
    <div class="alert alert-warning mb-20">
        This action cannot be undone. Download your data first if you want a copy.
        <a href="<?php echo baseUrl(); ?>/donor/data_export.php" class="fw-600">Download My Data</a>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <div class="form-group">
            <label for="password">Confirm with your password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn w-full bg-danger-dark">Erase My Account</button>
        <p class="text-center mt-16">
            <a href="<?php echo baseUrl(); ?>/donor/dashboard.php">&larr; Back to Dashboard</a>
        </p>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
