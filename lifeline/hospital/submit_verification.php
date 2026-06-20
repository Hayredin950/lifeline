<?php
/**
 * Hospital submits a verification document (license scan, registration cert, etc.)
 * Uploaded file goes to the uploads/verification/ directory (outside web root ideally;
 * here it is at uploads/verification/ with an .htaccess engine-off).
 */
require_once '../includes/functions.php';
requireHospital();

$userId  = (int)$_SESSION['user_id'];
$profile = $pdo->prepare("SELECT hospital_name, verification_status, verification_note FROM hospital_profiles WHERE user_id = ?");
$profile->execute([$userId]);
$hp = $profile->fetch();

if (!$hp) {
    setFlash('Hospital profile not found.', 'danger');
    redirect(baseUrl() . '/hospital/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    if ($hp['verification_status'] === 'approved') {
        setFlash('Your hospital is already verified.', 'info');
        redirect(baseUrl() . '/hospital/submit_verification.php');
    }

    if (empty($_FILES['document']['tmp_name'])) {
        setFlash('Please select a document to upload.', 'danger');
        redirect(baseUrl() . '/hospital/submit_verification.php');
    }

    $file    = $_FILES['document'];
    $maxSize = 5 * 1024 * 1024;  // 5 MB

    if ($file['size'] > $maxSize) {
        setFlash('File too large (max 5 MB).', 'danger');
        redirect(baseUrl() . '/hospital/submit_verification.php');
    }

    // Whitelist MIME types.
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowed  = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($mimeType, $allowed, true)) {
        setFlash('Only PDF, JPEG, or PNG files are accepted.', 'danger');
        redirect(baseUrl() . '/hospital/submit_verification.php');
    }

    $extMap = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    $ext    = $extMap[$mimeType];

    $uploadDir = __DIR__ . '/../../uploads/verification/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0750, true);
        file_put_contents($uploadDir . '.htaccess', "Deny from all\n");
    }

    $filename = 'hosp_' . $userId . '_' . time() . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        setFlash('File upload failed. Please try again.', 'danger');
        redirect(baseUrl() . '/hospital/submit_verification.php');
    }

    $pdo->prepare("
        UPDATE hospital_profiles
        SET verification_status = 'pending', verification_doc = ?, verification_note = NULL
        WHERE user_id = ?
    ")->execute([$filename, $userId]);

    auditLog($pdo, 'verification_submitted', 'hospital_profile', $userId, null, ['file' => $filename]);
    setFlash('Verification document submitted. An admin will review it shortly.', 'success');
    redirect(baseUrl() . '/hospital/submit_verification.php');
}

include '../includes/header.php';
?>

<div class="card" style="max-width:560px;margin:2rem auto">
    <div class="card-header">
        <h1>Hospital Verification</h1>
        <a href="<?php echo baseUrl(); ?>/hospital/dashboard.php" class="btn btn-secondary">Back</a>
    </div>

    <?php if ($hp['verification_status'] === 'approved'): ?>
        <p class="text-success-dark fw-600">
            Your hospital has been verified. A verified badge appears in donor-facing listings.
        </p>

    <?php elseif ($hp['verification_status'] === 'pending'): ?>
        <p class="text-muted">
            Your document is under review. You will be notified once a decision is made.
        </p>

    <?php elseif ($hp['verification_status'] === 'rejected'): ?>
        <div class="alert alert-danger mb-16" role="alert">
            <strong>Verification rejected.</strong>
            <?php if ($hp['verification_note']): ?>
                <br>Reason: <?php echo htmlspecialchars($hp['verification_note']); ?>
            <?php endif; ?>
        </div>
        <p>Please re-upload a corrected document below.</p>

    <?php else: ?>
        <p class="mb-16 text-muted">
            Upload a copy of your hospital license or registration certificate (PDF, JPEG, or PNG, max 5 MB).
            Once approved, a verified badge will appear next to your hospital in all listings.
        </p>
    <?php endif; ?>

    <?php if (!in_array($hp['verification_status'], ['approved', 'pending'], true)): ?>
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <div class="form-group">
            <label for="document">Verification Document</label>
            <input type="file" id="document" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
            <small class="field-hint">PDF, JPEG, or PNG — max 5 MB</small>
        </div>
        <button type="submit" class="btn">Submit for Verification</button>
    </form>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
