<?php
require_once '../includes/functions.php';
requireDonor();

$userId = $_SESSION['user_id'];
$profile = getDonorProfile($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $errors = [];

    // Handle profile picture upload
    $profilePic = $profile['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['name'] !== '') {
        $uploadResult = handleImageUpload($_FILES['profile_pic'], 'uploads/profile_pics');
        if ($uploadResult['success']) {
            $newPic = $uploadResult['filename'];
            // Delete old pic if exists
            if ($profilePic && file_exists(__DIR__ . '/../uploads/profile_pics/' . $profilePic)) {
                @unlink(__DIR__ . '/../uploads/profile_pics/' . $profilePic);
            }
            $profilePic = $newPic;
        } else {
            $errors[] = $uploadResult['error'];
        }
    }

    if (!empty($errors)) {
        setFlash(implode('<br>', $errors), 'danger');
        redirect(baseUrl() . '/donor/edit_profile.php');
    }

    // Update donor profile
    $stmt = $pdo->prepare("
        UPDATE donor_profiles SET
            full_name = ?,
            phone = ?,
            blood_type = ?,
            address = ?,
            city = ?,
            state = ?,
            country = ?,
            date_of_birth = ?,
            gender = ?,
            last_donation_date = ?,
            is_available = ?,
            profile_pic = ?
        WHERE user_id = ?
    ");
    $stmt->execute([
        trim($_POST['full_name'] ?? ''),
        trim($_POST['phone'] ?? ''),
        $_POST['blood_type'] ?? '',
        trim($_POST['address'] ?? ''),
        trim($_POST['city'] ?? ''),
        trim($_POST['state'] ?? ''),
        trim($_POST['country'] ?? 'India'),
        $_POST['date_of_birth'] ?: null,
        $_POST['gender'] ?? null,
        $_POST['last_donation_date'] ?: null,
        isset($_POST['is_available']) ? 1 : 0,
        $profilePic,
        $userId
    ]);

    // Update email if changed
    $newEmail = sanitizeEmail($_POST['email'] ?? '');
    if ($newEmail && $newEmail !== $_SESSION['email']) {
        // Check if email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$newEmail, $userId]);
        if ($check->fetch()) {
            setFlash('Email address is already in use by another account.', 'danger');
            redirect(baseUrl() . '/donor/edit_profile.php');
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
            redirect(baseUrl() . '/donor/edit_profile.php');
        }

        // Validate new password
        $passwordErrors = validatePassword($newPassword);
        if (!empty($passwordErrors)) {
            setFlash(implode('<br>', $passwordErrors), 'danger');
            redirect(baseUrl() . '/donor/edit_profile.php');
        }

        if ($newPassword !== $confirmPassword) {
            setFlash('New passwords do not match.', 'danger');
            redirect(baseUrl() . '/donor/edit_profile.php');
        }

        // Update password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);

        setFlash('Profile and password updated successfully.', 'success');
        redirect(baseUrl() . '/donor/dashboard.php');
    } else {
        setFlash('Profile updated successfully.', 'success');
        redirect(baseUrl() . '/donor/edit_profile.php');
    }
}

// Get current user data
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

include '../includes/header.php';
?>

<div class="card" style="max-width: 650px; margin: 30px auto;">
    <h1>Edit Profile</h1>
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <h3 style="margin-top: 20px; color: #b91c1c;">Profile Photo</h3>
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
            <img src="<?php echo getProfilePic($profile); ?>" alt="Profile" 
                 style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--crimson);">
            <div>
                <label for="profile_pic">Upload New Photo</label>
                <input type="file" id="profile_pic" name="profile_pic" accept="image/*">
                <p style="font-size: 0.8rem; color: #6b7280; margin-top: 5px;">JPG, PNG or WebP. Max 2MB.</p>
            </div>
        </div>

        <h3 style="margin-top: 20px; color: #b91c1c;">Account Information</h3>
        
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo htmlspecialchars($user['email']); ?>">
        </div>

        <h3 style="margin-top: 30px; color: #b91c1c;">Personal Information</h3>

        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($profile['full_name']); ?>">
        </div>
        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" required value="<?php echo htmlspecialchars($profile['phone']); ?>">
        </div>
        <div class="form-group">
            <label for="blood_type">Blood Type</label>
            <select id="blood_type" name="blood_type" required>
                <?php $types = ['A+','A-','B+','B-','AB+','AB-','O+','O-']; ?>
                <?php foreach ($types as $t): ?>
                    <option value="<?php echo $t; ?>" <?php echo ($profile['blood_type'] === $t) ? 'selected' : ''; ?>><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="date_of_birth">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($profile['date_of_birth'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="gender">Gender</label>
            <select id="gender" name="gender">
                <option value="">-- Select --</option>
                <option value="male" <?php echo ($profile['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo ($profile['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                <option value="other" <?php echo ($profile['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="state">State / Province</label>
            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($profile['state'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="country">Country</label>
            <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($profile['country'] ?? 'India'); ?>">
        </div>
        <div class="form-group">
            <label for="last_donation_date">Last Donation Date</label>
            <input type="date" id="last_donation_date" name="last_donation_date" value="<?php echo htmlspecialchars($profile['last_donation_date'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_available" value="1" <?php echo $profile['is_available'] ? 'checked' : ''; ?>> I am currently available to donate
            </label>
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
        
        <button type="submit" class="btn" style="width:100%;">Save Changes</button>
        <p class="text-center mt-2"><a href="<?php echo baseUrl(); ?>/donor/dashboard.php">&larr; Back to Dashboard</a></p>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
