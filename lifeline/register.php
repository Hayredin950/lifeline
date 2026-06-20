<?php
require_once 'includes/functions.php';
require_once 'includes/email_service.php';

if (isLoggedIn()) {
    redirect(baseUrl() . '/index.php');
}

$role = $_GET['role'] ?? $_POST['role'] ?? '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $role = $_POST['role'] ?? '';
    $email = sanitizeEmail($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!in_array($role, ['donor', 'hospital'], true)) {
        $errors[] = 'Invalid account type selected.';
    }

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    
    // Validate password strength
    $passwordErrors = validatePassword($password);
    if (!empty($passwordErrors)) {
        $errors = array_merge($errors, $passwordErrors);
    }
    
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Role-specific field validation
    if (empty($errors) && $role === 'donor') {
        $bloodType = $_POST['blood_type'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        if (!isValidBloodType($bloodType)) {
            $errors[] = 'Please select a valid blood type.';
        }
        if ($phone !== '' && !validatePhone($phone)) {
            $errors[] = 'Please enter a valid phone number (e.g. +251 91 234 5678).';
        }
    }

    // Consent to terms is mandatory (FR-49 / Doc 07 §6).
    if (!isset($_POST['consent_terms'])) {
        $errors[] = 'You must agree to the Terms of Service and Privacy Policy to register.';
    }

    if (empty($errors)) {
        // Check email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$email, $hash, $role]);
        $userId = $pdo->lastInsertId();

        // Geocode the registration location so the profile is map-ready for distance
        // matching (FR-20 / DEF-09). Best-effort: null coords if the lookup fails.
        $coords = geocodeIfChanged($_POST);
        $lat = $coords['latitude']  ?? null;
        $lng = $coords['longitude'] ?? null;

        if ($role === 'donor') {
            $stmt = $pdo->prepare("
                INSERT INTO donor_profiles
                (user_id, full_name, phone, blood_type, address, city, state, country, date_of_birth, gender, latitude, longitude)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                trim($_POST['full_name'] ?? ''),
                trim($_POST['phone'] ?? ''),
                $_POST['blood_type'] ?? '',
                trim($_POST['address'] ?? ''),
                trim($_POST['city'] ?? ''),
                trim($_POST['state'] ?? ''),
                trim($_POST['country'] ?? 'Ethiopia'),
                $_POST['date_of_birth'] ?: null,
                $_POST['gender'] ?? null,
                $lat,
                $lng
            ]);
        } elseif ($role === 'hospital') {
            $stmt = $pdo->prepare("
                INSERT INTO hospital_profiles
                (user_id, hospital_name, phone, address, city, state, country, license_number, latitude, longitude)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                trim($_POST['hospital_name'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['address'] ?? ''),
                trim($_POST['city'] ?? ''),
                trim($_POST['state'] ?? ''),
                trim($_POST['country'] ?? 'Ethiopia'),
                trim($_POST['license_number'] ?? ''),
                $lat,
                $lng
            ]);
        }

        // Record consent (FR-49 / Doc 07 §6) — immutable audit trail.
        $pdo->prepare("
            INSERT INTO consent_log (user_id, terms_version, ip_address, user_agent)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $userId,
            TERMS_VERSION,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        ]);

        // Auto-login
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $role;
        $_SESSION['email'] = $email;
        session_regenerate_id(true);
        
        // Queue welcome email asynchronously (NFR-02) — off the request path.
        if ($role === 'donor') {
            enqueueNotification($pdo, 'donor_welcome', $email, ['name' => trim($_POST['full_name'] ?? '')]);
        } else {
            enqueueNotification($pdo, 'hospital_welcome', $email, ['name' => trim($_POST['hospital_name'] ?? '')]);
        }
        
        setFlash('Registration successful! Welcome.', 'success');
        if ($role === 'donor') {
            redirect(baseUrl() . '/donor/dashboard.php');
        } else {
            redirect(baseUrl() . '/hospital/dashboard.php');
        }
    } else {
        setFlash(implode('<br>', $errors), 'danger');
    }
}

include 'includes/header.php';
?>

