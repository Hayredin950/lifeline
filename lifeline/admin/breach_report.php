<?php
/**
 * LifeLine — Breach Incident Report & Detail (P3 · Doc 07 § HIPAA Breach Rule)
 *
 * GET  /admin/breach_report.php          → new incident form
 * GET  /admin/breach_report.php?id=N     → view / update existing incident
 * POST /admin/breach_report.php          → create new incident
 * POST /admin/breach_report.php?id=N     → add timeline event / update status
 */

$pageTitle = 'Breach Incident Report';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$incident = null;
$timeline = [];

if ($id) {
    $incident = $pdo->prepare("SELECT * FROM breach_incidents WHERE id = ?")->execute([$id]) ? null : null;
    $stmt = $pdo->prepare("SELECT * FROM breach_incidents WHERE id = ?");
    $stmt->execute([$id]);
    $incident = $stmt->fetch();

    if (!$incident) {
        setFlash('error', 'Incident not found.');
        redirect('/admin/dpo_dashboard.php');
    }

    $tStmt = $pdo->prepare("
        SELECT t.*, u.email AS actor_email
        FROM breach_timeline t
        LEFT JOIN users u ON u.id = t.actor_id
        WHERE t.incident_id = ?
        ORDER BY t.created_at ASC
    ");
    $tStmt->execute([$id]);
    $timeline = $tStmt->fetchAll();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    if ($id) {
        // ── Update existing incident ────────────────────────────────────────
        $action = trim($_POST['action'] ?? '');
        $detail = trim($_POST['detail'] ?? '');
        $newStatus = in_array($_POST['status'] ?? '', ['open','contained','remediated','closed'])
            ? $_POST['status'] : $incident['status'];

        // Log timeline event.
        if ($action !== '') {
            $pdo->prepare("
                INSERT INTO breach_timeline (incident_id, actor_id, event, detail)
                VALUES (?, ?, ?, ?)
            ")->execute([$id, $_SESSION['user_id'], substr($action, 0, 255), $detail ?: null]);
        }

        // Update status & timestamps.
        $updates = ['status = ?', 'updated_at = NOW()'];
        $params  = [$newStatus];

        if ($newStatus === 'contained' && !$incident['contained_at']) {
            $updates[] = 'contained_at = NOW()';
        }
        if (isset($_POST['mark_notified']) && !$incident['notified_at']) {
            $updates[] = 'notified_at = NOW()';
        }
        if ($newStatus === 'closed' && !$incident['closed_at']) {
            $updates[] = 'closed_at = NOW()';
        }
        if (isset($_POST['authority_ref']) && $_POST['authority_ref'] !== '') {
            $updates[] = 'authority_ref = ?';
            $params[] = substr(trim($_POST['authority_ref']), 0, 255);
        }

        $params[] = $id;
        $pdo->prepare("UPDATE breach_incidents SET " . implode(', ', $updates) . " WHERE id = ?")
            ->execute($params);

        logAudit($pdo, 'breach_update', 'breach_incidents', $id,
            ['status' => $incident['status']],
            ['status' => $newStatus, 'action' => $action]);

        setFlash('success', 'Incident updated.');
        redirect("/admin/breach_report.php?id={$id}");

    } else {
        // ── Create new incident ─────────────────────────────────────────────
        $title     = trim($_POST['title'] ?? '');
        $severity  = in_array($_POST['severity'] ?? '', ['low','medium','high','critical']) ? $_POST['severity'] : 'medium';
        $desc      = trim($_POST['description'] ?? '');
        $affected  = isset($_POST['affected_tables']) ? substr(trim($_POST['affected_tables']), 0, 500) : null;
        $estUsers  = isset($_POST['estimated_users']) && is_numeric($_POST['estimated_users'])
                   ? (int)$_POST['estimated_users'] : null;

        if ($title === '') $errors[] = 'Title is required.';
        if ($desc  === '') $errors[] = 'Description is required.';

        if (empty($errors)) {
            $ins = $pdo->prepare("
                INSERT INTO breach_incidents
                  (title, severity, description, affected_tables, estimated_users, reported_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$title, $severity, $desc, $affected, $estUsers, $_SESSION['user_id']]);
            $newId = (int)$pdo->lastInsertId();

            // First timeline entry.
            $pdo->prepare("
                INSERT INTO breach_timeline (incident_id, actor_id, event, detail)
                VALUES (?, ?, 'Incident created', ?)
            ")->execute([$newId, $_SESSION['user_id'], "Severity: {$severity}. {$desc}"]);

            logAudit($pdo, 'breach_create', 'breach_incidents', $newId, [], ['title' => $title, 'severity' => $severity]);

            setFlash('success', 'Breach incident #' . $newId . ' recorded. Notify INSA/DPA within 72 hours if personal data is involved.');
            redirect("/admin/breach_report.php?id={$newId}");
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container" style="margin-top:2rem;">
  <div class="breadcrumb">
    <a href="dpo_dashboard.php">&#8592; DPO Dashboard</a>
  </div>

<?php if ($incident): ?>
  <!-- ── Existing incident detail ───────────────────────────────────────── -->
  <?php
  $sevClass = ['critical'=>'badge-danger','high'=>'badge-warning','medium'=>'badge-info','low'=>'badge-secondary'][$incident['severity']] ?? 'badge-secondary';
  $hoursOpen = (int)(strtotime('now') - strtotime($incident['discovered_at'])) / 3600;
  ?>
  <div class="page-header">
    <h1>Incident #<?php echo $incident['id']; ?>: <?php echo htmlspecialchars($incident['title']); ?></h1>
    <div>
      <span class="badge <?php echo $sevClass; ?>"><?php echo strtoupper($incident['severity']); ?></span>
      <span class="badge badge-<?php echo $incident['status']==='closed'?'success':'warning'; ?>"><?php echo $incident['status']; ?></span>
      <span class="text-muted fs-85"><?php echo $hoursOpen; ?>h since discovery<?php echo $hoursOpen > 72 && $incident['status'] !== 'closed' ? ' <strong class="text-danger">(72h notification window elapsed)</strong>' : ''; ?></span>
    </div>
  </div>

  <div class="grid-2" style="gap:2rem;margin-bottom:2rem;">
    <div class="card card-body">
      <h3>Incident Details</h3>
      <dl style="display:grid;grid-template-columns:auto 1fr;gap:.25rem 1rem;">
        <dt>Discovered:</dt><dd><?php echo date('d M Y H:i', strtotime($incident['discovered_at'])); ?></dd>
        <dt>Contained:</dt><dd><?php echo $incident['contained_at'] ? date('d M Y H:i', strtotime($incident['contained_at'])) : '<span class="text-danger">Not yet</span>'; ?></dd>
        <dt>Notified:</dt><dd><?php echo $incident['notified_at'] ? date('d M Y H:i', strtotime($incident['notified_at'])) : '<span class="text-danger">Not yet</span>'; ?></dd>
        <dt>Affected tables:</dt><dd><?php echo htmlspecialchars($incident['affected_tables'] ?? '—'); ?></dd>
        <dt>Est. users:</dt><dd><?php echo $incident['estimated_users'] ?? '—'; ?></dd>
        <dt>Authority ref:</dt><dd><?php echo htmlspecialchars($incident['authority_ref'] ?? '—'); ?></dd>
      </dl>
      <hr>
      <p><?php echo nl2br(htmlspecialchars($incident['description'])); ?></p>
    </div>

    <div class="card card-body">
      <h3>Update Incident</h3>
      <form method="POST" action="breach_report.php?id=<?php echo $incident['id']; ?>">
        <?php csrfField(); ?>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <?php foreach (['open','contained','remediated','closed'] as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo $incident['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Timeline Event</label>
          <input type="text" name="action" class="form-control" placeholder="e.g. Rotated leaked credentials" maxlength="255">
        </div>
        <div class="form-group">
          <label>Detail</label>
          <textarea name="detail" class="form-control" rows="3" placeholder="Additional context…"></textarea>
        </div>
        <?php if (!$incident['notified_at']): ?>
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="mark_notified"> Mark as notified (authorities / affected subjects)
          </label>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label>Authority Reference #</label>
          <input type="text" name="authority_ref" class="form-control" value="<?php echo htmlspecialchars($incident['authority_ref'] ?? ''); ?>" maxlength="255">
        </div>
        <button type="submit" class="btn btn-primary">Save Update</button>
      </form>
    </div>
  </div>

  <!-- Timeline -->
  <div class="card" style="margin-bottom:2rem;">
    <h2 class="card-header">Incident Timeline</h2>
    <div class="card-body">
      <?php if (empty($timeline)): ?>
        <p class="text-muted">No events yet.</p>
      <?php else: ?>
        <ol class="timeline-list" style="list-style:none;padding:0;border-left:3px solid var(--color-primary);margin:0;">
          <?php foreach ($timeline as $t): ?>
            <li style="padding:.75rem 1rem;position:relative;">
              <div style="position:absolute;left:-0.55rem;top:1rem;width:.75rem;height:.75rem;border-radius:50%;background:var(--color-primary);"></div>
              <strong><?php echo htmlspecialchars($t['event']); ?></strong>
              <span class="text-muted fs-85"> — <?php echo date('d M Y H:i', strtotime($t['created_at'])); ?> by <?php echo htmlspecialchars($t['actor_email'] ?? 'System'); ?></span>
              <?php if ($t['detail']): ?>
                <p style="margin:.25rem 0 0;"><?php echo nl2br(htmlspecialchars($t['detail'])); ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>
  <!-- ── New incident form ───────────────────────────────────────────────── -->
  <div class="page-header">
    <h1>Report New Breach Incident</h1>
    <p class="text-muted">DPDP / HIPAA — notify INSA and affected subjects within 72 hours of discovery.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><p><?php echo htmlspecialchars($e); ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card card-body" style="max-width:700px;">
    <form method="POST" action="breach_report.php">
      <?php csrfField(); ?>
      <div class="form-group">
        <label>Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars(old('title')); ?>" placeholder="e.g. Unauthorized access to donor PII" required maxlength="255">
      </div>
      <div class="form-group">
        <label>Severity</label>
        <select name="severity" class="form-control">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
      </div>
      <div class="form-group">
        <label>Description <span class="text-danger">*</span></label>
        <textarea name="description" class="form-control" rows="5" placeholder="Describe what happened, how it was discovered, and what data may be affected." required><?php echo htmlspecialchars(old('description')); ?></textarea>
      </div>
      <div class="form-group">
        <label>Affected Tables (CSV)</label>
        <input type="text" name="affected_tables" class="form-control" placeholder="e.g. users, donor_profiles" value="<?php echo htmlspecialchars(old('affected_tables')); ?>" maxlength="500">
      </div>
      <div class="form-group">
        <label>Estimated Affected Users</label>
        <input type="number" name="estimated_users" class="form-control" min="0" value="<?php echo htmlspecialchars(old('estimated_users')); ?>">
      </div>
      <button type="submit" class="btn btn-danger">Record Incident</button>
      <a href="dpo_dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
<?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
