<?php
/**
 * Email-change confirmation endpoint (DEF-07).
 * Consumes the tokened link sent to a user's NEW address and, only on a valid,
 * unexpired token, swaps the account email. The token is single-use.
 */
require_once 'includes/functions.php';

$rawToken = $_GET['token'] ?? '';
$ok = false;
$message = 'This confirmation link is invalid or has expired. Please request the change again from your profile.';

if ($rawToken !== '' && ctype_xdigit($rawToken)) {
    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare("
        SELECT id, user_id, new_email
        FROM email_change_requests
        WHERE token_hash = ? AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $req = $stmt->fetch();

    if ($req) {
        // Re-check the address is still free at confirmation time.
        $taken = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $taken->execute([$req['new_email'], $req['user_id']]);

        if ($taken->fetch()) {
            $message = 'That email address has since been taken by another account. Please choose a different one.';
            $pdo->prepare("DELETE FROM email_change_requests WHERE id = ?")->execute([$req['id']]);
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")
                    ->execute([$req['new_email'], $req['user_id']]);
                $pdo->prepare("DELETE FROM email_change_requests WHERE user_id = ?")
                    ->execute([$req['user_id']]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            auditLog($pdo, 'email_change_confirmed', 'user', (int)$req['user_id'], null, ['new_email' => $req['new_email']]);

            // If the confirming user is the one logged in, refresh their session email.
            if (isLoggedIn() && (int)$_SESSION['user_id'] === (int)$req['user_id']) {
                $_SESSION['email'] = $req['new_email'];
            }

            $ok = true;
            $message = 'Your email address has been updated successfully. You can now use it to log in.';
        }
    }
}

$pageTitle = 'Email Verification';
include 'includes/header.php';
?>

<div class="card maxw-520 mx-auto my-60 text-center">
    <h1><?php echo $ok ? '&#9989; Email Confirmed' : '&#9888; Verification Failed'; ?></h1>
    <p class="mt-12"><?php echo htmlspecialchars($message); ?></p>
    <p class="mt-20">
        <a href="<?php echo baseUrl(); ?>/<?php echo isLoggedIn() ? 'index.php' : 'login.php'; ?>" class="btn">
            <?php echo isLoggedIn() ? 'Back to Home' : 'Go to Login'; ?>
        </a>
    </p>
</div>

<?php include 'includes/footer.php'; ?>
