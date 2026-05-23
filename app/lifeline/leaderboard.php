<?php
require_once 'includes/functions.php';
$pageTitle = 'Donor Leaderboard';

// Get leaderboard data
$period = $_GET['period'] ?? 'all';
$limit = (int)($_GET['limit'] ?? 50);

$dateFilter = '';
if ($period === 'month') {
    $dateFilter = "AND dh.donation_date >= NOW() - INTERVAL 1 MONTH";
} elseif ($period === 'year') {
    $dateFilter = "AND dh.donation_date >= NOW() - INTERVAL 1 YEAR";
}

$leaderboard = $pdo->query("
    SELECT 
        dp.user_id,
        dp.full_name,
        dp.blood_type,
        dp.city,
        dp.state,
        dp.total_donations,
        dp.donation_points,
        dp.tier,
        dp.is_verified,
        (SELECT COUNT(*) FROM donation_history dh WHERE dh.donor_id = dp.user_id $dateFilter) as period_donations,
        (SELECT MAX(donation_date) FROM donation_history dh WHERE dh.donor_id = dp.user_id $dateFilter) as last_donation
    FROM donor_profiles dp
    JOIN users u ON dp.user_id = u.id
    WHERE u.is_active = true
    ORDER BY period_donations DESC, dp.total_donations DESC, dp.donation_points DESC
    LIMIT $limit
")->fetchAll();

// Get achievements count per donor
$achievements = [];
if (count($leaderboard) > 0) {
    $userIds = array_column($leaderboard, 'user_id');
    $in = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare("
        SELECT donor_id, COUNT(*) as count 
        FROM donor_matches 
        WHERE donor_id IN ($in) AND status = 'donated' 
        GROUP BY donor_id
    ");
    $stmt->execute($userIds);
    foreach ($stmt->fetchAll() as $a) {
        $achievements[$a['donor_id']] = $a['count'];
    }
}

// Top 3 for podium
$top3 = array_slice($leaderboard, 0, 3);

include 'includes/header.php';
?>

<!-- Hero -->
<section style="position: relative; padding: 80px 32px 40px; text-align: center; border-radius: 0 0 var(--radius-xl) var(--radius-xl); margin-bottom: 40px; overflow: hidden; background: linear-gradient(135deg, rgba(230,57,70,0.2) 0%, rgba(10,14,26,0.95) 100%);">
    <div style="position: absolute; inset: 0; background: url('<?php echo baseUrl(); ?>/assets/images/achievements-hero.jpg') center/cover; opacity: 0.15;"></div>
    <div style="position: relative; z-index: 1;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: clamp(2rem, 5vw, 3rem); margin-bottom: 12px;">Donor Leaderboard</h1>
        <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto;">Celebrating our life-saving heroes. Rankings based on verified donations and community impact.</p>
    </div>
</section>

<!-- Podium for Top 3 -->
<?php if (count($top3) >= 3): ?>
<section class="podium-section" style="margin-bottom: 48px;">
    <div class="podium-container" style="display: flex; justify-content: center; align-items: flex-end; gap: 24px; padding: 20px;">
        <!-- 2nd Place -->
        <div class="podium-item podium-2" style="text-align: center; order: 1;">
            <div style="width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, #94a3b8, #64748b); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; border: 4px solid var(--bg-dark-3); box-shadow: 0 4px 20px rgba(148,163,184,0.3); font-size: 2.5rem;">&#129352;</div>
            <div style="font-weight: 700; color: var(--text-primary); font-size: 1.1rem;"><?php echo htmlspecialchars($top3[1]['full_name']); ?></div>
            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo (int)$top3[1]['period_donations']; ?> donations</div>
            <div style="font-size: 0.8rem; color: #94a3b8; font-weight: 600; margin-top: 4px;">&#9733; <?php echo ucfirst($top3[1]['tier']); ?></div>
        </div>
        
        <!-- 1st Place -->
        <div class="podium-item podium-1" style="text-align: center; order: 0; transform: scale(1.15); margin-bottom: 20px;">
            <div style="width: 110px; height: 110px; border-radius: 50%; background: var(--gradient-gold); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; border: 4px solid var(--bg-dark-3); box-shadow: 0 4px 30px rgba(245,158,11,0.4); font-size: 3rem;">&#129351;</div>
            <div style="font-weight: 700; color: var(--text-primary); font-size: 1.2rem;"><?php echo htmlspecialchars($top3[0]['full_name']); ?></div>
            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo (int)$top3[0]['period_donations']; ?> donations</div>
            <div style="font-size: 0.8rem; color: #f59e0b; font-weight: 600; margin-top: 4px;">&#9733; <?php echo ucfirst($top3[0]['tier']); ?></div>
        </div>
        
        <!-- 3rd Place -->
        <div class="podium-item podium-3" style="text-align: center; order: 2;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #cd7f32, #a0522d); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; border: 4px solid var(--bg-dark-3); box-shadow: 0 4px 20px rgba(205,127,50,0.3); font-size: 2rem;">&#129353;</div>
            <div style="font-weight: 700; color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($top3[2]['full_name']); ?></div>
            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo (int)$top3[2]['period_donations']; ?> donations</div>
            <div style="font-size: 0.8rem; color: #cd7f32; font-weight: 600; margin-top: 4px;">&#9733; <?php echo ucfirst($top3[2]['tier']); ?></div>
        </div>
    </div>
</section>
<style>
    @media (max-width: 600px) {
        .podium-container {
            flex-direction: column !important;
            align-items: center !important;
            gap: 32px !important;
        }
        .podium-item {
            order: unset !important;
            transform: none !important;
            margin-bottom: 0 !important;
        }
        .podium-1 { order: 1 !important; }
        .podium-2 { order: 2 !important; }
        .podium-3 { order: 3 !important; }
    }
</style>
<?php endif; ?>

<!-- Filters -->
<div class="card" style="padding: 20px 28px;">
    <form method="GET" class="flex flex-wrap gap-2 items-center" style="justify-content: space-between;">
        <div class="flex flex-wrap gap-2 items-center">
            <div class="form-group" style="margin-bottom:0;min-width:160px;">
                <select name="period" onchange="this.form.submit()">
                    <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;min-width:140px;">
                <select name="limit" onchange="this.form.submit()">
                    <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>Top 10</option>
                    <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>Top 25</option>
                    <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>Top 50</option>
                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>Top 100</option>
                </select>
            </div>
        </div>
        <div style="color: var(--text-muted); font-size: 0.9rem;">
            Showing <?php echo count($leaderboard); ?> donors
        </div>
    </form>
</div>

<!-- Leaderboard Table -->
<section class="card" style="padding: 0; overflow: hidden;">
    <div class="table-wrapper" style="border: none;">
    <table>
        <thead>
            <tr>
                <th style="width: 60px;">Rank</th>
                <th>Donor</th>
                <th>Type</th>
                <th class="hide-mobile">Location</th>
                <th class="hide-xs">Tier</th>
                <th>Donations</th>
                <th class="hide-mobile">Points</th>
                <th class="hide-mobile">Badges</th>
                <th class="hide-xs">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leaderboard as $i => $d): ?>
            <tr>
                <td>
                    <?php if ($i < 3): ?>
                        <span style="font-size: 1.4rem;"><?php echo ['&#129351;', '&#129352;', '&#129353;'][$i]; ?></span>
                    <?php else: ?>
                        <span style="color: var(--text-muted); font-weight: 700;">#<?php echo $i + 1; ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--gradient-crimson); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.8rem; flex-shrink: 0;">
                            <?php echo strtoupper(substr($d['full_name'], 0, 1)); ?>
                        </div>
                        <div style="min-width: 0;">
                            <div style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;"><?php echo htmlspecialchars($d['full_name']); ?></div>
                            <?php if ($d['is_verified']): ?>
                            <div style="font-size: 0.7rem; color: var(--success);">&#10003; Verified</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><span class="blood-badge" style="padding: 4px 8px; font-size: 0.75rem;"><?php echo htmlspecialchars($d['blood_type']); ?></span></td>
                <td class="hide-mobile" style="color: var(--text-muted); font-size: 0.85rem;"><?php echo htmlspecialchars(($d['city'] ? $d['city'] . ', ' : '') . $d['state']); ?></td>
                <td class="hide-xs">
                    <span class="tier-<?php echo $d['tier']; ?>" style="font-weight: 700; font-size: 0.8rem;">
                        &#9733; <?php echo ucfirst($d['tier']); ?>
                    </span>
                </td>
                <td style="font-weight: 700; color: var(--text-primary); font-size: 0.9rem;"><?php echo (int)$d['period_donations']; ?></td>
                <td class="hide-mobile" style="color: var(--crimson-light); font-weight: 700; font-size: 0.9rem;"><?php echo (int)$d['donation_points']; ?></td>
                <td class="hide-mobile" style="color: var(--text-muted); font-size: 0.85rem;"><?php echo $achievements[$d['user_id']] ?? 0; ?></td>
                <td class="hide-xs">
                    <?php if ($d['last_donation'] && strtotime($d['last_donation']) > strtotime('-90 days')): ?>
                        <span class="badge badge-open" style="padding: 2px 8px; font-size: 0.7rem;">Active</span>
                    <?php else: ?>
                        <span class="badge badge-normal" style="padding: 2px 8px; font-size: 0.7rem;">Inactive</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if (empty($leaderboard)): ?>
    <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
        <div style="font-size: 3rem; margin-bottom: 16px;">&#127942;</div>
        <h3 style="color: var(--text-primary); margin-bottom: 8px;">No donors yet</h3>
        <p>Be the first to register and start saving lives!</p>
        <a href="<?php echo baseUrl(); ?>/register.php?role=donor" class="btn mt-2">Register as Donor</a>
    </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
