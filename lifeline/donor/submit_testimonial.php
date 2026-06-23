<?php
require_once '../includes/functions.php';
requireDonor();

$userId  = $_SESSION['user_id'];
$profile = getDonorProfile($pdo, $userId);

// Donors with at least one recorded donation may submit a testimonial.
$donated = (int)($profile['total_donations'] ?? 0);

// Check if we're editing an existing testimonial
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$testimonial = null;
if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ? AND donor_id = ?");
    $stmt->execute([$editId, $userId]);
    $testimonial = $stmt->fetch();
    if (!$testimonial) {
        setFlash('Testimonial not found.', 'danger');
        redirect(baseUrl() . '/donor/dashboard.php');
    }
}

// Handle delete request
if (isset($_POST['delete']) && $_POST['delete'] && isset($_POST['testimonial_id'])) {
    validateCsrf();
    $delId = (int)$_POST['testimonial_id'];
    $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ? AND donor_id = ?");
    $stmt->execute([$delId, $userId]);
    setFlash('Your story has been deleted.', 'success');
    redirect(baseUrl() . '/donor/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete'])) {
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
        redirect(baseUrl() . '/donor/submit_testimonial.php' . ($editId ? '?edit=' . $editId : ''));
    }

    if ($testimonial) {
        // Update existing
        $stmt = $pdo->prepare("
            UPDATE testimonials
            SET recipient_name = ?, story = ?, rating = ?, is_approved = 0
            WHERE id = ? AND donor_id = ?
        ");
        $stmt->execute([$recipientName ?: null, $story, $rating, $editId, $userId]);
        setFlash('Your story has been updated and will be re-reviewed.', 'success');
    } else {
        // Create new
        $stmt = $pdo->prepare("
            INSERT INTO testimonials (donor_id, recipient_name, story, rating, is_approved)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$userId, $recipientName ?: null, $story, $rating]);
        setFlash('Thank you! Your story has been submitted and will appear once reviewed by our team.', 'success');
    }

    redirect(baseUrl() . '/donor/dashboard.php');
}

include '../includes/header.php';
?>

<div class="card maxw-650 mx-auto my-30">
    <h1><?php echo $testimonial ? 'Edit Your Story' : 'Share Your Story'; ?></h1>
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
                       value="<?php echo htmlspecialchars($testimonial['recipient_name'] ?? $_POST['recipient_name'] ?? ''); ?>">
                <small class="field-hint">Name of the patient or recipient if you know it.</small>
            </div>

            <div class="form-group">
                <label for="story">Your Story <span class="text-crimson">*</span></label>
                <textarea id="story" name="story" rows="6" maxlength="2000" required
                          placeholder="Describe your donation experience..."><?php echo htmlspecialchars($testimonial['story'] ?? $_POST['story'] ?? ''); ?></textarea>
                <small class="field-hint">Max 2 000 characters.</small>
            </div>

            <div class="form-group">
                <label>Overall Experience</label>
                <div class="flex gap-12 mt-6">
                    <?php for ($i = 1; $i <= 5; $i++):
                        $checked = ((int)($testimonial['rating'] ?? $_POST['rating'] ?? 5) === $i) ? 'checked' : '';
                    ?>
                    <label class="flex items-center gap-4">
                        <input type="radio" name="rating" value="<?php echo $i; ?>" <?php echo $checked; ?>>
                        <?php echo $i; ?> ★
                    </label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="btn w-full"><?php echo $testimonial ? 'Update Story' : 'Submit Story'; ?></button>
                <?php if ($testimonial): ?>
                    <a href="<?php echo baseUrl(); ?>/donor/submit_testimonial.php" class="btn btn-secondary w-full">Cancel</a>
                <?php endif; ?>
            </div>
            <p class="text-center mt-16">
                <a href="<?php echo baseUrl(); ?>/donor/dashboard.php">&larr; Back to Dashboard</a>
            </p>
        </form>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
