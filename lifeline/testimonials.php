<?php
require_once 'includes/functions.php';
$pageTitle = 'Stories';

// JOIN donor_profiles so we have the donor's real name and blood type
$stmt = $pdo->query("
    SELECT t.*, dp.full_name AS donor_full_name, dp.blood_type AS donor_blood_type, dp.city AS donor_city
    FROM testimonials t
    LEFT JOIN donor_profiles dp ON t.donor_id = dp.user_id
    WHERE t.is_approved = 1
    ORDER BY t.created_at DESC
");
$testimonials = $stmt->fetchAll();

include 'includes/header.php';
?>

<style>
/* ── Stories page ────────────────────────────────────────────────────────── */
.stories-hero {
    position: relative;
    padding: 64px 40px 56px;
    text-align: center;
    margin-bottom: 40px;
    border-radius: 20px;
    overflow: hidden;
    background: linear-gradient(135deg, #1a0505 0%, #2d0a0a 50%, #1a0505 100%);
    border: 1px solid rgba(185,28,28,.2);
}
.stories-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 70% 60% at 50% 0%, rgba(185,28,28,.2) 0%, transparent 70%);
    pointer-events: none;
}
.stories-hero-tag {
    display: inline-block;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #ef4444;
    background: rgba(185,28,28,.12);
    border: 1px solid rgba(185,28,28,.3);
    border-radius: 999px;
    padding: 4px 14px;
    margin-bottom: 20px;
}
.stories-hero h1 {
    font-size: clamp(1.8rem, 5vw, 2.8rem);
    font-weight: 800;
    color: #fff;
    margin-bottom: 14px;
    letter-spacing: -.02em;
    line-height: 1.15;
}
.stories-hero p {
    color: rgba(255,255,255,.6);
    font-size: 1.05rem;
    max-width: 520px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Story grid */
.stories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    margin-bottom: 48px;
}

/* Premium story card */
.story-card {
    background: var(--card-bg, #161625);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 20px;
    padding: 32px 28px 24px;
    display: flex;
    flex-direction: column;
    gap: 0;
    position: relative;
    overflow: hidden;
    transition: border-color .2s, transform .25s, box-shadow .25s;
}
.story-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #b91c1c 0%, #f87171 100%);
    opacity: 0;
    transition: opacity .2s;
}
.story-card:hover {
    border-color: rgba(185,28,28,.3);
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(0,0,0,.35);
}
.story-card:hover::before { opacity: 1; }

