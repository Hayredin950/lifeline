<?php
require_once '../includes/functions.php';
requireDonor();

$userId  = $_SESSION['user_id'];
$profile = getDonorProfile($pdo, $userId);

// Ensure unsubscribe token exists (lazy generation for older accounts).
$stmt = $pdo->prepare("SELECT unsubscribe_token FROM users WHERE id = ?");
$stmt->execute([$userId]);
$token = $stmt->fetchColumn();
if (!$token) {
    do {
        $token = bin2hex(random_bytes(32));
        $u = $pdo->prepare("UPDATE users SET unsubscribe_token = ? WHERE id = ? AND unsubscribe_token IS NULL");
        $u->execute([$token, $userId]);
    } while ($u->rowCount() === 0);
}

// Parse existing prefs (null = all enabled by default).
$prefs = [];
if (!empty($profile['email_notif_prefs'])) {
    $prefs = json_decode($profile['email_notif_prefs'], true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $newPrefs = [
        'blood_request' => isset($_POST['notif_blood_request']),
    ];

    $pdo->prepare("UPDATE donor_profiles SET email_notif_prefs = ? WHERE user_id = ?")
        ->execute([json_encode($newPrefs), $userId]);

    setFlash('Notification preferences saved.', 'success');
    redirect(baseUrl() . '/donor/notification_prefs.php');
}

$bloodRequestEnabled = $prefs['blood_request'] ?? true;

include '../includes/header.php';
?>

<div class="card maxw-560 mx-auto my-30">
    <h1>Notification Preferences</h1>
    <p class="text-muted mb-20">Control which email notifications you receive from LifeLine.</p>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <div class="form-group">
            <label class="flex items-center gap-10">
                <input type="checkbox" name="notif_blood_request" value="1"
                       <?php echo $bloodRequestEnabled ? 'checked' : ''; ?>>
                <span>
                    <strong>Blood request alerts</strong><br>
                    <span class="fs-85 text-muted">Get emailed when a hospital needs your blood type.</span>
                </span>
            </label>
        </div>

        <p class="fs-85 text-muted mt-4">
            Account emails (password resets, email verification) are always sent regardless of these settings.
        </p>

        <button type="submit" class="btn w-full mt-16">Save Preferences</button>
    </form>

    <hr class="my-20">

    <h3 class="mb-8">Unsubscribe Link</h3>
    <p class="fs-85 text-muted mb-8">
        Share this link to instantly opt out of blood-request emails without logging in
        (e.g. from an email footer).
    </p>
    <?php $unsubUrl = fullBaseUrl() . '/unsubscribe.php?token=' . urlencode($token) . '&type=blood_request'; ?>
    <code class="fs-80 d-block p-10" style="word-break:break-all; background:var(--color-bg-alt); border-radius:4px;">
        <?php echo htmlspecialchars($unsubUrl); ?>
    </code>

    <p class="text-center mt-20">
        <a href="<?php echo baseUrl(); ?>/donor/dashboard.php">&larr; Back to Dashboard</a>
    </p>
</div>

<?php include '../includes/footer.php'; ?>
