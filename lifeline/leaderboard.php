<?php
require_once 'includes/functions.php';
$pageTitle = 'Donor Leaderboard';

// Get leaderboard data
$period = in_array($_GET['period'] ?? '', ['month', 'year']) ? $_GET['period'] : 'all';
$limit  = in_array((int)($_GET['limit'] ?? 50), [10, 25, 50, 100]) ? (int)$_GET['limit'] : 50;

$dateFilter = '';
if ($period === 'month') {
    $dateFilter = "AND dh.donation_date >= NOW() - INTERVAL 1 MONTH";
} elseif ($period === 'year') {
    $dateFilter = "AND dh.donation_date >= NOW() - INTERVAL 1 YEAR";
}

$pdoR = getReadPdo();
$leaderboard = $pdoR->query("
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
    WHERE u.is_active = true AND u.deleted_at IS NULL
    ORDER BY period_donations DESC, dp.total_donations DESC, dp.donation_points DESC
    LIMIT $limit
")->fetchAll();

// Get achievements count per donor
$achievements = [];
if (count($leaderboard) > 0) {
    $userIds = array_column($leaderboard, 'user_id');
    $in = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdoR->prepare("
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
<section class="leaderboard-hero">
    <div class="leaderboard-hero-bg"></div>
    <div class="relative z-1">
        <h1>Donor Leaderboard</h1>
        <p class="text-muted maxw-500 mx-auto">Celebrating our life-saving heroes. Rankings based on verified donations and community impact.</p>
    </div>
</section>

<!-- Podium for Top 3 -->
<?php if (count($top3) >= 3): ?>
<section class="podium-section mb-48">
    <div class="podium-container flex justify-center items-end gap-24 p-20">
        <!-- 2nd Place -->
        <div class="podium-item podium-2 text-center order-1">
            <div class="podium-avatar podium-avatar--silver">&#129352;</div>
            <div class="fw-700 text-primary fs-110"><?php echo htmlspecialchars($top3[1]['full_name']); ?></div>
            <div class="fs-85 text-muted"><?php echo (int)$top3[1]['period_donations']; ?> donations</div>
            <div class="fs-80 text-secondary fw-600 mt-4">&#9733; <?php echo ucfirst($top3[1]['tier']); ?></div>
        </div>

        <!-- 1st Place -->
        <div class="podium-item podium-1 text-center order-0 scale-115 mb-20">
            <div class="podium-avatar podium-avatar--gold">&#129351;</div>
            <div class="fw-700 text-primary fs-120"><?php echo htmlspecialchars($top3[0]['full_name']); ?></div>
            <div class="fs-85 text-muted"><?php echo (int)$top3[0]['period_donations']; ?> donations</div>
            <div class="fs-80 text-warning fw-600 mt-4">&#9733; <?php echo ucfirst($top3[0]['tier']); ?></div>
        </div>

        <!-- 3rd Place -->
        <div class="podium-item podium-3 text-center order-2">
            <div class="podium-avatar podium-avatar--bronze">&#129353;</div>
            <div class="fw-700 text-primary fs-100"><?php echo htmlspecialchars($top3[2]['full_name']); ?></div>
            <div class="fs-85 text-muted"><?php echo (int)$top3[2]['period_donations']; ?> donations</div>
            <div class="fs-80 tier-bronze fw-600 mt-4">&#9733; <?php echo ucfirst($top3[2]['tier']); ?></div>
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
<div class="card p-20">
    <form method="GET" class="flex flex-wrap gap-16 items-center justify-between">
        <div class="flex flex-wrap gap-16 items-center">
            <div class="form-group mb-0 minw-160">
                <select name="period" onchange="this.form.submit()">
                    <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                </select>
            </div>
            <div class="form-group mb-0 minw-140">
                <select name="limit" onchange="this.form.submit()">
                    <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>Top 10</option>
                    <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>Top 25</option>
                    <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>Top 50</option>
                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>Top 100</option>
                </select>
            </div>
        </div>
        <div class="text-muted fs-90">
            Showing <?php echo count($leaderboard); ?> donors
        </div>
    </form>
</div>

<!-- Leaderboard Table -->
<section class="card p-0 overflow-hidden">
    <div class="table-wrapper border-none">
    <table>
        <thead>
            <tr>
                <th class="w-60">Rank</th>
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
                        <span class="fs-140"><?php echo ['&#129351;', '&#129352;', '&#129353;'][$i]; ?></span>
                    <?php else: ?>
                        <span class="text-muted fw-700">#<?php echo $i + 1; ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="flex items-center gap-12">
                        <div class="avatar-initials-sm">
                            <?php echo htmlspecialchars(strtoupper(mb_substr($d['full_name'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="min-w-0">
                            <div class="fw-600 text-primary fs-90 ellipsis maxw-120"><?php echo htmlspecialchars($d['full_name']); ?></div>
                            <?php if ($d['is_verified']): ?>
                            <div class="fs-70 text-success">&#10003; Verified</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><span class="blood-badge py-4 px-8 fs-75"><?php echo htmlspecialchars($d['blood_type']); ?></span></td>
                <td class="hide-mobile text-muted fs-85"><?php echo htmlspecialchars(($d['city'] ? $d['city'] . ', ' : '') . $d['state']); ?></td>
                <td class="hide-xs">
                    <span class="tier-<?php echo $d['tier']; ?> fw-700 fs-80">
                        &#9733; <?php echo ucfirst($d['tier']); ?>
                    </span>
                </td>
                <td class="fw-700 text-primary fs-90"><?php echo (int)$d['period_donations']; ?></td>
                <td class="hide-mobile text-crimson-light fw-700 fs-90"><?php echo (int)$d['donation_points']; ?></td>
                <td class="hide-mobile text-muted fs-85"><?php echo $achievements[$d['user_id']] ?? 0; ?></td>
                <td class="hide-xs">
                    <?php if ($d['last_donation'] && strtotime($d['last_donation']) > strtotime('-90 days')): ?>
                        <span class="badge badge-open py-2 px-8 fs-70">Active</span>
                    <?php else: ?>
                        <span class="badge badge-normal py-2 px-8 fs-70">Inactive</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if (empty($leaderboard)): ?>
    <div class="empty-note">
        <div class="fs-300 mb-16">&#127942;</div>
        <h3 class="text-primary mb-8">No donors yet</h3>
        <p>Be the first to register and start saving lives!</p>
        <a href="<?php echo baseUrl(); ?>/register.php?role=donor" class="btn mt-16">Register as Donor</a>
    </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
