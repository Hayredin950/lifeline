<?php
/**
 * 2FA challenge page — shown after successful password authentication
 * when the account has TOTP enabled.
 *
 * Session state:
 *   $_SESSION['2fa_pending_user_id'] — set by login.php on password-OK
 *   $_SESSION['2fa_pending_role']    — the user's role for redirect
 */
require_once '../includes/functions.php';
require_once '../includes/totp.php';

// If there is no pending 2FA session, bounce to login.
$pendingId = $_SESSION['2fa_pending_user_id'] ?? null;
if (!$pendingId) {
    redirect(baseUrl() . '/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $stmt = $pdo->prepare("SELECT totp_secret, totp_backup_codes FROM users WHERE id = ? AND totp_enabled = 1");
    $stmt->execute([$pendingId]);
    $row = $stmt->fetch();
    if (!$row) {
        // TOTP no longer enabled for this account — complete login normally.
        finalizeLogin($pendingId);
        exit;
    }

    $code = trim($_POST['code'] ?? '');

    // Try TOTP first.
    if (Totp::verify($row['totp_secret'], $code)) {
        finalizeLogin($pendingId);
        exit;
    }

    // Try backup codes.
    $hashes = json_decode((string)$row['totp_backup_codes'], true);
    if (is_array($hashes) && Totp::verifyBackupCode($code, $hashes)) {
        // Consume the backup code.
        $remaining = array_filter($hashes, fn($h) => !hash_equals($h, Totp::hashBackupCode($code)));
        $pdo->prepare("UPDATE users SET totp_backup_codes=? WHERE id=?")->execute([
            json_encode(array_values($remaining)),
            $pendingId,
        ]);
        finalizeLogin($pendingId);
        exit;
    }

    setFlash('Invalid code. Please try again or use a backup code.', 'danger');
    redirect(baseUrl() . '/auth/verify_2fa.php');
}

function finalizeLogin(int $userId): void
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, email, role, must_change_password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_role']);

    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    auditLog($pdo, 'login_2fa_ok', 'user', $user['id']);

    if ($user['must_change_password']) {
        redirect(baseUrl() . '/change_password.php');
    }

    $dashboards = [
        'admin'    => '/admin/dashboard.php',
        'hospital' => '/hospital/dashboard.php',
        'donor'    => '/donor/dashboard.php',
    ];
    redirect(baseUrl() . ($dashboards[$user['role']] ?? '/'));
}

include '../includes/header.php';
?>

<div class="card" style="max-width:400px;margin:3rem auto">
    <div class="card-header">
        <h1>Two-Factor Verification</h1>
    </div>
    <p class="mb-20 text-muted">
        Enter the 6-digit code from your authenticator app,
        or one of your backup codes.
    </p>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <div class="form-group">
            <label for="code">Authentication Code</label>
            <input type="text" id="code" name="code" inputmode="numeric"
                   maxlength="20" required autofocus autocomplete="one-time-code"
                   placeholder="123456 or backup code">
        </div>
        <button type="submit" class="btn w-full">Verify</button>
        <p class="fs-85 text-muted mt-12 text-center">
            <a href="<?php echo baseUrl(); ?>/login.php">← Back to login</a>
        </p>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
