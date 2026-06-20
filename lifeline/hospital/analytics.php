<?php
/**
 * Hospital analytics dashboard.
 * Metrics: request volume, fulfillment rate, avg time-to-fill, blood type demand,
 * urgency breakdown, top donor cities, recent matches.
 */
require_once '../includes/functions.php';
requireHospital();

$hospitalId = (int)$_SESSION['user_id'];
$pdoR       = getReadPdo();

// ── Request volume (last 12 months by month) ──────────────────────────────
$volumeStmt = $pdoR->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total,
           SUM(status = 'fulfilled') AS fulfilled,
           SUM(status = 'open') AS open_count
    FROM blood_requests
    WHERE hospital_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month
");
$volumeStmt->execute([$hospitalId]);
$volumeRows = $volumeStmt->fetchAll();

// ── Fulfillment rate overall ───────────────────────────────────────────────
$rateStmt = $pdoR->prepare("
    SELECT COUNT(*) AS total, SUM(status = 'fulfilled') AS fulfilled
    FROM blood_requests WHERE hospital_id = ?
");
$rateStmt->execute([$hospitalId]);
$rateRow  = $rateStmt->fetch();
$totalReq = (int)$rateRow['total'];
$fulfilled = (int)$rateRow['fulfilled'];
$fulfillRate = $totalReq > 0 ? round($fulfilled / $totalReq * 100, 1) : 0;

// ── Avg time-to-fill (days) — from created_at to date of first 'donated' match ──
$ttfStmt = $pdoR->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, br.created_at, dm.updated_at)) AS avg_hours
    FROM blood_requests br
    JOIN donor_matches dm ON dm.request_id = br.id AND dm.status = 'donated'
    WHERE br.hospital_id = ? AND br.status = 'fulfilled'
");
$ttfStmt->execute([$hospitalId]);
$avgHours = (float)($ttfStmt->fetchColumn() ?? 0);
$avgDays  = $avgHours > 0 ? round($avgHours / 24, 1) : null;

