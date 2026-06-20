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
    
    // Geocode the location when it changed so request matching (FR-20) ranks by real
    // distance from the hospital (DEF-09). Best-effort: keep old coords on lookup failure.
    $coords = geocodeIfChanged($_POST, $profile);
    $lat = $coords['latitude']  ?? $profile['latitude'];
    $lng = $coords['longitude'] ?? $profile['longitude'];

    // Update hospital profile
    $stmt = $pdo->prepare("
        UPDATE hospital_profiles SET
            hospital_name = ?,
            phone = ?,
            address = ?,
            city = ?,
            state = ?,
            country = ?,
            license_number = ?,
            latitude = ?,
            longitude = ?
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
        $lat,
        $lng,
        $userId
    ]);
    
    // Email change requires verification of the new address before the swap (DEF-07).
    $newEmail = sanitizeEmail($_POST['email'] ?? '');
    $emailChangePending = false;
    if ($newEmail && $newEmail !== $user['email']) {
        $result = requestEmailChange($pdo, $userId, $newEmail);
        if (!$result['success']) {
            setFlash($result['error'], 'danger');
            redirect(baseUrl() . '/hospital/edit_profile.php');
        }
        $emailChangePending = true;
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
        
        $msg = 'Profile and password updated successfully.';
        if ($emailChangePending) $msg .= ' Check your new inbox to confirm the email change — your address stays the same until you do.';
        setFlash($msg, 'success');
        redirect(baseUrl() . '/hospital/dashboard.php');
    } else {
        $msg = 'Profile updated successfully.';
        if ($emailChangePending) $msg .= ' Check your new inbox to confirm the email change — your address stays the same until you do.';
        setFlash($msg, 'success');
        redirect(baseUrl() . '/hospital/edit_profile.php');
    }
}

include '../includes/header.php';
?>

<div class="card maxw-650 mx-auto my-30">
    <h1>Edit Hospital Profile</h1>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <h3 class="form-section-title mt-20">Account Information</h3>
        
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo htmlspecialchars($user['email']); ?>">
        </div>
        
        <h3 class="form-section-title mt-30">Hospital Details</h3>
        
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
        
        <h3 class="form-section-title mt-30">Change Password (Optional)</h3>
        <p class="text-muted fs-90 mb-15">
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
            <small class="field-hint">
                Must contain: 8+ chars, uppercase, lowercase, number, special character
            </small>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" 
                   placeholder="Re-enter new password">
        </div>
        
        <button type="submit" class="btn w-full">Save Changes</button>
        
        <p class="text-center mt-16">
            <a href="<?php echo baseUrl(); ?>/hospital/dashboard.php">&larr; Back to Dashboard</a>
        </p>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