.story-quote-icon {
    font-size: 3rem;
    line-height: 1;
    color: rgba(185,28,28,.25);
    margin-bottom: 16px;
    font-family: Georgia, serif;
    user-select: none;
}
.story-stars {
    display: flex;
    gap: 3px;
    margin-bottom: 16px;
}
.story-star { color: #f59e0b; font-size: 1rem; }
.story-star.empty { color: rgba(255,255,255,.15); }
.story-text {
    color: rgba(255,255,255,.72);
    font-size: .95rem;
    line-height: 1.75;
    flex: 1;
    margin-bottom: 24px;
}
.story-divider {
    height: 1px;
    background: rgba(255,255,255,.06);
    margin-bottom: 20px;
}
.story-author { display: flex; align-items: center; gap: 14px; }
.story-avatar {
    width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #b91c1c, #7f1d1d);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 800; font-size: 1.1rem;
    border: 2px solid rgba(185,28,28,.3);
}
.story-author-info { min-width: 0; }
.story-author-name {
    font-weight: 700;
    color: #e2e8f0;
    font-size: .92rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.story-author-meta {
    font-size: .78rem;
    color: rgba(255,255,255,.35);
    margin-top: 2px;
}
.story-blood-badge {
    margin-left: auto;
    flex-shrink: 0;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 700;
    background: rgba(185,28,28,.15);
    border: 1px solid rgba(185,28,28,.3);
    color: #ef4444;
}

/* CTA banner */
.stories-cta {
    background: linear-gradient(135deg, #1a0505 0%, #2d0a0a 100%);
    border: 1px solid rgba(185,28,28,.2);
    border-radius: 20px;
    padding: 48px 40px;
    text-align: center;
    position: relative;
    overflow: hidden;
    margin-bottom: 40px;
}
.stories-cta::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 50% 60% at 50% 100%, rgba(185,28,28,.15) 0%, transparent 70%);
    pointer-events: none;
}
.stories-cta h2 { font-size: 1.6rem; font-weight: 800; color: #fff; margin-bottom: 10px; }
.stories-cta p { color: rgba(255,255,255,.55); margin-bottom: 28px; line-height: 1.6; }

/* Empty state */
.stories-empty {
    text-align: center;
    padding: 80px 20px;
    color: rgba(255,255,255,.4);
}
.stories-empty-icon { font-size: 3.5rem; margin-bottom: 16px; }
.stories-empty h3 { color: rgba(255,255,255,.6); margin-bottom: 8px; }

@media (max-width: 600px) {
    .stories-hero { padding: 44px 20px 40px; }
    .stories-cta { padding: 36px 20px; }
    .story-card { padding: 24px 20px 20px; }
}
</style>

<!-- Hero -->
<div class="stories-hero">
    <div class="stories-hero-tag">Community Stories</div>
    <h1>Lives Changed,<br>Stories Shared</h1>
    <p>Real voices from donors across Ethiopia who gave the gift of life and the difference they made.</p>
</div>

<!-- Stories grid -->
<?php if (empty($testimonials)): ?>
<div class="stories-empty">
    <div class="stories-empty-icon">&#128172;</div>
    <h3>No stories yet</h3>
    <p>Be the first to share your donation experience with the community.</p>
</div>
<?php else: ?>
<div class="stories-grid">
    <?php foreach ($testimonials as $t):
        $donorName = $t['donor_full_name'] ?? $t['recipient_name'] ?? 'Anonymous';
        $initial   = strtoupper(mb_substr($donorName, 0, 1, 'UTF-8')) ?: 'A';
        $bloodType = $t['donor_blood_type'] ?? null;
        $city      = $t['donor_city'] ?? null;
        $rating    = max(1, min(5, (int)($t['rating'] ?? 5)));
        $dateStr   = date('M j, Y', strtotime($t['created_at']));
    ?>
    <div class="story-card">
        <div class="story-quote-icon">&ldquo;</div>
        <div class="story-stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="story-star <?php echo $i > $rating ? 'empty' : ''; ?>">&#9733;</span>
            <?php endfor; ?>
        </div>
        <div class="story-text"><?php echo htmlspecialchars($t['story']); ?></div>
        <div class="story-divider"></div>
        <div class="story-author">
            <div class="story-avatar"><?php echo htmlspecialchars($initial); ?></div>
            <div class="story-author-info">
                <div class="story-author-name"><?php echo htmlspecialchars($donorName); ?></div>
                <div class="story-author-meta">
                    <?php echo $city ? htmlspecialchars($city) . ' &middot; ' : ''; ?>
                    <?php echo $dateStr; ?>
                </div>
            </div>
            <?php if ($bloodType): ?>
            <div class="story-blood-badge"><?php echo htmlspecialchars($bloodType); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- CTA -->
<div class="stories-cta">
    <h2>Your Story Could Inspire the Next Donor</h2>
    <p>Did LifeLine help you or someone you love? Share your experience and encourage others to give blood.</p>
    <?php if (isDonor()): ?>
        <a href="<?php echo baseUrl(); ?>/donor/submit_testimonial.php" class="btn btn-large">Share Your Story</a>
    <?php elseif (!isLoggedIn()): ?>
        <a href="<?php echo baseUrl(); ?>/register.php?role=donor" class="btn btn-large">Join LifeLine</a>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
