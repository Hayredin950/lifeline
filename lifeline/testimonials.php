<?php
require_once 'includes/functions.php';
$pageTitle = 'Stories';

$stmt = $pdo->query("SELECT * FROM testimonials WHERE is_approved = true ORDER BY created_at DESC");
$testimonials = $stmt->fetchAll();

include 'includes/header.php';
?>

<style>
.stories-hero {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    padding: 60px 0;
    text-align: center;
    margin: -20px -20px 40px;
    border-radius: 0 0 20px 20px;
}
.stories-hero h1 { color: #fff; font-size: 2.5rem; margin-bottom: 12px; }
.stories-hero p  { color: rgba(255,255,255,0.85); font-size: 1.1rem; max-width: 600px; margin: 0 auto; }

.stories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    margin-bottom: 60px;
}
.story-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    transition: transform .2s, box-shadow .2s;
}
.story-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.3);
}
.story-stars { color: #f59e0b; font-size: 1.2rem; margin-bottom: 14px; }
.story-text {
    color: var(--text-muted);
    font-size: 0.97rem;
    line-height: 1.7;
    margin-bottom: 20px;
    font-style: italic;
}
.story-author { display: flex; align-items: center; gap: 12px; }
.story-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 1.1rem; flex-shrink: 0;
}
.story-name { font-weight: 600; color: var(--text); font-size: 0.95rem; }
.story-date { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; }

.share-cta {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    margin-bottom: 40px;
}
.share-cta h2 { margin-bottom: 10px; }
.share-cta p { color: var(--text-muted); margin-bottom: 24px; }

.empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.empty-state .icon { font-size: 3rem; margin-bottom: 16px; }
</style>

<div class="stories-hero">
    <h1>&#10084; Stories That Saved Lives</h1>
    <p>Real people, real impact. Read how LifeLine connects donors and hospitals across Ethiopia every day.</p>
</div>

<?php if (empty($testimonials)): ?>
<div class="empty-state">
    <div class="icon">&#128172;</div>
    <h3>No stories yet</h3>
    <p>Be the first to share your experience with LifeLine.</p>
</div>
<?php else: ?>
<div class="stories-grid">
    <?php foreach ($testimonials as $t): ?>
    <div class="story-card">
        <div class="story-stars"><?php echo str_repeat('&#9733;', (int)$t['rating']); ?></div>
        <div class="story-text">"<?php echo htmlspecialchars($t['story']); ?>"</div>
        <div class="story-author">
            <div class="story-avatar"><?php echo mb_strtoupper(mb_substr($t['recipient_name'], 0, 1)); ?></div>
            <div>
                <div class="story-name"><?php echo htmlspecialchars($t['recipient_name']); ?></div>
                <div class="story-date"><?php echo date('M j, Y', strtotime($t['created_at'])); ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="share-cta">
    <h2>&#128221; Share Your Story</h2>
    <p>Did LifeLine help you or someone you love? Your story could inspire the next donor.</p>
    <?php if (isLoggedIn()): ?>
        <a href="<?php echo baseUrl(); ?>/donor/submit_testimonial.php" class="btn btn-large">Share Your Story</a>
    <?php else: ?>
        <a href="<?php echo baseUrl(); ?>/register.php" class="btn btn-large">Join LifeLine to Share</a>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
