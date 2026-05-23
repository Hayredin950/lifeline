<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(baseUrl() . '/index.php');
}

$rateLimitInfo = null;
$identifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . ($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $email = sanitizeEmail($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Rate limiting check
    if (isRateLimited($identifier)) {
        $info = getRateLimitRemaining($identifier);
        setFlash('Too many failed attempts. Please try again in ' . $info['minutes_remaining'] . ' minutes.', 'danger');
        redirect(baseUrl() . '/login.php');
    }

    if (!$email || !$password) {
        recordLoginAttempt($identifier);
        setFlash('Please enter both email and password.', 'danger');
        redirect(baseUrl() . '/login.php');
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = true");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Clear rate limiting on successful login
        clearLoginAttempts($identifier);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        session_regenerate_id(true);
        auditLog($pdo, 'login', 'user', $user['id'], null, ['role' => $user['role']]);
        setFlash('Login successful. Welcome back!', 'success');
        if ($user['role'] === 'admin') {
            redirect(baseUrl() . '/admin/dashboard.php');
        } elseif ($user['role'] === 'hospital') {
            redirect(baseUrl() . '/hospital/dashboard.php');
        } else {
            redirect(baseUrl() . '/donor/dashboard.php');
        }
    } else {
        recordLoginAttempt($identifier);
        auditLog($pdo, 'login_failed', 'user', null, null, ['email' => $email]);
        $remaining = getRateLimitRemaining($identifier);
        $msg = 'Invalid email or password.';
        if ($remaining['attempts_remaining'] > 0 && $remaining['attempts_remaining'] < 5) {
            $msg .= ' (' . $remaining['attempts_remaining'] . ' attempts remaining)';
        }
        setFlash($msg, 'danger');
        redirect(baseUrl() . '/login.php');
    }
}

// Get rate limit info for display
$rateLimitInfo = getRateLimitRemaining($identifier);

include 'includes/header.php';
?>

<div class="card" style="max-width: 480px; margin: 40px auto;">
    <h1 class="text-center">Login</h1>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo old('email'); ?>" required placeholder="you@example.com">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required placeholder="Enter password">
        </div>
        <button type="submit" class="btn" style="width:100%;">Login</button>
        <p class="text-center mt-2">
            <a href="<?php echo baseUrl(); ?>/forgot_password.php">Forgot password?</a>
        </p>
        <p class="text-center">Don't have an account? <a href="<?php echo baseUrl(); ?>/register.php">Register</a></p>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
