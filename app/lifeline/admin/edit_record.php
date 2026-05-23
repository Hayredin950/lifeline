<?php
require_once '../includes/functions.php';
requireAdmin();

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!in_array($type, ['donor', 'hospital', 'request']) || !$id) {
    setFlash('Invalid record type or ID.', 'danger');
    redirect(baseUrl() . '/admin/dashboard.php');
}

$record = [];
$user = null;

if ($type === 'donor') {
    $stmt = $pdo->prepare("
        SELECT dp.*, u.email, u.is_active
        FROM donor_profiles dp
        JOIN users u ON dp.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
} elseif ($type === 'hospital') {
    $stmt = $pdo->prepare("
        SELECT hp.*, u.email, u.is_active
        FROM hospital_profiles hp
        JOIN users u ON hp.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
} elseif ($type === 'request') {
    $stmt = $pdo->prepare("SELECT * FROM blood_requests WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
}

if (!$record) {
    setFlash('Record not found.', 'danger');
    redirect(baseUrl() . '/admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    if ($type === 'donor') {
        // Update users table
        $stmt = $pdo->prepare("UPDATE users SET email = ?, is_active = ? WHERE id = ?");
        $stmt->execute([
            trim($_POST['email'] ?? ''),
            isset($_POST['is_active']) ? 1 : 0,
            $id
        ]);
        // Update donor_profiles
        $stmt = $pdo->prepare("
            UPDATE donor_profiles SET
                full_name = ?, phone = ?, blood_type = ?, address = ?, city = ?,
                state = ?, country = ?, date_of_birth = ?, gender = ?, is_available = ?
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
            isset($_POST['is_available']) ? 1 : 0,
            $id
        ]);
        setFlash('Donor record updated.', 'success');
        redirect(baseUrl() . '/admin/manage_donors.php');
    } elseif ($type === 'hospital') {
        $stmt = $pdo->prepare("UPDATE users SET email = ?, is_active = ? WHERE id = ?");
        $stmt->execute([
            trim($_POST['email'] ?? ''),
            isset($_POST['is_active']) ? 1 : 0,
            $id
        ]);
        $stmt = $pdo->prepare("
            UPDATE hospital_profiles SET
                hospital_name = ?, phone = ?, address = ?, city = ?,
                state = ?, country = ?, license_number = ?
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
            $id
        ]);
        setFlash('Hospital record updated.', 'success');
        redirect(baseUrl() . '/admin/manage_hospitals.php');
    } elseif ($type === 'request') {
        $stmt = $pdo->prepare("
            UPDATE blood_requests SET
                patient_blood_type = ?, units_needed = ?, urgency = ?, status = ?,
                required_date = ?, city = ?, state = ?, hospital_address = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['patient_blood_type'] ?? '',
            (int)($_POST['units_needed'] ?? 1),
            $_POST['urgency'] ?? 'normal',
            $_POST['status'] ?? 'open',
            $_POST['required_date'] ?: null,
            trim($_POST['city'] ?? ''),
            trim($_POST['state'] ?? ''),
            trim($_POST['hospital_address'] ?? ''),
            trim($_POST['notes'] ?? ''),
            $id
        ]);
        setFlash('Blood request updated.', 'success');
        redirect(baseUrl() . '/admin/manage_requests.php');
    }
}

include '../includes/header.php';
?>

