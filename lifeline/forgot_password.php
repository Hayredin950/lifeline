<?php
require_once 'includes/functions.php';
require_once 'includes/email_service.php';

if (isLoggedIn()) {
    redirect(baseUrl() . '/index.php');
}

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $email = sanitizeEmail($_POST['email'] ?? '');
    
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('Please enter a valid email address.', 'danger');
        redirect(baseUrl() . '/forgot_password.php');
    }
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = true");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Store token in database
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (email, token, expires_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), used_at = NULL
        ");
        $stmt->execute([$email, $token, $expiresAt]);
        
        // Queue password-reset email asynchronously (NFR-02).
        $resetUrl = fullBaseUrl() . '/reset_password.php?token=' . $token;
        enqueueNotification($pdo, 'password_reset', $email, ['reset_url' => $resetUrl]);
    }
    
    // Always show success message to prevent email enumeration
    $success = true;
}

include 'includes/header.php';
?>

<div class="card maxw-480 mx-auto my-40">
    <h1 class="text-center">Reset Password</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <p><strong>Email sent!</strong></p>
            <p>If an account exists with this email address, you will receive password reset instructions shortly.</p>
            <p>Please check your inbox and spam folder.</p>
        </div>
        <p class="text-center mt-16">
            <a href="<?php echo baseUrl(); ?>/login.php">&larr; Back to Login</a>
        </p>
    <?php else: ?>
        <p class="text-center text-muted mb-20">
            Enter your email address and we'll send you a link to reset your password.
        </p>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       placeholder="you@example.com" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <button type="submit" class="btn w-full">Send Reset Link</button>
            
            <p class="text-center mt-16">
                <a href="<?php echo baseUrl(); ?>/login.php">&larr; Back to Login</a>
            </p>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
