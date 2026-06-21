<?php
require_once '../includes/functions.php';
requireDonor();

$userId  = $_SESSION['user_id'];
$profile = getDonorProfile($pdo, $userId);

// Donors with at least one recorded donation may submit a testimonial.
$donated = (int)($profile['total_donations'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    if (!$donated) {
        setFlash('You need at least one completed donation to share a story.', 'danger');
        redirect(baseUrl() . '/donor/submit_testimonial.php');
    }

    $recipientName = trim($_POST['recipient_name'] ?? '');
    $story         = trim($_POST['story'] ?? '');
    $rating        = (int)($_POST['rating'] ?? 5);

    $errors = [];
    if ($story === '') $errors[] = 'Story is required.';
    if (mb_strlen($story) > 2000) $errors[] = 'Story must be 2 000 characters or fewer.';
    if ($rating < 1 || $rating > 5) $rating = 5;

    if ($errors) {
        setFlash(implode('<br>', $errors), 'danger');
        redirect(baseUrl() . '/donor/submit_testimonial.php');
    }

    $stmt = $pdo->prepare("
        INSERT INTO testimonials (donor_id, recipient_name, story, rating, is_approved)
        VALUES (?, ?, ?, ?, 0)
    ");
    $stmt->execute([$userId, $recipientName ?: null, $story, $rating]);

    setFlash('Thank you! Your story has been submitted and will appear once reviewed by our team.', 'success');
    redirect(baseUrl() . '/donor/dashboard.php');
}

include '../includes/header.php';
?>

<div class="card maxw-650 mx-auto my-30">
    <h1>Share Your Story</h1>
    <p class="text-muted mb-20">Tell the community about your donation experience. Stories are reviewed before being published.</p>

    <?php if (!$donated): ?>
        <div class="alert alert-warning">
            You need at least one completed donation to share a story.
            <a href="<?php echo baseUrl(); ?>/donor/dashboard.php">&larr; Back to Dashboard</a>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <div class="form-group">
                <label for="recipient_name">Recipient Name (optional)</label>
                <input type="text" id="recipient_name" name="recipient_name" maxlength="120"
                       placeholder="e.g. Rahul, or leave blank"
                       value="<?php echo htmlspecialchars($_POST['recipient_name'] ?? ''); ?>">
                <small class="field-hint">Name of the patient or recipient if you know it.</small>
            </div>

            <div class="form-group">
                <label for="story">Your Story <span class="text-crimson">*</span></label>
                <textarea id="story" name="story" rows="6" maxlength="2000" required
                          placeholder="Describe your donation experience..."><?php echo htmlspecialchars($_POST['story'] ?? ''); ?></textarea>
                <small class="field-hint">Max 2 000 characters.</small>
            </div>

            <div class="form-group">
                <label>Overall Experience</label>
                <div class="flex gap-12 mt-6">
                    <?php for ($i = 1; $i <= 5; $i++):
                        $checked = ((int)($_POST['rating'] ?? 5) === $i) ? 'checked' : '';
                    ?>
                    <label class="flex items-center gap-4">
                        <input type="radio" name="rating" value="<?php echo $i; ?>" <?php echo $checked; ?>>
                        <?php echo $i; ?> ★
                    </label>
                    <?php endfor; ?>
                </div>
            </div>

            <button type="submit" class="btn w-full">Submit Story</button>
            <p class="text-center mt-16">
                <a href="<?php echo baseUrl(); ?>/donor/dashboard.php">&larr; Back to Dashboard</a>
            </p>
        </form>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