<?php if (!in_array($role, ['donor', 'hospital'])): ?>
<div class="card maxw-600 mx-auto my-40">
    <h1 class="text-center"><?php echo t('auth.register_title'); ?></h1>
    <p class="text-center">Choose your account type to get started.</p>
    <div class="grid-2 mt-20">
        <a href="<?php echo baseUrl(); ?>/register.php?role=donor" class="card text-center no-underline text-inherit">
            <h2><?php echo t('register.role_donor'); ?></h2>
            <p>Register as a voluntary blood donor and help save lives in your community.</p>
            <span class="btn"><?php echo t('register.submit'); ?></span>
        </a>
        <a href="<?php echo baseUrl(); ?>/register.php?role=hospital" class="card text-center no-underline text-inherit">
            <h2><?php echo t('register.role_hospital'); ?></h2>
            <p>Register your hospital to create emergency blood requests and find donors.</p>
            <span class="btn"><?php echo t('register.submit'); ?></span>
        </a>
    </div>
</div>
<?php else: ?>
<div class="card maxw-600 mx-auto my-40">
    <h1 class="text-center"><?php echo t('auth.register_title'); ?></h1>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo old('email'); ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8" placeholder="Min 8 characters">
            <small class="field-hint">
                Must contain: 8+ chars, uppercase, lowercase, number, special character
            </small>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <?php if ($role === 'donor'): ?>
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo old('full_name'); ?>" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" value="<?php echo old('phone'); ?>" required placeholder="e.g. +251 91 234 5678">
        </div>
        <div class="form-group">
            <label for="blood_type">Blood Type</label>
            <select id="blood_type" name="blood_type" required>
                <option value="">-- Select --</option>
                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bt): ?>
                    <option value="<?php echo $bt; ?>" <?php echo old('blood_type') === $bt ? 'selected' : ''; ?>><?php echo $bt; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="date_of_birth">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo old('date_of_birth'); ?>">
        </div>
        <div class="form-group">
            <label for="gender">Gender</label>
            <select id="gender" name="gender">
                <option value="">-- Select --</option>
                <option value="male" <?php echo old('gender') === 'male' ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo old('gender') === 'female' ? 'selected' : ''; ?>>Female</option>
                <option value="other" <?php echo old('gender') === 'other' ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address" placeholder="Street address"><?php echo old('address'); ?></textarea>
        </div>
        <div class="form-group">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="<?php echo old('city'); ?>">
        </div>
        <div class="form-group">
            <label for="state">State / Province</label>
            <input type="text" id="state" name="state" value="<?php echo old('state'); ?>">
        </div>
        <div class="form-group">
            <label for="country">Country</label>
            <input type="text" id="country" name="country" value="<?php echo old('country', 'Ethiopia'); ?>">
        </div>
        <?php elseif ($role === 'hospital'): ?>
        <div class="form-group">
            <label for="hospital_name">Hospital Name</label>
            <input type="text" id="hospital_name" name="hospital_name" value="<?php echo old('hospital_name'); ?>" required>
        </div>
        <div class="form-group">
            <label for="license_number">License Number</label>
            <input type="text" id="license_number" name="license_number" value="<?php echo old('license_number'); ?>" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" value="<?php echo old('phone'); ?>" required>
        </div>
        <div class="form-group">
            <label for="address">Full Address</label>
            <textarea id="address" name="address" required><?php echo old('address'); ?></textarea>
        </div>
        <div class="form-group">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="<?php echo old('city'); ?>" required>
        </div>
        <div class="form-group">
            <label for="state">State</label>
            <input type="text" id="state" name="state" value="<?php echo old('state'); ?>" required>
        </div>
        <div class="form-group">
            <label for="country">Country</label>
            <input type="text" id="country" name="country" value="<?php echo old('country', 'Ethiopia'); ?>" required>
        </div>
        <?php endif; ?>

        <div class="form-group mt-20">
            <label class="flex items-center gap-10">
                <input type="checkbox" name="consent_terms" value="1" required
                       <?php echo isset($_POST['consent_terms']) ? 'checked' : ''; ?>>
                <span><?php echo t('register.consent', ['version' => TERMS_VERSION]); ?></span>
            </label>
        </div>

        <button type="submit" class="btn w-full"><?php echo t('register.submit'); ?></button>
        <p class="text-center mt-16"><a href="<?php echo baseUrl(); ?>/register.php">&larr; Back to selection</a></p>
    </form>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
