<?php
/**
 * Admin: SLO monitoring dashboard.
 * Shows real-time health signals for the platform's key SLOs:
 *   - Notification queue depth and error rate
 *   - Open critical blood requests (response SLO)
 *   - DB connectivity + migration ledger status
 *   - Worker health (last-run timestamps from audit_logs)
 *   - Replica lag (reported when DB_READ_HOST differs from DB_HOST)
 *   - Cache health
 */
require_once '../includes/functions.php';
requireAdmin();

$t0 = microtime(true);

// ── 1. Notification queue ─────────────────────────────────────────────────────
$queueStats = $pdo->query("
    SELECT
        SUM(status = 'pending')  AS pending,
        SUM(status = 'sent')     AS sent,
        SUM(status = 'failed')   AS failed,
        SUM(status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)) AS stale_pending
    FROM notification_queue
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetch();

$queueDepth   = (int)$queueStats['pending'];
$queueFailed  = (int)$queueStats['failed'];
$queueSent    = (int)$queueStats['sent'];
$stalePending = (int)$queueStats['stale_pending'];

// ── 2. Critical open requests ─────────────────────────────────────────────────
$criticalOpen = (int)$pdo->query("SELECT COUNT(*) FROM blood_requests WHERE urgency='critical' AND status='open'")->fetchColumn();
$openAll      = (int)$pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status='open'")->fetchColumn();

// Oldest open critical request age (minutes)
$oldestCrit = $pdo->query("SELECT TIMESTAMPDIFF(MINUTE, MIN(created_at), NOW()) FROM blood_requests WHERE urgency='critical' AND status='open'")->fetchColumn();

// ── 3. DB health ──────────────────────────────────────────────────────────────
$migrationCount  = (int)$pdo->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn();
$expectedMigrations = count(glob(__DIR__ . '/../../schema/*.sql') ?: []);

// DB response time
$dbT0      = microtime(true);
$pdo->query("SELECT 1");
$dbLatency = round((microtime(true) - $dbT0) * 1000, 2);

// Replica lag check
$replicaLag = null;
try {
    $pdoR = getReadPdo();
    $slaveStatus = @$pdoR->query("SHOW SLAVE STATUS")->fetch();
    $replicaLag  = isset($slaveStatus['Seconds_Behind_Master'])
        ? (int)$slaveStatus['Seconds_Behind_Master']
        : null;
} catch (Exception $e) {
    $replicaLag = null;
}

