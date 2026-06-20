<?php
/**
 * LifeLine — DPO (Data Protection Officer) Dashboard (P3 · Doc 07)
 *
 * One-stop compliance overview for the Data Protection Officer / admin:
 *   • DSAR request queue with 30-day countdown
 *   • Consent health: subjects on latest TERMS_VERSION vs outdated
 *   • Breach incident tracker
 *   • BAA (Business Associate Agreement) status
 *   • DPIA register summary
 */

$pageTitle = 'DPO Compliance Dashboard';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

// ── DSAR queue ───────────────────────────────────────────────────────────────
$dsarRows = $pdo->query("
    SELECT d.*,
           u.email        AS subject_email,
           h.email        AS handler_email,
           DATEDIFF(d.due_at, CURDATE()) AS days_left
    FROM   dsar_requests d
    LEFT   JOIN users u ON u.id = d.user_id
    LEFT   JOIN users h ON h.id = d.handler_id
    WHERE  d.status IN ('received','in_progress')
    ORDER  BY d.due_at ASC
    LIMIT  50
")->fetchAll();

// ── Consent health ───────────────────────────────────────────────────────────
$consentStats = $pdo->query("
    SELECT
        SUM(c.terms_version = '" . TERMS_VERSION . "') AS current_version,
        SUM(c.terms_version != '" . TERMS_VERSION . "' OR c.terms_version IS NULL) AS outdated
    FROM (
        SELECT terms_version,
               ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY accepted_at DESC) AS rn
        FROM consent_log
    ) c WHERE c.rn = 1
")->fetch();

// ── Breach incidents ─────────────────────────────────────────────────────────
$openBreaches = $pdo->query("
    SELECT b.*, u.email AS reporter_email,
           TIMESTAMPDIFF(HOUR, b.discovered_at, NOW()) AS hours_open
    FROM   breach_incidents b
    LEFT   JOIN users u ON u.id = b.reported_by
    WHERE  b.status NOT IN ('closed')
    ORDER  BY FIELD(b.severity,'critical','high','medium','low'), b.discovered_at ASC
    LIMIT  20
")->fetchAll();

$totalBreaches = (int)$pdo->query("SELECT COUNT(*) FROM breach_incidents")->fetchColumn();
$closedBreaches = (int)$pdo->query("SELECT COUNT(*) FROM breach_incidents WHERE status='closed'")->fetchColumn();

// ── BAA status ───────────────────────────────────────────────────────────────
$baaAlerts = $pdo->query("
    SELECT * FROM baa_agreements
    WHERE status = 'active'
      AND (expires_at IS NULL OR expires_at >= CURDATE())
      AND (renewal_alert_at IS NULL OR renewal_alert_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
    ORDER BY expires_at ASC
    LIMIT 20
")->fetchAll();

$baaCounts = $pdo->query("
    SELECT status, COUNT(*) AS n FROM baa_agreements GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── DPIA register ─────────────────────────────────────────────────────────────
$dpiaRows = $pdo->query("
    SELECT * FROM dpia_records
    ORDER BY FIELD(status,'in_review','draft','approved','retired'),
             FIELD(risk_level,'high','medium','low')
    LIMIT 30
")->fetchAll();

$dpiaOverdue = (int)$pdo->query("
    SELECT COUNT(*) FROM dpia_records
    WHERE next_review_at < CURDATE() AND status != 'retired'
")->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="container" style="margin-top:2rem;">
  <div class="page-header">
    <h1>DPO Compliance Dashboard</h1>
    <p class="text-muted">DPDP · HIPAA-style Program · LifeLine Blood Network</p>
  </div>

  <!-- ── Compliance score strip ─────────────────────────────────────────── -->
  <div class="stats-grid" style="margin-bottom:2rem;">
    <?php
    $openDsar = count($dsarRows);
    $overdueDsar = array_filter($dsarRows, fn($r) => $r['days_left'] < 0);
    ?>
    <div class="stat-card <?php echo count($overdueDsar) ? 'stat-card--danger' : ''; ?>">
      <div class="stat-number"><?php echo $openDsar; ?></div>
      <div class="stat-label">Open DSARs <?php echo count($overdueDsar) ? '(' . count($overdueDsar) . ' overdue)' : ''; ?></div>
    </div>
    <div class="stat-card <?php echo count($openBreaches) ? 'stat-card--danger' : 'stat-card--success'; ?>">
      <div class="stat-number"><?php echo count($openBreaches); ?></div>
      <div class="stat-label">Open Breach Incidents</div>
    </div>
    <div class="stat-card <?php echo $dpiaOverdue ? 'stat-card--warning' : ''; ?>">
      <div class="stat-number"><?php echo $dpiaOverdue; ?></div>
      <div class="stat-label">DPIAs Overdue Review</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?php echo number_format((int)($consentStats['current_version'] ?? 0)); ?></div>
      <div class="stat-label">Consents on v<?php echo htmlspecialchars(TERMS_VERSION); ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?php echo $baaCounts['active'] ?? 0; ?></div>
      <div class="stat-label">Active BAAs</div>
    </div>
  </div>

  <!-- ── DSAR Queue ─────────────────────────────────────────────────────── -->
  <section class="card" style="margin-bottom:2rem;">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h2 style="margin:0;">Data Subject Requests (DSARs)</h2>
      <a href="dsar_new.php" class="btn btn-sm btn-primary">+ Log DSAR</a>
    </div>
    <?php if (empty($dsarRows)): ?>
      <div class="card-body"><p class="text-muted">No open requests.</p></div>
    <?php else: ?>
    <div class="table-wrapper">
      <table class="admin-table">
        <thead>
          <tr>
            <th>#</th><th>Type</th><th>Requester</th><th>Status</th>
            <th>Due</th><th>Days left</th><th>Handler</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($dsarRows as $r): ?>
          <tr class="<?php echo $r['days_left'] < 0 ? 'row-danger' : ($r['days_left'] <= 5 ? 'row-warning' : ''); ?>">
            <td><?php echo $r['id']; ?></td>
            <td><?php echo htmlspecialchars(ucfirst($r['request_type'])); ?></td>
            <td><?php echo htmlspecialchars($r['subject_email'] ?? $r['requester_email']); ?></td>
            <td><span class="badge badge-<?php echo $r['status'] === 'received' ? 'info' : 'warning'; ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
            <td><?php echo htmlspecialchars($r['due_at']); ?></td>
            <td><?php $d = (int)$r['days_left']; echo $d < 0 ? '<strong class="text-danger">' . abs($d) . ' overdue</strong>' : $d; ?></td>
            <td><?php echo htmlspecialchars($r['handler_email'] ?? '—'); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <!-- ── Breach incidents ──────────────────────────────────────────────── -->
  <section class="card" style="margin-bottom:2rem;">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h2 style="margin:0;">Breach Incidents <small class="text-muted fs-85"><?php echo $closedBreaches; ?>/<?php echo $totalBreaches; ?> closed</small></h2>
      <a href="breach_report.php" class="btn btn-sm btn-danger">+ Report Breach</a>
    </div>
    <?php if (empty($openBreaches)): ?>
      <div class="card-body"><p class="text-success">No open breach incidents.</p></div>
    <?php else: ?>
    <div class="table-wrapper">
      <table class="admin-table">
        <thead>
          <tr><th>#</th><th>Title</th><th>Severity</th><th>Status</th><th>Discovered</th><th>Hours open</th><th>Notified</th></tr>
        </thead>
        <tbody>
        <?php foreach ($openBreaches as $b):
          $sevClass = ['critical'=>'badge-danger','high'=>'badge-warning','medium'=>'badge-info','low'=>'badge-secondary'][$b['severity']] ?? 'badge-secondary';
        ?>
          <tr>
            <td><?php echo $b['id']; ?></td>
            <td><a href="breach_report.php?id=<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['title']); ?></a></td>
            <td><span class="badge <?php echo $sevClass; ?>"><?php echo htmlspecialchars($b['severity']); ?></span></td>
            <td><?php echo htmlspecialchars($b['status']); ?></td>
            <td><?php echo date('d M Y H:i', strtotime($b['discovered_at'])); ?></td>
            <td><?php $h = (int)$b['hours_open']; echo $h > 72 ? '<strong class="text-danger">' . $h . 'h</strong>' : $h . 'h'; ?></td>
            <td><?php echo $b['notified_at'] ? date('d M Y', strtotime($b['notified_at'])) : '<span class="text-danger">Not yet</span>'; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <!-- ── BAA tracker ────────────────────────────────────────────────────── -->
  <section class="card" style="margin-bottom:2rem;">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h2 style="margin:0;">Business Associate Agreements</h2>
      <a href="baa_new.php" class="btn btn-sm btn-primary">+ Add BAA</a>
    </div>
    <div class="card-body">
      <?php
      $baaList = $pdo->query("SELECT * FROM baa_agreements ORDER BY status ASC, expires_at ASC LIMIT 30")->fetchAll();
      if (empty($baaList)): ?>
        <p class="text-muted">No BAAs recorded yet.</p>
      <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr><th>Partner</th><th>Type</th><th>Status</th><th>Signed</th><th>Expires</th><th>Contact</th></tr>
        </thead>
        <tbody>
        <?php foreach ($baaList as $b):
          $expired = $b['expires_at'] && $b['expires_at'] < date('Y-m-d');
          $soon    = $b['expires_at'] && $b['expires_at'] <= date('Y-m-d', strtotime('+30 days'));
        ?>
          <tr class="<?php echo $expired ? 'row-danger' : ($soon ? 'row-warning' : ''); ?>">
            <td><?php echo htmlspecialchars($b['partner_name']); ?></td>
            <td><?php echo htmlspecialchars($b['partner_type']); ?></td>
            <td><span class="badge badge-<?php echo $b['status']==='active'?'success':($b['status']==='expired'?'danger':'secondary'); ?>"><?php echo $b['status']; ?></span></td>
            <td><?php echo $b['signed_at']; ?></td>
            <td><?php echo $b['expires_at'] ?? '—'; ?></td>
            <td><?php echo htmlspecialchars($b['contact_email'] ?? '—'); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </section>

  <!-- ── DPIA register ──────────────────────────────────────────────────── -->
  <section class="card" style="margin-bottom:2rem;">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h2 style="margin:0;">DPIA Register <?php if ($dpiaOverdue): ?><span class="badge badge-danger"><?php echo $dpiaOverdue; ?> overdue</span><?php endif; ?></h2>
      <a href="dpia_new.php" class="btn btn-sm btn-primary">+ New DPIA</a>
    </div>
    <div class="card-body">
      <?php if (empty($dpiaRows)): ?>
        <p class="text-muted">No DPIA records yet.</p>
      <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr><th>Process</th><th>Risk</th><th>Status</th><th>DPO sign-off</th><th>Next review</th></tr>
        </thead>
        <tbody>
        <?php foreach ($dpiaRows as $d):
          $riskClass = ['high'=>'badge-danger','medium'=>'badge-warning','low'=>'badge-success'][$d['risk_level']] ?? 'badge-secondary';
          $overdue = $d['next_review_at'] && $d['next_review_at'] < date('Y-m-d') && $d['status'] !== 'retired';
        ?>
          <tr class="<?php echo $overdue ? 'row-danger' : ''; ?>">
            <td><?php echo htmlspecialchars($d['process_name']); ?></td>
            <td><span class="badge <?php echo $riskClass; ?>"><?php echo $d['risk_level']; ?></span></td>
            <td><span class="badge badge-<?php echo $d['status']==='approved'?'success':($d['status']==='in_review'?'warning':'secondary'); ?>"><?php echo $d['status']; ?></span></td>
            <td><?php echo $d['dpo_sign_off'] ? '<span class="text-success">&#10003;</span>' : '<span class="text-danger">&#10007; Pending</span>'; ?></td>
            <td><?php echo $d['next_review_at'] ? ($overdue ? '<strong class="text-danger">' . $d['next_review_at'] . ' (overdue)</strong>' : $d['next_review_at']) : '—'; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </section>

  <!-- ── Consent health ─────────────────────────────────────────────────── -->
  <section class="card" style="margin-bottom:2rem;">
    <h2 class="card-header" style="margin:0 0 1rem;">Consent Health (Terms v<?php echo htmlspecialchars(TERMS_VERSION); ?>)</h2>
    <div class="card-body">
      <?php
      $current  = (int)($consentStats['current_version'] ?? 0);
      $outdated = (int)($consentStats['outdated'] ?? 0);
      $total    = $current + $outdated;
      $pct      = $total > 0 ? round($current / $total * 100) : 0;
      ?>
      <p><?php echo $current; ?> of <?php echo $total; ?> consenting users are on the current terms version (<?php echo $pct; ?>%).</p>
      <div class="progress" style="height:1.25rem;background:#e5e7eb;border-radius:.5rem;overflow:hidden;">
        <div class="progress-bar" style="width:<?php echo $pct; ?>%;background:var(--color-primary);height:100%;border-radius:.5rem;"></div>
      </div>
      <?php if ($outdated > 0): ?>
        <p class="text-warning" style="margin-top:.5rem;">
          <?php echo $outdated; ?> user(s) accepted an older version — they will be prompted to re-consent on next login.
        </p>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