<div class="card" style="max-width: 700px; margin: 30px auto;">
    <h1>Edit <?php echo ucfirst($type); ?> Record</h1>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <?php if ($type === 'donor'): ?>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($record['email']); ?>">
        </div>
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" required value="<?php echo htmlspecialchars($record['full_name']); ?>">
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" required value="<?php echo htmlspecialchars($record['phone']); ?>">
        </div>
        <div class="form-group">
            <label>Blood Type</label>
            <select name="blood_type" required>
                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $t): ?>
                    <option value="<?php echo $t; ?>" <?php echo ($record['blood_type'] === $t) ? 'selected' : ''; ?>><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($record['date_of_birth'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Gender</label>
            <select name="gender">
                <option value="">--</option>
                <option value="male" <?php echo ($record['gender']==='male'?'selected':''); ?>>Male</option>
                <option value="female" <?php echo ($record['gender']==='female'?'selected':''); ?>>Female</option>
                <option value="other" <?php echo ($record['gender']==='other'?'selected':''); ?>>Other</option>
            </select>
        </div>
        <div class="form-group">
            <label>Address</label>
            <textarea name="address"><?php echo htmlspecialchars($record['address'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label>City</label>
            <input type="text" name="city" value="<?php echo htmlspecialchars($record['city'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>State</label>
            <input type="text" name="state" value="<?php echo htmlspecialchars($record['state'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Country</label>
            <input type="text" name="country" value="<?php echo htmlspecialchars($record['country'] ?? 'India'); ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?php echo $record['is_active'] ? 'checked' : ''; ?>> Account Active</label>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_available" value="1" <?php echo $record['is_available'] ? 'checked' : ''; ?>> Available to Donate</label>
        </div>

        <?php elseif ($type === 'hospital'): ?>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($record['email']); ?>">
        </div>
        <div class="form-group">
            <label>Hospital Name</label>
            <input type="text" name="hospital_name" required value="<?php echo htmlspecialchars($record['hospital_name']); ?>">
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" required value="<?php echo htmlspecialchars($record['phone']); ?>">
        </div>
        <div class="form-group">
            <label>License Number</label>
            <input type="text" name="license_number" required value="<?php echo htmlspecialchars($record['license_number']); ?>">
        </div>
        <div class="form-group">
            <label>Address</label>
            <textarea name="address"><?php echo htmlspecialchars($record['address'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label>City</label>
            <input type="text" name="city" value="<?php echo htmlspecialchars($record['city'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>State</label>
            <input type="text" name="state" value="<?php echo htmlspecialchars($record['state'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Country</label>
            <input type="text" name="country" value="<?php echo htmlspecialchars($record['country'] ?? 'India'); ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?php echo $record['is_active'] ? 'checked' : ''; ?>> Account Active</label>
        </div>

        <?php elseif ($type === 'request'): ?>
        <div class="form-group">
            <label>Patient Blood Type</label>
            <select name="patient_blood_type" required>
                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $t): ?>
                    <option value="<?php echo $t; ?>" <?php echo ($record['patient_blood_type'] === $t) ? 'selected' : ''; ?>><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Units Needed</label>
            <input type="number" name="units_needed" min="1" value="<?php echo (int)$record['units_needed']; ?>">
        </div>
        <div class="form-group">
            <label>Urgency</label>
            <select name="urgency">
                <option value="normal" <?php echo ($record['urgency']==='normal'?'selected':''); ?>>Normal</option>
                <option value="urgent" <?php echo ($record['urgency']==='urgent'?'selected':''); ?>>Urgent</option>
                <option value="critical" <?php echo ($record['urgency']==='critical'?'selected':''); ?>>Critical</option>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="open" <?php echo ($record['status']==='open'?'selected':''); ?>>Open</option>
                <option value="fulfilled" <?php echo ($record['status']==='fulfilled'?'selected':''); ?>>Fulfilled</option>
                <option value="cancelled" <?php echo ($record['status']==='cancelled'?'selected':''); ?>>Cancelled</option>
            </select>
        </div>
        <div class="form-group">
            <label>Required Date</label>
            <input type="date" name="required_date" value="<?php echo htmlspecialchars($record['required_date'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>City</label>
            <input type="text" name="city" value="<?php echo htmlspecialchars($record['city'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>State</label>
            <input type="text" name="state" value="<?php echo htmlspecialchars($record['state'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Hospital Address</label>
            <textarea name="hospital_address"><?php echo htmlspecialchars($record['hospital_address'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes"><?php echo htmlspecialchars($record['notes'] ?? ''); ?></textarea>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn" style="width:100%;">Save Changes</button>
        <p class="text-center mt-2">
            <a href="<?php echo baseUrl(); ?>/admin/<?php echo $type==='request'?'manage_requests':('manage_'.$type.'s'); ?>.php">&larr; Back to list</a>
        </p>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