// ── 4. Worker last-run (from audit_logs) ─────────────────────────────────────
$workerLast = $pdo->query("
    SELECT action, MAX(created_at) AS last_run
    FROM audit_logs
    WHERE action IN ('archive_run','purge_run','forecast_run','notification_processed')
    GROUP BY action
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Notification worker: check process_notifications last sent
$lastNotifSent = $pdo->query("
    SELECT MAX(sent_at) FROM notification_queue WHERE status = 'sent'
")->fetchColumn();

// Forecast worker: check demand_forecasts last created
$lastForecast = $pdo->query("SELECT MAX(created_at) FROM demand_forecasts")->fetchColumn();

// ── 5. Cache health ───────────────────────────────────────────────────────────
$cacheType    = 'file';
$cacheHealthy = true;
if (_cacheRedis()) {
    $cacheType = 'redis';
    try {
        $r = _cacheRedis();
        $r->ping();
    } catch (Exception $e) {
        $cacheHealthy = false;
    }
}

// ── 6. Audit log rate (error signal) ──────────────────────────────────────────
$auditLast24h = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();

$pageLatency = round((microtime(true) - $t0) * 1000, 1);

// ── SLO signal helpers ────────────────────────────────────────────────────────
function sloStatus(bool $ok): string {
    return $ok
        ? '<span class="pill pill--success">OK</span>'
        : '<span class="pill pill--danger">ALERT</span>';
}
function sloRow(string $label, string $value, bool $ok, string $note = ''): void {
    echo '<tr>';
    echo '<td class="fw-600">' . htmlspecialchars($label) . '</td>';
    echo '<td>' . $value . '</td>';
    echo '<td>' . sloStatus($ok) . '</td>';
    if ($note) echo '<td class="fs-80 text-muted">' . htmlspecialchars($note) . '</td>';
    else echo '<td></td>';
    echo '</tr>';
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>SLO Monitoring Dashboard</h1>
        <div class="flex gap-8 items-center">
            <span class="fs-80 text-muted">Page latency: <?php echo $pageLatency; ?> ms</span>
            <a href="" class="btn btn-secondary btn-small">Refresh</a>
            <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back</a>
        </div>
    </div>
    <p class="text-muted fs-90">Real-time health signals. Alert = SLO breach or action required.</p>
</div>

<div class="card">
    <h2>Notification Queue</h2>
    <div class="flex gap-12 flex-wrap mb-16">
        <div class="stat-card <?php echo $queueDepth > 100 ? 'border-l-danger' : ''; ?>">
            <h3><?php echo $queueDepth; ?></h3><p>Pending (24h)</p>
        </div>
        <div class="stat-card <?php echo $stalePending > 0 ? 'border-l-danger' : ''; ?>">
            <h3><?php echo $stalePending; ?></h3><p>Stale (>30 min)</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $queueSent; ?></h3><p>Sent (24h)</p>
        </div>
        <div class="stat-card <?php echo $queueFailed > 5 ? 'border-l-danger' : ''; ?>">
            <h3><?php echo $queueFailed; ?></h3><p>Failed (24h)</p>
        </div>
    </div>
    <table>
        <thead><tr><th>Signal</th><th>Value</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
        <?php
        sloRow('Queue depth < 50',    (string)$queueDepth,   $queueDepth  <= 50, 'Notifications waiting to be sent');
        sloRow('Stale pending = 0',   (string)$stalePending, $stalePending === 0, 'Pending > 30 min = worker may be down');
        sloRow('Failed < 5 (24h)',    (string)$queueFailed,  $queueFailed  < 5,  'Delivery failures in past 24 h');
        $notifSentAgo = $lastNotifSent ? round((time() - strtotime($lastNotifSent)) / 60) . ' min ago' : 'never';
        sloRow('Notification worker active', $notifSentAgo, $lastNotifSent && strtotime($lastNotifSent) > strtotime('-2 hours'), 'Last successful send');
        ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Blood Request Response SLO</h2>
    <div class="flex gap-12 flex-wrap mb-16">
        <div class="stat-card <?php echo $criticalOpen > 0 ? 'border-l-danger' : ''; ?>">
            <h3><?php echo $criticalOpen; ?></h3><p>Critical Open</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $openAll; ?></h3><p>All Open</p>
        </div>
        <?php if ($oldestCrit !== null && $criticalOpen > 0): ?>
        <div class="stat-card <?php echo (int)$oldestCrit > 120 ? 'border-l-danger' : ''; ?>">
            <h3><?php echo (int)$oldestCrit; ?> min</h3><p>Oldest Critical Age</p>
        </div>
        <?php endif; ?>
    </div>
    <table>
        <thead><tr><th>Signal</th><th>Value</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
        <?php
        sloRow('Critical requests < 10',  (string)$criticalOpen, $criticalOpen < 10, 'Open critical blood requests');
        if ($criticalOpen > 0 && $oldestCrit !== null) {
            sloRow('Oldest critical < 2h', (int)$oldestCrit . ' min', (int)$oldestCrit < 120, 'SLO: match within 2 h for critical');
        }
        ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Database Health</h2>
    <table>
        <thead><tr><th>Signal</th><th>Value</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
        <?php
        sloRow('DB latency < 50ms',      $dbLatency . ' ms', $dbLatency < 50, 'Round-trip SELECT 1');
        sloRow('Migrations up-to-date',  $migrationCount . ' applied / ' . $expectedMigrations . ' files',
               $migrationCount >= $expectedMigrations,
               'Mismatch = unapplied migration');
        if ($replicaLag !== null) {
            sloRow('Replica lag < 10s', $replicaLag . ' s', $replicaLag < 10, 'SHOW SLAVE STATUS Seconds_Behind_Master');
        } else {
            sloRow('Replica lag', 'N/A (single node)', true, 'Set DB_READ_HOST to enable replica');
        }
        ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Workers &amp; Background Jobs</h2>
    <table>
        <thead><tr><th>Signal</th><th>Value</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
        <?php
        $forecastAgo = $lastForecast
            ? round((time() - strtotime($lastForecast)) / 3600, 1) . 'h ago'
            : 'never';
        $forecastOk = $lastForecast && strtotime($lastForecast) > strtotime('-25 hours');
        sloRow('Forecast worker < 25h', $forecastAgo, $forecastOk, 'worker/compute_forecasts.php last run');
        sloRow('Notification worker < 2h', $notifSentAgo ?? '—', $lastNotifSent && strtotime($lastNotifSent) > strtotime('-2 hours'), 'worker/process_notifications.php');
        ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Cache &amp; Audit</h2>
    <table>
        <thead><tr><th>Signal</th><th>Value</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
        <?php
        sloRow('Cache backend', ucfirst($cacheType), $cacheHealthy, 'Redis or file-based fragment cache');
        sloRow('Audit events (24h)', (string)$auditLast24h, $auditLast24h >= 0, 'Total audit_logs entries last 24 h');
        sloRow('Page latency < 500ms', $pageLatency . ' ms', $pageLatency < 500, 'Time to render this page');
        ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
