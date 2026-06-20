<?php
require_once '../includes/functions.php';
requireHospital();

$userId = $_SESSION['user_id'];
$profile = getHospitalProfile($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $bloodType = $_POST['patient_blood_type'] ?? '';
    $urgency   = $_POST['urgency'] ?? 'normal';
    $reqErrors = [];

    if (!isValidBloodType($bloodType)) {
        $reqErrors[] = 'Please select a valid blood type.';
    }
    if (!in_array($urgency, ['normal', 'urgent', 'critical'], true)) {
        $reqErrors[] = 'Invalid urgency level.';
    }

    if (!empty($reqErrors)) {
        setFlash(implode('<br>', $reqErrors), 'danger');
        redirect(baseUrl() . '/hospital/create_request.php');
    }

    $stmt = $pdo->prepare("
        INSERT INTO blood_requests
        (hospital_id, patient_blood_type, units_needed, urgency, required_date, city, state, hospital_address, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $bloodType,
        max(1, (int)($_POST['units_needed'] ?? 1)),
        $urgency,
        $_POST['required_date'] ?: null,
        trim($_POST['city'] ?? ''),
        trim($_POST['state'] ?? ''),
        trim($_POST['hospital_address'] ?? ''),
        trim($_POST['notes'] ?? '')
    ]);

    setFlash('Blood request created successfully.', 'success');
    redirect(baseUrl() . '/hospital/dashboard.php');
}

include '../includes/header.php';
?>

<div class="card maxw-700 mx-auto my-30">
    <h1>Create Urgent Blood Request</h1>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <div class="form-group">
            <label for="patient_blood_type">Patient Blood Type Needed</label>
            <select id="patient_blood_type" name="patient_blood_type" required>
                <option value="">-- Select --</option>
                <?php $types = ['A+','A-','B+','B-','AB+','AB-','O+','O-']; ?>
                <?php foreach ($types as $t): ?>
                    <option value="<?php echo $t; ?>" <?php echo old('patient_blood_type') === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="units_needed">Units Needed</label>
            <input type="number" id="units_needed" name="units_needed" min="1" value="<?php echo old('units_needed', '1'); ?>" required>
        </div>
        <div class="form-group">
            <label for="urgency">Urgency Level</label>
            <select id="urgency" name="urgency" required>
                <option value="normal" <?php echo old('urgency') === 'normal' ? 'selected' : ''; ?>>Normal</option>
                <option value="urgent" <?php echo old('urgency') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                <option value="critical" <?php echo old('urgency') === 'critical' ? 'selected' : ''; ?>>Critical</option>
            </select>
        </div>
        <div class="form-group">
            <label for="required_date">Required By Date</label>
            <input type="date" id="required_date" name="required_date" value="<?php echo old('required_date'); ?>">
        </div>
        <div class="form-group">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="<?php echo old('city', $profile['city'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="state">State / Province</label>
            <input type="text" id="state" name="state" value="<?php echo old('state', $profile['state'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="hospital_address">Hospital Address (for pickup)</label>
            <textarea id="hospital_address" name="hospital_address" placeholder="Enter full address for donor arrival / pickup"><?php echo old('hospital_address', $profile['address'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="notes">Additional Notes</label>
            <textarea id="notes" name="notes" placeholder="Any special requirements, contact person, etc."><?php echo old('notes'); ?></textarea>
        </div>
        <button type="submit" class="btn w-full">Submit Request</button>
        <p class="text-center mt-16"><a href="<?php echo baseUrl(); ?>/hospital/dashboard.php">&larr; Back to Dashboard</a></p>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
