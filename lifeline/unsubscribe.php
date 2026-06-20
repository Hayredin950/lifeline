<?php
require_once 'includes/functions.php';

$token = trim($_GET['token'] ?? '');
$type  = trim($_GET['type']  ?? '');

$allowedTypes = ['blood_request'];
$success = false;
$error   = '';

if ($token === '' || !in_array($type, $allowedTypes, true)) {
    $error = 'Invalid unsubscribe link.';
} else {
    // Look up user by token.
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE unsubscribe_token = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Unsubscribe link is invalid or has expired.';
    } elseif ($user['role'] !== 'donor') {
        $error = 'This unsubscribe link applies to donor accounts only.';
    } else {
        $userId = (int)$user['id'];

        // Merge the opted-out type into existing prefs.
        $profStmt = $pdo->prepare("SELECT email_notif_prefs FROM donor_profiles WHERE user_id = ?");
        $profStmt->execute([$userId]);
        $existing = json_decode((string)$profStmt->fetchColumn(), true) ?: [];
        $existing[$type] = false;

        $pdo->prepare("UPDATE donor_profiles SET email_notif_prefs = ? WHERE user_id = ?")
            ->execute([json_encode($existing), $userId]);

        $success = true;
    }
}

include 'includes/header.php';
?>

<div class="card maxw-480 mx-auto my-40 text-center">
    <?php if ($success): ?>
        <h1>Unsubscribed</h1>
        <p class="text-muted">You have been removed from <strong><?php echo htmlspecialchars(str_replace('_', ' ', $type)); ?></strong> emails.</p>
        <p class="mt-20">
            <a href="<?php echo baseUrl(); ?>/donor/notification_prefs.php" class="btn btn-secondary">Manage All Preferences</a>
        </p>
    <?php elseif ($error): ?>
        <h1>Unsubscribe Failed</h1>
        <p class="text-danger-dark"><?php echo htmlspecialchars($error); ?></p>
        <p class="mt-20"><a href="<?php echo baseUrl(); ?>/index.php">&larr; Home</a></p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
