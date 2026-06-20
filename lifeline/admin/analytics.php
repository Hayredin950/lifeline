<?php
/**
 * Admin analytics dashboard.
 * Platform-wide: registrations, blood requests, fulfillment, donor activity,
 * blood type demand, geographic distribution, leaderboard.
 */
require_once '../includes/functions.php';
requireAdmin();

$pdoR = getReadPdo();

// ── Overview KPIs ─────────────────────────────────────────────────────────
$kpi = $pdoR->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE role='donor' AND deleted_at IS NULL)            AS total_donors,
        (SELECT COUNT(*) FROM users WHERE role='hospital' AND deleted_at IS NULL)          AS total_hospitals,
        (SELECT COUNT(*) FROM blood_requests)                                             AS total_requests,
        (SELECT COUNT(*) FROM blood_requests WHERE status='fulfilled')                    AS fulfilled_requests,
        (SELECT COUNT(*) FROM donor_matches WHERE status='donated')                       AS total_donations,
        (SELECT COUNT(*) FROM blood_requests WHERE status='open')                         AS open_requests,
        (SELECT COUNT(*) FROM users WHERE role='donor' AND DATE(created_at)=CURDATE())   AS new_donors_today,
        (SELECT COUNT(*) FROM blood_requests WHERE DATE(created_at)=CURDATE())           AS requests_today
")->fetch();

$fulfillRate = $kpi['total_requests'] > 0
    ? round($kpi['fulfilled_requests'] / $kpi['total_requests'] * 100, 1) : 0;

// ── Monthly registrations + requests (last 12 months) ──────────────────────
$trendRows = $pdoR->query("
    SELECT m.month,
           COUNT(DISTINCT u.id)  AS new_donors,
           COUNT(DISTINCT br.id) AS new_requests
    FROM (
        SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL n MONTH), '%Y-%m') AS month
        FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
              UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8
              UNION SELECT 9 UNION SELECT 10 UNION SELECT 11) nums
    ) m
    LEFT JOIN users u
           ON u.role = 'donor' AND DATE_FORMAT(u.created_at,'%Y-%m') = m.month
           AND u.deleted_at IS NULL
    LEFT JOIN blood_requests br
           ON DATE_FORMAT(br.created_at,'%Y-%m') = m.month
    GROUP BY m.month ORDER BY m.month
")->fetchAll();

