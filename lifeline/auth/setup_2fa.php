<?php
/**
 * 2FA setup page — available to hospital and admin accounts.
 * Flow:
 *   GET  → generate (or reuse pending) secret, show QR + manual entry + verify form
 *   POST confirm → verify entered code against pending secret → enable
 *   POST disable → verify password → disable + wipe secret
 */
require_once '../includes/functions.php';
require_once '../includes/totp.php';

requireLogin();
$user = getCurrentUser($pdo);
if (!in_array($user['role'], ['hospital', 'admin'], true)) {
    setFlash('2FA is only available for hospital and admin accounts.', 'danger');
    redirect(baseUrl() . '/');
}

$is2FAEnabled = (bool)($user['totp_enabled'] ?? false);

// ── POST: disable ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disable') {
    validateCsrf();
    if (!password_verify($_POST['password'] ?? '', $user['password'])) {
        setFlash('Incorrect password. 2FA not disabled.', 'danger');
        redirect(baseUrl() . '/auth/setup_2fa.php');
    }
    $pdo->prepare("UPDATE users SET totp_enabled=0, totp_secret=NULL, totp_backup_codes=NULL WHERE id=?")->execute([$user['id']]);
    auditLog($pdo, '2fa_disabled', 'user', $user['id']);
    setFlash('Two-factor authentication has been disabled.', 'success');
    redirect(baseUrl() . '/auth/setup_2fa.php');
}

// ── POST: confirm setup ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    validateCsrf();
    $pendingSecret = $_SESSION['totp_pending_secret'] ?? '';
    if (!$pendingSecret) {
        setFlash('Session expired. Please restart 2FA setup.', 'danger');
        redirect(baseUrl() . '/auth/setup_2fa.php');
    }
    if (!Totp::verify($pendingSecret, $_POST['code'] ?? '')) {
        setFlash('Invalid code. Make sure your authenticator app is synced and try again.', 'danger');
        redirect(baseUrl() . '/auth/setup_2fa.php');
    }

    // Generate backup codes.
    $plain   = Totp::generateBackupCodes(8);
    $hashed  = array_map([Totp::class, 'hashBackupCode'], $plain);

    $pdo->prepare("UPDATE users SET totp_secret=?, totp_enabled=1, totp_backup_codes=? WHERE id=?")->execute([
        $pendingSecret,
        json_encode($hashed),
        $user['id'],
    ]);
    unset($_SESSION['totp_pending_secret']);
    auditLog($pdo, '2fa_enabled', 'user', $user['id']);

    // Store backup codes in session so we can show them once.
    $_SESSION['totp_new_backup_codes'] = $plain;
    setFlash('2FA enabled successfully!', 'success');
    redirect(baseUrl() . '/auth/setup_2fa.php');
}

// ── GET / first load ─────────────────────────────────────────────────────────
// If we just enabled, show backup codes once then clear.
$newBackupCodes = null;
if (isset($_SESSION['totp_new_backup_codes'])) {
    $newBackupCodes = $_SESSION['totp_new_backup_codes'];
    unset($_SESSION['totp_new_backup_codes']);
}

// Pending secret (regenerate if not yet in session or already enabled).
$secret = null;
$qrUri  = null;
if (!$is2FAEnabled) {
    if (empty($_SESSION['totp_pending_secret'])) {
        $_SESSION['totp_pending_secret'] = Totp::generateSecret();
    }
    $secret = $_SESSION['totp_pending_secret'];
    $label  = $user['email'];
    $qrUri  = Totp::otpAuthUri($secret, $label);
}

include '../includes/header.php';
?>

<div class="card" style="max-width:560px;margin:2rem auto">
    <div class="card-header">
        <h1>Two-Factor Authentication</h1>
        <a href="<?php echo baseUrl(); ?>/<?php echo $user['role']; ?>/dashboard.php"
           class="btn btn-secondary">Back</a>
    </div>

    <?php if ($newBackupCodes): ?>
    <div class="alert alert-success mb-20" role="alert">
        <strong>Save these backup codes now</strong> — they will not be shown again.
        Each code can be used once if you lose access to your authenticator.
        <div class="totp-backup-grid mt-12">
        <?php foreach ($newBackupCodes as $c): ?>
            <code><?php echo htmlspecialchars($c); ?></code>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($is2FAEnabled): ?>
        <p class="text-success-dark fw-600 mb-16">Two-factor authentication is <strong>active</strong> on your account.</p>

        <h3 class="mb-8">Disable 2FA</h3>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="disable">
            <div class="form-group">
                <label for="password">Confirm your password to disable 2FA</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn bg-danger-dark"
                    onclick="return confirm('Are you sure you want to disable 2FA?')">
                Disable 2FA
            </button>
        </form>

    <?php else: ?>
        <p class="mb-16">
            Scan the QR code with your authenticator app (Google Authenticator, Authy, etc.),
            then enter the 6-digit code to confirm.
        </p>

        <?php
        // Render QR as an inline <img> using a data: URI — no external service.
        // We use a basic text-based QR using the otpauth URI printed as a link
        // as fallback; production should swap in a proper QR library.
        ?>
        <div class="mb-16 text-center">
            <p class="fs-85 text-muted mb-8">
                Can't scan? <strong>Enter manually:</strong>
            </p>
            <code class="block fs-90 p-8 bg-light rounded"><?php echo htmlspecialchars($secret); ?></code>
            <p class="fs-80 text-muted mt-4">
                Or open this link on your phone:
                <a href="<?php echo htmlspecialchars($qrUri); ?>">otpauth link</a>
            </p>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="confirm">
            <div class="form-group">
                <label for="code">6-digit code from your app</label>
                <input type="text" id="code" name="code" inputmode="numeric"
                       pattern="\d{6}" maxlength="6" required autocomplete="one-time-code"
                       placeholder="123456">
            </div>
            <button type="submit" class="btn">Enable 2FA</button>
        </form>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