// ── Blood type demand breakdown ───────────────────────────────────────────
$btStmt = $pdoR->prepare("
    SELECT patient_blood_type, COUNT(*) AS cnt
    FROM blood_requests WHERE hospital_id = ?
    GROUP BY patient_blood_type ORDER BY cnt DESC
");
$btStmt->execute([$hospitalId]);
$btRows = $btStmt->fetchAll();

// ── Urgency breakdown ─────────────────────────────────────────────────────
$urgStmt = $pdoR->prepare("
    SELECT urgency, COUNT(*) AS cnt FROM blood_requests WHERE hospital_id = ?
    GROUP BY urgency ORDER BY FIELD(urgency,'critical','urgent','routine')
");
$urgStmt->execute([$hospitalId]);
$urgRows = $urgStmt->fetchAll();

// ── Top donor cities ──────────────────────────────────────────────────────
$cityStmt = $pdoR->prepare("
    SELECT dp.city, COUNT(*) AS cnt
    FROM donor_matches dm
    JOIN donor_profiles dp ON dm.donor_id = dp.user_id
    JOIN blood_requests br ON dm.request_id = br.id
    WHERE br.hospital_id = ? AND dm.status = 'donated'
    GROUP BY dp.city ORDER BY cnt DESC LIMIT 5
");
$cityStmt->execute([$hospitalId]);
$cityRows = $cityStmt->fetchAll();

// ── Recent matches ────────────────────────────────────────────────────────
$recentStmt = $pdoR->prepare("
    SELECT dm.status, dm.updated_at, dp.full_name, dp.blood_type, br.urgency
    FROM donor_matches dm
    JOIN donor_profiles dp ON dm.donor_id = dp.user_id
    JOIN blood_requests br ON dm.request_id = br.id
    WHERE br.hospital_id = ?
    ORDER BY dm.updated_at DESC LIMIT 10
");
$recentStmt->execute([$hospitalId]);
$recentMatches = $recentStmt->fetchAll();

$profile = getHospitalProfile($pdoR, $hospitalId);

include '../includes/header.php';
?>

<div class="card-header mb-20">
    <h1><?php echo htmlspecialchars($profile['hospital_name']); ?> — Analytics</h1>
    <a href="<?php echo baseUrl(); ?>/hospital/dashboard.php" class="btn btn-secondary">Back</a>
</div>

<!-- KPI strip -->
<div class="stats-grid mb-24">
    <div class="stat-card">
        <div class="stat-value"><?php echo $totalReq; ?></div>
        <div class="stat-label">Total Requests</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $fulfilled; ?></div>
        <div class="stat-label">Fulfilled</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $fulfillRate; ?>%</div>
        <div class="stat-label">Fulfillment Rate</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $avgDays !== null ? $avgDays . 'd' : '—'; ?></div>
        <div class="stat-label">Avg Time to Fill</div>
    </div>
</div>

<div class="grid-2-col gap-20">

    <!-- Monthly volume bar chart (CSS-only) -->
    <div class="card">
        <h3>Monthly Requests (12 mo)</h3>
        <?php if ($volumeRows): ?>
        <div class="chart-bars mt-12">
            <?php
            $maxVol = max(1, ...array_column($volumeRows, 'total'));
            foreach ($volumeRows as $r):
                $pct = round($r['total'] / $maxVol * 100);
            ?>
            <div class="chart-bar-wrap" title="<?php echo htmlspecialchars($r['month']); ?>: <?php echo $r['total']; ?> requests">
                <div class="chart-bar bg-crimson" style="height:<?php echo $pct; ?>%"></div>
                <div class="chart-label"><?php echo substr($r['month'], 5); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted">No data yet.</p>
        <?php endif; ?>
    </div>

    <!-- Blood type demand table -->
    <div class="card">
        <h3>Blood Type Demand</h3>
        <?php if ($btRows): ?>
        <table class="mt-12">
            <thead><tr><th>Blood Type</th><th>Requests</th><th>Share</th></tr></thead>
            <tbody>
            <?php foreach ($btRows as $b): $share = $totalReq > 0 ? round($b['cnt'] / $totalReq * 100, 1) : 0; ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($b['patient_blood_type']); ?></strong></td>
                <td><?php echo (int)$b['cnt']; ?></td>
                <td>
                    <div class="progress-bar-wrap">
                        <div class="progress-bar bg-crimson" style="width:<?php echo $share; ?>%"></div>
                    </div>
                    <span class="fs-80"><?php echo $share; ?>%</span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p class="text-muted">No data yet.</p><?php endif; ?>
    </div>

    <!-- Urgency breakdown -->
    <div class="card">
        <h3>Urgency Breakdown</h3>
        <?php if ($urgRows): ?>
        <table class="mt-12">
            <thead><tr><th>Urgency</th><th>Count</th><th>Share</th></tr></thead>
            <tbody>
            <?php foreach ($urgRows as $u):
                $share = $totalReq > 0 ? round($u['cnt'] / $totalReq * 100, 1) : 0;
                $cls   = $u['urgency'] === 'critical' ? 'bg-danger-dark' : ($u['urgency'] === 'urgent' ? 'bg-warning' : 'bg-secondary');
            ?>
            <tr>
                <td><?php echo ucfirst($u['urgency']); ?></td>
                <td><?php echo (int)$u['cnt']; ?></td>
                <td>
                    <div class="progress-bar-wrap"><div class="progress-bar <?php echo $cls; ?>" style="width:<?php echo $share; ?>%"></div></div>
                    <span class="fs-80"><?php echo $share; ?>%</span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p class="text-muted">No data yet.</p><?php endif; ?>
    </div>

    <!-- Top donor cities -->
    <div class="card">
        <h3>Top Donor Cities</h3>
        <?php if ($cityRows): ?>
        <table class="mt-12">
            <thead><tr><th>City</th><th>Donations</th></tr></thead>
            <tbody>
            <?php foreach ($cityRows as $c): ?>
            <tr>
                <td><?php echo htmlspecialchars($c['city'] ?: '(unknown)'); ?></td>
                <td><?php echo (int)$c['cnt']; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p class="text-muted">No confirmed donations yet.</p><?php endif; ?>
    </div>

</div>

<!-- Recent matches -->
<div class="card mt-20">
    <h3>Recent Matches</h3>
    <?php if ($recentMatches): ?>
    <div class="table-wrapper mt-12">
        <table>
            <thead><tr><th>Donor</th><th>Blood Type</th><th>Urgency</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($recentMatches as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                <td><?php echo htmlspecialchars($m['blood_type']); ?></td>
                <td><?php echo ucfirst($m['urgency']); ?></td>
                <td><span class="badge <?php echo $m['status'] === 'donated' ? 'badge-success' : 'badge-secondary'; ?>">
                    <?php echo ucfirst($m['status']); ?>
                </span></td>
                <td class="fs-85"><?php echo date('M j', strtotime($m['updated_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted">No matches yet.</p><?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
