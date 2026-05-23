<?php
require_once '../includes/functions.php';
requireHospital();

$userId = $_SESSION['user_id'];
$profile = getHospitalProfile($pdo, $userId);

// Get user data for email
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    
    // Update hospital profile
    $stmt = $pdo->prepare("
        UPDATE hospital_profiles SET
            hospital_name = ?,
            phone = ?,
            address = ?,
            city = ?,
            state = ?,
            country = ?,
            license_number = ?
        WHERE user_id = ?
    ");
    $stmt->execute([
        trim($_POST['hospital_name'] ?? ''),
        trim($_POST['phone'] ?? ''),
        trim($_POST['address'] ?? ''),
        trim($_POST['city'] ?? ''),
        trim($_POST['state'] ?? ''),
        trim($_POST['country'] ?? 'India'),
        trim($_POST['license_number'] ?? ''),
        $userId
    ]);
    
    // Update email if changed
    $newEmail = sanitizeEmail($_POST['email'] ?? '');
    if ($newEmail && $newEmail !== $user['email']) {
        // Check if email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$newEmail, $userId]);
        if ($check->fetch()) {
            setFlash('Email address is already in use by another account.', 'danger');
            redirect(baseUrl() . '/hospital/edit_profile.php');
        }
        
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$newEmail, $userId]);
        $_SESSION['email'] = $newEmail;
    }
    
    // Handle password change
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($newPassword) {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentHash = $stmt->fetchColumn();
        
        if (!password_verify($currentPassword, $currentHash)) {
            setFlash('Current password is incorrect.', 'danger');
            redirect(baseUrl() . '/hospital/edit_profile.php');
        }
        
        // Validate new password
        $passwordErrors = validatePassword($newPassword);
        if (!empty($passwordErrors)) {
            setFlash(implode('<br>', $passwordErrors), 'danger');
            redirect(baseUrl() . '/hospital/edit_profile.php');
        }
        
        if ($newPassword !== $confirmPassword) {
            setFlash('New passwords do not match.', 'danger');
            redirect(baseUrl() . '/hospital/edit_profile.php');
        }
        
        // Update password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        
        setFlash('Profile and password updated successfully.', 'success');
        redirect(baseUrl() . '/hospital/dashboard.php');
    } else {
        setFlash('Profile updated successfully.', 'success');
        redirect(baseUrl() . '/hospital/edit_profile.php');
    }
}

include '../includes/header.php';
?>

<div class="card" style="max-width: 650px; margin: 30px auto;">
    <h1>Edit Hospital Profile</h1>
    
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        
        <h3 style="margin-top: 20px; color: #b91c1c;">Account Information</h3>
        
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo htmlspecialchars($user['email']); ?>">
        </div>
        
        <h3 style="margin-top: 30px; color: #b91c1c;">Hospital Details</h3>
        
        <div class="form-group">
            <label for="hospital_name">Hospital Name</label>
            <input type="text" id="hospital_name" name="hospital_name" required 
                   value="<?php echo htmlspecialchars($profile['hospital_name']); ?>">
        </div>
        
        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" required 
                   value="<?php echo htmlspecialchars($profile['phone']); ?>">
        </div>
        
        <div class="form-group">
            <label for="license_number">License Number</label>
            <input type="text" id="license_number" name="license_number" required 
                   value="<?php echo htmlspecialchars($profile['license_number']); ?>">
        </div>
        
        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="city">City</label>
            <input type="text" id="city" name="city" 
                   value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="state">State / Province</label>
            <input type="text" id="state" name="state" 
                   value="<?php echo htmlspecialchars($profile['state'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="country">Country</label>
            <input type="text" id="country" name="country" 
                   value="<?php echo htmlspecialchars($profile['country'] ?? 'India'); ?>">
        </div>
        
        <h3 style="margin-top: 30px; color: #b91c1c;">Change Password (Optional)</h3>
        <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 15px;">
            Leave blank to keep your current password.
        </p>
        
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" 
                   placeholder="Required to change password">
        </div>
        
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" 
                   minlength="8" placeholder="Min 8 characters">
            <small style="color: #6b7280; display: block; margin-top: 5px;">
                Must contain: 8+ chars, uppercase, lowercase, number, special character
            </small>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" 
                   placeholder="Re-enter new password">
        </div>
        
        <button type="submit" class="btn" style="width: 100%;">Save Changes</button>
        
        <p class="text-center mt-2">
            <a href="<?php echo baseUrl(); ?>/hospital/dashboard.php">&larr; Back to Dashboard</a>
        </p>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
