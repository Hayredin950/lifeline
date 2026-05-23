<?php
require_once 'includes/functions.php';
$pageTitle = 'Emergency SOS';

$success = false;
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

    if ($name && $phone && $bloodType && $city && $state) {
        // Create an urgent blood request
        $stmt = $pdo->prepare("
            INSERT INTO blood_requests (hospital_id, patient_blood_type, units_needed, urgency, status, city, state, hospital_address, notes, required_date)
            VALUES (?, ?, ?, 'critical', 'open', ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([NULL, $bloodType, $units, $city, $state, $hospital ?: 'Emergency - ' . $name, "EMERGENCY SOS REQUEST\nContact: $name\nPhone: $phone\n" . $notes]);
        $requestId = $pdo->lastInsertId();

        // Try to notify compatible donors via email
        $compatibleTypes = getCompatibleDonorBloodTypes($bloodType);
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
                EmailService::sendBloodRequestNotification(
                    $donor['email'],
                    $donor['full_name'],
                    $requestData
                );
            }
        }

        auditLog($pdo, 'emergency_sos', 'request', $requestId);
        setFlash('Emergency SOS request submitted! Compatible donors in your area are being notified.', 'success');
        redirect(baseUrl() . '/emergency.php');
    } else {
        setFlash('Please fill in all required fields.', 'danger');
        // No redirect here so inputs are preserved
    }
}

include 'includes/header.php';
?>

<div class="sos-hero">
    <div class="sos-pulse">&#9764;</div>
    <h1>Emergency Blood SOS</h1>
    <p>For critical, life-threatening blood emergencies. Your request will be sent to all compatible donors in your area immediately.</p>
</div>

<div class="dashboard-layout">
    <div class="card">
        <h2 style="color: var(--danger);">&#9888; Submit Emergency Request</h2>
        <p style="color: var(--text-secondary);">This creates a CRITICAL priority request and notifies nearby compatible donors.</p>

        <form method="POST" action="" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label for="name">Your Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo old('name'); ?>" required placeholder="Full name">
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo old('phone'); ?>" required placeholder="+91-XXXXX-XXXXX">
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
                    <input type="text" id="city" name="city" value="<?php echo old('city'); ?>" required placeholder="e.g. Mumbai">
                </div>
                <div class="form-group">
                    <label for="state">State *</label>
                    <input type="text" id="state" name="state" value="<?php echo old('state'); ?>" required placeholder="e.g. Maharashtra">
                </div>
            </div>

            <div class="form-group">
                <label for="hospital">Hospital Name (If admitted)</label>
                <input type="text" id="hospital" name="hospital" value="<?php echo old('hospital'); ?>" placeholder="e.g. Apollo Hospital">
            </div>

            <div class="form-group">
                <label for="notes">Additional Notes</label>
                <textarea id="notes" name="notes" placeholder="Any specific requirements..."><?php echo old('notes'); ?></textarea>
            </div>

            <button type="submit" class="btn btn-large" style="width: 100%; background: linear-gradient(135deg, #d63031, #c0392b);">
                &#9888; Submit Emergency SOS
            </button>
        </form>
    </div>

    <div>
        <div class="card" style="border-left: 4px solid #d63031;">
            <h3 style="color: #d63031;">&#9888; When to Use SOS</h3>
            <ul style="padding-left: 20px; color: var(--text-secondary); font-size: 0.9rem; margin-top: 8px;">
                <li>Life-threatening emergency</li>
                <li>Rare blood type needed urgently</li>
                <li>Multiple units required immediately</li>
                <li>No matching donors found through normal search</li>
            </ul>
        </div>

        <div class="card" style="border-left: 4px solid var(--success);">
            <h3 style="color: var(--success);">&#128222; Emergency Hotlines</h3>
            <div style="margin-top: 8px;">
                <p style="font-size: 0.9rem;"><strong>Indian Red Cross:</strong> 1800-11-4488</p>
                <p style="font-size: 0.9rem;"><strong>Blood Helpline:</strong> 104</p>
                <p style="font-size: 0.9rem;"><strong>Emergency:</strong> 112</p>
                <p style="font-size: 0.9rem;"><strong>Ambulance:</strong> 108</p>
            </div>
        </div>

        <div class="card">
            <h3>&#128161; Tips</h3>
            <ul style="padding-left: 20px; color: var(--text-secondary); font-size: 0.9rem; margin-top: 8px;">
                <li>Call the blood bank directly for fastest response</li>
                <li>Keep patient blood report ready</li>
                <li>Arrange transport for donors</li>
                <li>Check nearby blood banks too</li>
            </ul>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
