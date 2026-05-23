<?php
require_once 'includes/functions.php';
$pageTitle = 'Home';

// Stats
$donorCount = $pdo->query("SELECT COUNT(*) FROM donor_profiles")->fetchColumn();
$hospitalCount = $pdo->query("SELECT COUNT(*) FROM hospital_profiles")->fetchColumn();
$requestCount = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'open'")->fetchColumn();
$fulfilledCount = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'fulfilled'")->fetchColumn();
$totalDonations = $pdo->query("SELECT COUNT(*) FROM donation_history")->fetchColumn();

// Recent urgent requests
$stmt = $pdo->query("
    SELECT br.*, hp.hospital_name 
    FROM blood_requests br
    LEFT JOIN hospital_profiles hp ON br.hospital_id = hp.user_id
    WHERE br.status = 'open'
    ORDER BY br.created_at DESC
    LIMIT 5
");
$recentRequests = $stmt->fetchAll();

// Featured donors for spotlight
$featuredDonors = $pdo->query("
    SELECT dp.full_name, dp.blood_type, dp.city, dp.is_available, dp.total_donations, dp.tier, dp.profile_pic, dp.gender
    FROM donor_profiles dp JOIN users u ON dp.user_id = u.id
    WHERE u.is_active = true AND dp.is_available = true
    ORDER BY COALESCE(dp.total_donations, 0) DESC, COALESCE(dp.last_donation_date, '1970-01-01') DESC
    LIMIT 5
")->fetchAll();

// Testimonials
$testimonials = $pdo->query("
    SELECT t.*, dp.full_name as donor_name
    FROM testimonials t
    LEFT JOIN donor_profiles dp ON t.donor_id = dp.user_id
    WHERE t.is_approved = true
    ORDER BY t.created_at DESC
    LIMIT 3
")->fetchAll();

// Blood type distribution for chart
$bloodDist = $pdo->query("
    SELECT blood_type, COUNT(*) as count FROM donor_profiles GROUP BY blood_type ORDER BY count DESC
")->fetchAll();

include 'includes/header.php';
?>

<canvas id="particle-canvas"></canvas>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-bg" style="background-image: url('<?php echo baseUrl(); ?>/assets/images/hero-bg.jpg');"></div>
    <div class="hero-content">
        <h1>Every Drop Counts.<br>Save a <span class="highlight">Life Today</span>.</h1>
        <p>Connecting hospitals with voluntary blood donors across India. Find compatible donors, create urgent requests, and save lives in emergencies using our advanced AI-powered matching system.</p>
        <div class="hero-actions">
            <a href="<?php echo baseUrl(); ?>/find_donors.php" class="btn btn-large">&#128269; Find Donors</a>
            <a href="<?php echo baseUrl(); ?>/register.php?role=donor" class="btn btn-large btn-glass">&#10084; Become a Donor</a>
            <a href="<?php echo baseUrl(); ?>/emergency.php" class="btn btn-large" style="background: linear-gradient(135deg, #dc2626, #991b1b);box-shadow: 0 4px 15px rgba(220,38,38,0.4);">&#9888; Emergency SOS</a>
        </div>
    </div>
    
    <!-- Animated Stats Bar -->
    <div class="hero-stats-bar">
        <div class="hero-stat">
            <div class="hero-stat-value" data-counter="<?php echo (int)$donorCount; ?>">0</div>
            <div class="hero-stat-label">Registered Donors</div>
        </div>
        <div class="hero-stat">
            <div class="hero-stat-value" data-counter="<?php echo (int)$hospitalCount; ?>">0</div>
            <div class="hero-stat-label">Partner Hospitals</div>
        </div>
        <div class="hero-stat">
            <div class="hero-stat-value" data-counter="<?php echo (int)$fulfilledCount; ?>">0</div>
            <div class="hero-stat-label">Lives Saved</div>
        </div>
        <div class="hero-stat">
            <div class="hero-stat-value" data-counter="<?php echo (int)$totalDonations; ?>">0</div>
            <div class="hero-stat-label">Total Donations</div>
        </div>
    </div>
</section>

<!-- How It Works -->
<div class="section-divider">
    <h2>How It Works</h2>
</div>
<section class="grid-4" style="margin-bottom: 40px;">
    <div class="feature-card" data-animate="animate-fade-up">
        <div class="feature-icon">&#128221;</div>
        <h3>1. Register</h3>
        <p>Sign up as a donor or hospital. Build your profile with blood type, location, and verification badges.</p>
    </div>
    <div class="feature-card" data-animate="animate-fade-up" style="animation-delay:0.1s">
        <div class="feature-icon">&#128228;</div>
        <h3>2. Request</h3>
        <p>Hospitals submit urgent blood requests with patient details. Our system geocodes locations instantly.</p>
    </div>
    <div class="feature-card" data-animate="animate-fade-up" style="animation-delay:0.2s">
        <div class="feature-icon">&#128269;</div>
        <h3>3. Match</h3>
        <p>AI-powered matching finds compatible donors nearby. Donors receive instant notifications.</p>
    </div>
    <div class="feature-card" data-animate="animate-fade-up" style="animation-delay:0.3s">
        <div class="feature-icon">&#128154;</div>
        <h3>4. Save Lives</h3>
        <p>Donors respond, blood is donated, and lives are saved. Earn achievements and track your impact.</p>
    </div>
</section>

<!-- Blood Type Explorer -->
<div class="section-divider">
    <h2>Blood Type Compatibility
        <span class="subtitle">Interactive guide to blood type matching</span>
    </h2>
</div>
<section class="card" style="margin-bottom: 40px;">
    <div class="blood-explorer">
        <div>
            <h3 style="margin-bottom: 20px; color: var(--text-primary);">Select a Blood Type</h3>
            <div class="blood-type-grid" id="bloodTypeGrid">
                <button class="blood-type-btn" onclick="showCompat('A+')"><span class="type-label">A</span><span class="type-rh">Positive</span></button>
                <button class="blood-type-btn" onclick="showCompat('A-')"><span class="type-label">A</span><span class="type-rh">Negative</span></button>
                <button class="blood-type-btn" onclick="showCompat('B+')"><span class="type-label">B</span><span class="type-rh">Positive</span></button>
                <button class="blood-type-btn" onclick="showCompat('B-')"><span class="type-label">B</span><span class="type-rh">Negative</span></button>
                <button class="blood-type-btn" onclick="showCompat('AB+')"><span class="type-label">AB</span><span class="type-rh">Positive</span></button>
                <button class="blood-type-btn" onclick="showCompat('AB-')"><span class="type-label">AB</span><span class="type-rh">Negative</span></button>
                <button class="blood-type-btn" onclick="showCompat('O+')"><span class="type-label">O</span><span class="type-rh">Positive</span></button>
                <button class="blood-type-btn" onclick="showCompat('O-')"><span class="type-label">O</span><span class="type-rh">Negative</span></button>
            </div>
        </div>
        <div class="compat-panel" id="compatPanel">
            <h3>&#9764; Click a blood type to see compatibility</h3>
            <p style="color: var(--text-muted);">Learn which blood types can donate to and receive from each other. Understanding compatibility is crucial for emergency transfusions.</p>
            
            <!-- Mini Blood Distribution Chart -->
            <div style="margin-top: 24px;">
                <h4 style="font-size:0.9rem;color:var(--text-muted);margin-bottom:12px;">Donor Distribution</h4>
                <div style="display:flex;align-items:flex-end;gap:8px;height:120px;padding-top:20px;">
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                        <div style="width:100%;background:var(--gradient-crimson);border-radius:6px 6px 0 0;min-height:4px;height:85px;"></div>
                        <span style="font-size:0.75rem;font-weight:700;color:var(--text-secondary);">A+</span>
                        <span style="font-size:0.65rem;color:var(--text-muted);">28</span>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                        <div style="width:100%;background:var(--gradient-crimson);border-radius:6px 6px 0 0;min-height:4px;height:62px;"></div>
                        <span style="font-size:0.75rem;font-weight:700;color:var(--text-secondary);">O+</span>
                        <span style="font-size:0.65rem;color:var(--text-muted);">37</span>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                        <div style="width:100%;background:var(--gradient-crimson);border-radius:6px 6px 0 0;min-height:4px;height:45px;"></div>
                        <span style="font-size:0.75rem;font-weight:700;color:var(--text-secondary);">B+</span>
                        <span style="font-size:0.65rem;color:var(--text-muted);">22</span>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                        <div style="width:100%;background:var(--gradient-crimson);border-radius:6px 6px 0 0;min-height:4px;height:28px;"></div>
                        <span style="font-size:0.75rem;font-weight:700;color:var(--text-secondary);">A-</span>
                        <span style="font-size:0.65rem;color:var(--text-muted);">7</span>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                        <div style="width:100%;background:var(--gradient-crimson);border-radius:6px 6px 0 0;min-height:4px;height:20px;"></div>
                        <span style="font-size:0.75rem;font-weight:700;color:var(--text-secondary);">B-</span>
                        <span style="font-size:0.65rem;color:var(--text-muted);">5</span>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                        <div style="width:100%;background:var(--gradient-crimson);border-radius:6px 6px 0 0;min-height:4px;height:18px;"></div>
                        <span style="font-size:0.75rem;font-weight:700;color:var(--text-secondary);">AB+</span>
                        <span style="font-size:0.65rem;color:var(--text-muted);">4</span>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                        <div style="width:100%;background:var(--gradient-crimson);border-radius:6px 6px 0 0;min-height:4px;height:15px;"></div>
                        <span style="font-size:0.75rem;font-weight:700;color:var(--text-secondary);">O-</span>
                        <span style="font-size:0.65rem;color:var(--text-muted);">3</span>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                        <div style="width:100%;background:var(--gradient-crimson);border-radius:6px 6px 0 0;min-height:4px;height:12px;"></div>
                        <span style="font-size:0.75rem;font-weight:700;color:var(--text-secondary);">AB-</span>
                        <span style="font-size:0.65rem;color:var(--text-muted);">2</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Recent Urgent Requests -->
<div class="section-divider">
    <h2>Urgent Blood Requests
        <span class="subtitle">Real-time emergency requests from hospitals</span>
    </h2>
</div>
<section class="card">
    <div class="card-header">
        <h2>Recent Requests</h2>
        <a href="<?php echo baseUrl(); ?>/find_donors.php" class="btn btn-small">View All</a>
    </div>
    <?php if (count($recentRequests) > 0): ?>
        <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Blood Type</th>
                    <th>Units</th>
                    <th>Urgency</th>
                    <th>Required By</th>
                    <th>Location</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRequests as $req): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($req['hospital_name']); ?></strong></td>
                    <td><span class="blood-badge"><?php echo htmlspecialchars($req['patient_blood_type']); ?></span></td>
                    <td><?php echo (int)$req['units_needed']; ?></td>
                    <td>
                        <span class="badge badge-<?php echo $req['urgency']; ?>">
                            <?php echo ucfirst($req['urgency']); ?>
                        </span>
                    </td>
                    <td><?php echo $req['required_date'] ? htmlspecialchars($req['required_date']) : 'ASAP'; ?></td>
                    <td><?php echo htmlspecialchars($req['city'] . ', ' . $req['state']); ?></td>
                    <td><a href="<?php echo baseUrl(); ?>/view_request.php?id=<?php echo (int)$req['id']; ?>" class="btn btn-small">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php else: ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px;">No open blood requests at the moment. <a href="<?php echo baseUrl(); ?>/emergency.php">Create an emergency request</a> if needed.</p>
    <?php endif; ?>
</section>

<!-- Donor Spotlight -->
<?php if (count($featuredDonors) > 0): ?>
<div class="section-divider">
    <h2>Donor Spotlight
        <span class="subtitle">Top heroes saving lives in our community</span>
    </h2>
</div>
<section class="donor-spotlight">
    <?php foreach ($featuredDonors as $i => $d): ?>
    <div class="donor-card" data-animate="animate-fade-up" style="animation-delay:<?php echo $i * 0.1; ?>s">
        <?php if ($i < 3): ?>
        <div class="donor-rank"><?php echo $i + 1; ?></div>
        <?php endif; ?>
        <img src="<?php echo getProfilePic($d); ?>" alt="Donor" class="donor-avatar" onerror="this.src='<?php echo baseUrl(); ?>/assets/images/default-avatar.png'">
        <h4><?php echo htmlspecialchars($d['full_name']); ?></h4>
        <span class="blood-badge"><?php echo htmlspecialchars($d['blood_type']); ?></span>
        <p class="donor-location"><?php echo htmlspecialchars($d['city']); ?></p>
        <div class="donor-stats">
            <span>&#127942; <?php echo (int)$d['total_donations']; ?> donations</span>
            <span class="tier-<?php echo $d['tier']; ?>">&#9733; <?php echo ucfirst($d['tier']); ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- Testimonials -->
<?php if (count($testimonials) > 0): ?>
<div class="section-divider">
    <h2>Success Stories
        <span class="subtitle">Real stories from our life-saving community</span>
    </h2>
</div>
<section class="grid-3" style="margin-bottom: 40px;">
    <?php foreach ($testimonials as $t): ?>
    <div class="testimonial-card" data-animate="animate-fade-up">
        <p class="testimonial-text"><?php echo htmlspecialchars($t['story']); ?></p>
        <div class="testimonial-author">
            <div class="testimonial-avatar" style="background:var(--gradient-crimson);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;">
                <?php echo strtoupper(substr($t['donor_name'] ?? 'A', 0, 1)); ?>
            </div>
            <div class="testimonial-info">
                <h4><?php echo htmlspecialchars($t['donor_name'] ?? $t['recipient_name'] ?? 'Anonymous'); ?></h4>
                <div class="testimonial-stars">
                    <?php echo str_repeat('&#9733;', $t['rating']); ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- CTA Section -->
<section class="card" style="text-align: center; padding: 60px 40px; background: linear-gradient(135deg, rgba(230,57,70,0.15) 0%, rgba(17,24,39,0.9) 100%); border: 1px solid rgba(230,57,70,0.2); position: relative; overflow: hidden;">
    <div style="position: absolute; inset: 0; background: url('<?php echo baseUrl(); ?>/assets/images/community-hero.jpg') center/cover; opacity: 0.15;"></div>
    <div style="position: relative; z-index: 1;">
        <h2 style="font-family: 'Playfair Display', serif; font-size: clamp(1.8rem, 4vw, 2.5rem); margin-bottom: 16px;">Ready to Save a Life?</h2>
        <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 32px; font-size: 1.05rem;">Join thousands of donors who are making a difference. Registration takes less than 2 minutes and you can start receiving match notifications immediately.</p>
        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo baseUrl(); ?>/register.php?role=donor" class="btn btn-large">&#10084; Register as Donor</a>
            <a href="<?php echo baseUrl(); ?>/register.php?role=hospital" class="btn btn-large btn-glass">&#127973; Register Hospital</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
