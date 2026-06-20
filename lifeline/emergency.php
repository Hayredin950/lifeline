<?php
require_once 'includes/functions.php';
requireAuth();
$pageTitle = 'Emergency SOS';

// Issue a fresh arithmetic CAPTCHA (no external dependency — stack-compliant).
function newSosCaptcha(): array {
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    $_SESSION['sos_captcha'] = $a + $b;
    return [$a, $b];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $name = sanitizeString($_POST['name'] ?? '');
    $phone = sanitizeString($_POST['phone'] ?? '');
    $bloodType = $_POST['blood_type'] ?? '';
    $units = (int)($_POST['units'] ?? 1);
    $city = sanitizeString($_POST['city'] ?? '');
    $state = sanitizeString($_POST['state'] ?? '');
    $hospital = sanitizeString($_POST['hospital'] ?? '');
    $notes = sanitizeString($_POST['notes'] ?? '');
    $honeypot = trim($_POST['website'] ?? '');         // bots fill this; humans never see it
    $captchaAnswer = $_POST['captcha'] ?? '';

    // 1. Honeypot: a filled hidden field means a bot. Feign success, do nothing.
    if ($honeypot !== '') {
        setFlash('Emergency SOS request submitted! Compatible donors in your area are being notified.', 'success');
        redirect(baseUrl() . '/emergency.php');
    }

    // 2. Per-IP rate limit (DEF-02): 5 requests / hour / IP — durable, guest-proof.
    $ipLimit = rateLimitHit($pdo, 'sos_ip:' . clientIp(), 5, 3600);
    if (!$ipLimit['allowed']) {
        $mins = (int)ceil($ipLimit['retry_after'] / 60);
        $errors[] = "Too many emergency requests from your network. Please wait about {$mins} minute(s), or call a hotline listed on the right.";
    }

    // 3. CAPTCHA check.
    if (empty($errors) && (!isset($_SESSION['sos_captcha']) || (int)$captchaAnswer !== (int)$_SESSION['sos_captcha'])) {
        $errors[] = 'Incorrect answer to the verification question. Please try the new question and submit again.';
    }

    // 4. Required fields + blood-type whitelist (DEF-05).
    if (empty($errors)) {
        if (!($name && $phone && $bloodType && $city && $state)) {
            $errors[] = 'Please fill in all required fields.';
        } elseif (!isValidBloodType($bloodType)) {
            $errors[] = 'Please select a valid blood type.';
        }
    }

    // 5. Per-phone rate limit (DEF-02): 3 requests / hour / phone.
    if (empty($errors)) {
        $phoneKey = 'sos_phone:' . preg_replace('/\s+/', '', $phone);
        $phoneLimit = rateLimitHit($pdo, $phoneKey, 3, 3600);
        if (!$phoneLimit['allowed']) {
            $mins = (int)ceil($phoneLimit['retry_after'] / 60);
            $errors[] = "An emergency request from this phone number was just submitted. Please wait about {$mins} minute(s).";
        }
    }

    if (empty($errors)) {
        $units = max(1, min(10, $units));

        // Create the critical request.
        $stmt = $pdo->prepare("
            INSERT INTO blood_requests (hospital_id, patient_blood_type, units_needed, urgency, status, city, state, hospital_address, notes, required_date)
            VALUES (?, ?, ?, 'critical', 'open', ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([NULL, $bloodType, $units, $city, $state, $hospital ?: 'Emergency - ' . $name, "EMERGENCY SOS REQUEST\nContact: $name\nPhone: $phone\n" . $notes]);
        $requestId = $pdo->lastInsertId();

        // ENQUEUE donor notifications (DEF-03) — the worker delivers them off the request thread.
        $compatibleTypes = getCompatibleDonorBloodTypes($bloodType);
        $queued = 0;
        if (!empty($compatibleTypes)) {
            $placeholders = implode(',', array_fill(0, count($compatibleTypes), '?'));
            $donorStmt = $pdo->prepare("
                SELECT u.email, dp.full_name FROM donor_profiles dp
                JOIN users u ON dp.user_id = u.id
                WHERE u.is_active = true AND dp.is_available = true
                AND dp.blood_type IN ($placeholders) AND dp.city LIKE ?
            ");
            $params = array_merge($compatibleTypes, ["%$city%"]);
            $donorStmt->execute($params);
            $donors = $donorStmt->fetchAll();

            $requestData = [
                'id' => $requestId,
                'patient_blood_type' => $bloodType,
                'urgency' => 'critical',
                'hospital_name' => $hospital ?: 'Emergency SOS',
                'city' => $city,
                'state' => $state,
                'units_needed' => $units,
                'required_date' => date('Y-m-d')
            ];

            foreach ($donors as $donor) {
                if (enqueueNotification($pdo, 'blood_request', $donor['email'], [
                    'donor_name' => $donor['full_name'],
                    'request'    => $requestData,
                ])) {
                    $queued++;
                }
            }
        }

        auditLog($pdo, 'emergency_sos', 'request', $requestId, null, ['queued_notifications' => $queued]);
        unset($_SESSION['sos_captcha']);
        setFlash("Emergency SOS submitted! {$queued} compatible donor(s) in your area are being notified.", 'success');
        redirect(baseUrl() . '/emergency.php');
    } else {
        setFlash(implode('<br>', $errors), 'danger');
        // Fall through to re-render with preserved inputs and a fresh CAPTCHA.
    }
}

// A fresh CAPTCHA for every render (GET or failed POST).
[$captchaA, $captchaB] = newSosCaptcha();

include 'includes/header.php';
?>

<div class="sos-hero">
    <div class="sos-pulse">&#9764;</div>
    <h1><?php echo t('sos.title'); ?></h1>
    <p><?php echo t('sos.subtitle'); ?></p>
</div>

<div class="dashboard-layout">
    <div class="card">
        <h2 class="text-danger">&#9888; Submit Emergency Request</h2>
        <p class="text-secondary">This creates a CRITICAL priority request and notifies nearby compatible donors.</p>

        <form method="POST" action="" class="mt-20">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label for="name">Your Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo old('name'); ?>" required placeholder="Full name">
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo old('phone'); ?>" required placeholder="+251-9X-XXX-XXXX">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="blood_type">Blood Type Needed *</label>
                    <select id="blood_type" name="blood_type" required>
                        <option value="">Select blood type</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bt): ?>
                            <option value="<?php echo $bt; ?>" <?php echo old('blood_type') === $bt ? 'selected' : ''; ?>><?php echo $bt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="units">Units Needed *</label>
                    <input type="number" id="units" name="units" value="<?php echo old('units', '1'); ?>" min="1" max="10" required>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="city">City *</label>
                    <input type="text" id="city" name="city" value="<?php echo old('city'); ?>" required placeholder="e.g. Addis Ababa">
                </div>
                <div class="form-group">
                    <label for="state">State *</label>
                    <input type="text" id="state" name="state" value="<?php echo old('state'); ?>" required placeholder="e.g. Oromia">
                </div>
            </div>

            <div class="form-group">
                <label for="hospital">Hospital Name (If admitted)</label>
                <input type="text" id="hospital" name="hospital" value="<?php echo old('hospital'); ?>" placeholder="e.g. Black Lion Hospital">
            </div>

            <div class="form-group">
                <label for="notes">Additional Notes</label>
                <textarea id="notes" name="notes" placeholder="Any specific requirements..."><?php echo old('notes'); ?></textarea>
            </div>

            <!-- Honeypot: hidden from humans; bots that fill it are silently dropped. -->
            <div aria-hidden="true" class="honeypot">
                <label for="website">Website (leave blank)</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
            </div>

            <div class="form-group">
                <label for="captcha">Verification: what is <?php echo $captchaA; ?> + <?php echo $captchaB; ?>? *</label>
                <input type="number" id="captcha" name="captcha" required aria-required="true"
                       placeholder="Answer" inputmode="numeric" autocomplete="off"
                       aria-describedby="captcha-hint">
                <small id="captcha-hint" class="text-secondary">A quick check to keep emergency requests genuine.</small>
            </div>

            <button type="submit" class="btn btn-large w-full btn-emergency">
                <span aria-hidden="true">&#9888;</span> <?php echo t('sos.send_btn'); ?>
            </button>
        </form>
    </div>

    <div>
        <div class="card border-l-emergency">
            <h3 class="text-emergency">&#9888; When to Use SOS</h3>
            <ul class="list-indent text-secondary fs-90 mt-8">
                <li>Life-threatening emergency</li>
                <li>Rare blood type needed urgently</li>
                <li>Multiple units required immediately</li>
                <li>No matching donors found through normal search</li>
            </ul>
        </div>

        <div class="card border-l-success">
            <h3 class="text-success">&#128222; Emergency Hotlines</h3>
            <div class="mt-8">
                <p class="fs-90"><strong>Ethiopian Red Cross:</strong> +251 11 551 5166</p>
                <p class="fs-90"><strong>Blood Bank (NABC):</strong> +251 11 667 7281</p>
                <p class="fs-90"><strong>Emergency:</strong> 911</p>
                <p class="fs-90"><strong>Ambulance:</strong> 907</p>
            </div>
        </div>

        <div class="card">
            <h3>&#128161; Tips</h3>
            <ul class="list-indent text-secondary fs-90 mt-8">
                <li>Call the blood bank directly for fastest response</li>
                <li>Keep patient blood report ready</li>
                <li>Arrange transport for donors</li>
                <li>Check nearby blood banks too</li>
            </ul>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