// ── Blood type demand ──────────────────────────────────────────────────────
$btRows = $pdoR->query("
    SELECT patient_blood_type AS bt, COUNT(*) AS cnt
    FROM blood_requests GROUP BY bt ORDER BY cnt DESC
")->fetchAll();

// ── Donor blood type supply ────────────────────────────────────────────────
$supplyRows = $pdoR->query("
    SELECT blood_type AS bt, COUNT(*) AS cnt
    FROM donor_profiles GROUP BY bt ORDER BY cnt DESC
")->fetchAll();

// ── Top hospitals by fulfillment ───────────────────────────────────────────
$topHospRows = $pdoR->query("
    SELECT hp.hospital_name, hp.city,
           COUNT(br.id) AS total, SUM(br.status='fulfilled') AS done
    FROM hospital_profiles hp
    JOIN blood_requests br ON br.hospital_id = hp.user_id
    GROUP BY hp.user_id
    ORDER BY done DESC LIMIT 10
")->fetchAll();

// ── Geographic distribution of donors ─────────────────────────────────────
$geoRows = $pdoR->query("
    SELECT city, state, COUNT(*) AS cnt
    FROM donor_profiles
    WHERE city IS NOT NULL AND city != ''
    GROUP BY city, state ORDER BY cnt DESC LIMIT 10
")->fetchAll();

// ── Notification queue depth ───────────────────────────────────────────────
$queueStmt = $pdoR->query("
    SELECT status, COUNT(*) AS cnt FROM notification_queue GROUP BY status
");
$queueByStatus = [];
foreach ($queueStmt->fetchAll() as $r) $queueByStatus[$r['status']] = (int)$r['cnt'];

include '../includes/header.php';
?>

<div class="card-header mb-20">
    <h1>Platform Analytics</h1>
    <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back</a>
</div>

<!-- KPI strip -->
<div class="stats-grid mb-24">
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format((int)$kpi['total_donors']); ?></div>
        <div class="stat-label">Total Donors</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format((int)$kpi['total_hospitals']); ?></div>
        <div class="stat-label">Hospitals</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format((int)$kpi['total_requests']); ?></div>
        <div class="stat-label">Blood Requests</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $fulfillRate; ?>%</div>
        <div class="stat-label">Fulfillment Rate</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format((int)$kpi['total_donations']); ?></div>
        <div class="stat-label">Confirmed Donations</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo (int)$kpi['open_requests']; ?></div>
        <div class="stat-label">Open Requests Now</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo (int)$kpi['new_donors_today']; ?></div>
        <div class="stat-label">New Donors Today</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo (int)$kpi['requests_today']; ?></div>
        <div class="stat-label">Requests Today</div>
    </div>
</div>

<div class="grid-2-col gap-20">

    <!-- Growth trend bar chart -->
    <div class="card">
        <h3>Monthly Activity (12 mo)</h3>
        <?php if ($trendRows): ?>
        <div class="chart-bars mt-12">
            <?php
            $maxT = max(1, ...array_merge(array_column($trendRows, 'new_donors'), array_column($trendRows, 'new_requests')));
            foreach ($trendRows as $r):
                $dp = round($r['new_donors']   / $maxT * 100);
                $rp = round($r['new_requests'] / $maxT * 100);
            ?>
            <div class="chart-bar-group" title="<?php echo htmlspecialchars($r['month']); ?>">
                <div class="chart-bar bg-crimson" style="height:<?php echo $dp; ?>%" title="Donors: <?php echo $r['new_donors']; ?>"></div>
                <div class="chart-bar bg-secondary" style="height:<?php echo $rp; ?>%" title="Requests: <?php echo $r['new_requests']; ?>"></div>
                <div class="chart-label"><?php echo substr($r['month'], 5); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-legend mt-8 fs-80">
            <span class="legend-dot bg-crimson"></span> Donors &nbsp;
            <span class="legend-dot bg-secondary"></span> Requests
        </div>
        <?php else: ?><p class="text-muted">No data yet.</p><?php endif; ?>
    </div>

    <!-- Blood type demand vs supply -->
    <div class="card">
        <h3>Blood Type — Demand vs Supply</h3>
        <table class="mt-12 fs-90">
            <thead><tr><th>Type</th><th>Demand (req)</th><th>Supply (donors)</th></tr></thead>
            <tbody>
            <?php
            $supply = [];
            foreach ($supplyRows as $s) $supply[$s['bt']] = (int)$s['cnt'];
            foreach ($btRows as $b):
                $bt  = $b['bt'];
                $dem = (int)$b['cnt'];
                $sup = $supply[$bt] ?? 0;
                $cls = $sup < $dem ? 'text-danger-dark fw-600' : '';
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($bt); ?></strong></td>
                <td><?php echo $dem; ?></td>
                <td class="<?php echo $cls; ?>"><?php echo $sup; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top hospitals -->
    <div class="card">
        <h3>Top Hospitals by Fulfillment</h3>
        <?php if ($topHospRows): ?>
        <table class="mt-12 fs-90">
            <thead><tr><th>Hospital</th><th>City</th><th>Requests</th><th>Fulfilled</th><th>Rate</th></tr></thead>
            <tbody>
            <?php foreach ($topHospRows as $h):
                $rate = $h['total'] > 0 ? round($h['done'] / $h['total'] * 100) : 0;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($h['hospital_name']); ?></td>
                <td class="fs-85"><?php echo htmlspecialchars($h['city']); ?></td>
                <td><?php echo (int)$h['total']; ?></td>
                <td><?php echo (int)$h['done']; ?></td>
                <td><?php echo $rate; ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p class="text-muted">No hospital data yet.</p><?php endif; ?>
    </div>

    <!-- Donor geographic distribution -->
    <div class="card">
        <h3>Donor Distribution (Top Cities)</h3>
        <?php if ($geoRows): ?>
        <?php $maxGeo = max(1, ...array_column($geoRows, 'cnt')); ?>
        <table class="mt-12 fs-90">
            <thead><tr><th>City</th><th>State</th><th>Donors</th></tr></thead>
            <tbody>
            <?php foreach ($geoRows as $g): $pct = round($g['cnt'] / $maxGeo * 100); ?>
            <tr>
                <td><?php echo htmlspecialchars($g['city']); ?></td>
                <td class="fs-85"><?php echo htmlspecialchars($g['state']); ?></td>
                <td>
                    <div class="progress-bar-wrap" style="display:inline-block;width:80px;vertical-align:middle">
                        <div class="progress-bar bg-crimson" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                    <?php echo (int)$g['cnt']; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p class="text-muted">No location data yet.</p><?php endif; ?>
    </div>

    <!-- Notification queue health -->
    <div class="card">
        <h3>Notification Queue Health</h3>
        <table class="mt-12 fs-90">
            <thead><tr><th>Status</th><th>Count</th></tr></thead>
            <tbody>
            <?php
            $statuses = ['pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed'];
            foreach ($statuses as $s => $label):
                $cls = $s === 'failed' ? 'text-danger-dark fw-600' : '';
            ?>
            <tr>
                <td class="<?php echo $cls; ?>"><?php echo $label; ?></td>
                <td><?php echo $queueByStatus[$s] ?? 0; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include '../includes/footer.php'; ?>
