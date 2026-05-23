<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(baseUrl() . '/index.php');
}

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

// Validate token
$validToken = false;
$email = '';

if ($token) {
    $stmt = $pdo->prepare("
        SELECT email, expires_at, used_at 
        FROM password_resets 
        WHERE token = ? 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        $error = 'Invalid or expired reset link.';
    } elseif ($reset['used_at']) {
        $error = 'This reset link has already been used.';
    } elseif (strtotime($reset['expires_at']) < time()) {
        $error = 'This reset link has expired. Please request a new one.';
    } else {
        $validToken = true;
        $email = $reset['email'];
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    validateCsrf();
    
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    // Validate password
    $passwordErrors = validatePassword($password);
    if (!empty($passwordErrors)) {
        $error = implode('<br>', $passwordErrors);
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Update password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hash, $email]);
        
        // Mark token as used
        $stmt = $pdo->prepare("
            UPDATE password_resets 
            SET used_at = NOW() 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        
        $success = true;
        setFlash('Your password has been reset successfully. Please login with your new password.', 'success');
    }
}

include 'includes/header.php';
?>

<div class="card" style="max-width: 480px; margin: 40px auto;">
    <h1 class="text-center">Set New Password</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <p>Your password has been reset successfully!</p>
        </div>
        <p class="text-center">
            <a href="<?php echo baseUrl(); ?>/login.php" class="btn">Go to Login</a>
        </p>
    <?php elseif ($error): ?>
        <div class="alert alert-danger">
            <p><?php echo $error; ?></p>
        </div>
        <p class="text-center mt-2">
            <a href="<?php echo baseUrl(); ?>/forgot_password.php">Request new reset link</a>
        </p>
    <?php else: ?>
        <p class="text-center" style="color: #6b7280; margin-bottom: 20px;">
            Create a new password for your account.
        </p>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required 
                       minlength="8" placeholder="Min 8 characters">
                <small style="color: #6b7280; display: block; margin-top: 5px;">
                    Must contain: 8+ chars, uppercase, lowercase, number, special character
                </small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       placeholder="Re-enter password">
            </div>
            
            <button type="submit" class="btn" style="width: 100%;">Reset Password</button>
            
            <p class="text-center mt-2">
                <a href="<?php echo baseUrl(); ?>/login.php">&larr; Back to Login</a>
            </p>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
