<?php
/**
 * LifeLine — Public-health shortage analytics (P3 · Doc 13 §4)
 *
 * De-identified aggregate view for DPO / health-authority reporting.
 * No names, emails, or device IDs — cohort-level blood-type × region data only.
 * Cohorts < MIN_COHORT_SIZE are suppressed (k-anonymity floor).
 */

$pageTitle = 'Shortage Analytics (De-identified)';
define('MIN_COHORT_SIZE', 5);

require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$rangeOptions = ['30d' => 'Last 30 days', '90d' => 'Last 90 days', '180d' => 'Last 6 months', '365d' => 'Last 12 months', 'all' => 'All time'];
$range     = in_array($_GET['range'] ?? '', array_keys($rangeOptions)) ? $_GET['range'] : '90d';
$dayMap    = ['30d' => 30, '90d' => 90, '180d' => 180, '365d' => 365];
$dateWhere = isset($dayMap[$range]) ? "AND r.created_at >= DATE_SUB(NOW(), INTERVAL {$dayMap[$range]} DAY)" : '';
$dateWhereNoAlias = isset($dayMap[$range]) ? "AND created_at >= DATE_SUB(NOW(), INTERVAL {$dayMap[$range]} DAY)" : '';

// ── 1. Active shortages heatmap ───────────────────────────────────────────────
$shortages = $pdo->query("
    SELECT blood_type,
           COALESCE(state,'Unknown') AS region,
           COUNT(*)                  AS open_requests,
           SUM(units_needed)         AS units_needed
    FROM   blood_requests
    WHERE  status = 'open'
    GROUP  BY blood_type, region
    HAVING open_requests >= " . MIN_COHORT_SIZE . "
    ORDER  BY open_requests DESC
")->fetchAll();

// ── 2. Fulfillment rate by blood type ─────────────────────────────────────────
$fulfillment = $pdo->query("
    SELECT blood_type,
           COUNT(*)                                         AS total,
           SUM(status = 'fulfilled')                       AS fulfilled,
           ROUND(SUM(status='fulfilled') / COUNT(*) * 100) AS pct
    FROM   blood_requests
    WHERE  1=1 {$dateWhereNoAlias}
    GROUP  BY blood_type
    HAVING total >= " . MIN_COHORT_SIZE . "
    ORDER  BY pct ASC
")->fetchAll();

// ── 3. Avg time-to-fill ───────────────────────────────────────────────────────
$ttf = $pdo->query("
    SELECT r.blood_type,
           COALESCE(r.state,'Unknown')                                    AS region,
           COUNT(*)                                                       AS fulfilled_count,
           ROUND(AVG(TIMESTAMPDIFF(HOUR, r.created_at, d.created_at)),1) AS avg_hours
    FROM   blood_requests r
    JOIN   donation_history d ON d.request_id = r.id
    WHERE  r.status = 'fulfilled' {$dateWhere}
    GROUP  BY r.blood_type, region
    HAVING fulfilled_count >= " . MIN_COHORT_SIZE . "
    ORDER  BY avg_hours DESC
")->fetchAll();

// ── 4. Available donors by blood type × region ────────────────────────────────
$donorAvail = $pdo->query("
    SELECT d.blood_type,
           COALESCE(d.state,'Unknown') AS region,
           COUNT(*)                    AS available_donors
    FROM   donor_profiles d
    JOIN   users u ON u.id = d.user_id
    WHERE  u.is_active = 1 AND u.deleted_at IS NULL AND d.is_available = 1
    GROUP  BY d.blood_type, region
    HAVING available_donors >= " . MIN_COHORT_SIZE . "
    ORDER  BY available_donors DESC
")->fetchAll();

// ── 5. Top shortage regions ────────────────────────────────────────────────────
$topRegions = $pdo->query("
    SELECT COALESCE(state,'Unknown') AS region, COUNT(*) AS open_requests
    FROM   blood_requests
    WHERE  status = 'open'
    GROUP  BY region
    HAVING open_requests >= " . MIN_COHORT_SIZE . "
    ORDER  BY open_requests DESC
    LIMIT  10
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="container" style="margin-top:2rem;">
  <div class="page-header d-flex align-items-center justify-content-between">
    <div>
      <h1>Public-Health Shortage Analytics</h1>
      <p class="text-muted fs-85">
        De-identified &mdash; cohorts &lt; <?php echo MIN_COHORT_SIZE; ?> suppressed &mdash; for health-authority / DPO use.
        <a href="<?php echo baseUrl(); ?>/admin/analytics.php">&#8592; Platform analytics</a>
      </p>
    </div>
    <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
      <select name="range" class="form-control form-control-sm" onchange="this.form.submit()">
        <?php foreach ($rangeOptions as $v => $l): ?>
          <option value="<?php echo $v; ?>" <?php echo $range===$v?'selected':''; ?>><?php echo $l; ?></option>
        <?php endforeach; ?>
      </select>
      <a href="<?php echo baseUrl(); ?>/api/v1/analytics.php?range=<?php echo urlencode($range); ?>"
         target="_blank" class="btn btn-sm btn-secondary">API&nbsp;Export</a>
    </form>
  </div>

  <!-- Shortage heatmap -->
  <section class="card" style="margin-bottom:2rem;">
    <h2 class="card-header">Active Shortages — Blood Type &times; Region</h2>
    <div class="card-body">
    <?php if (empty($shortages)): ?>
      <p class="text-muted">No active shortages above the cohort floor (<?php echo MIN_COHORT_SIZE; ?>).</p>
    <?php else:
      $maxReq   = max(array_column($shortages,'open_requests'));
      $btypes   = array_unique(array_column($shortages,'blood_type'));  sort($btypes);
      $regions  = array_unique(array_column($shortages,'region'));
      $hmap = [];
      foreach ($shortages as $s) $hmap[$s['region']][$s['blood_type']] = $s;
    ?>
      <div class="table-wrapper">
      <table class="admin-table text-center" style="min-width:600px;">
        <thead>
          <tr>
            <th style="text-align:left;">Region</th>
            <?php foreach ($btypes as $bt): ?><th><?php echo htmlspecialchars($bt); ?></th><?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($regions as $region): ?>
          <tr>
            <td style="text-align:left;font-weight:600;"><?php echo htmlspecialchars($region); ?></td>
            <?php foreach ($btypes as $bt):
              $cell = $hmap[$region][$bt] ?? null;
              $pct  = $cell ? round($cell['open_requests'] / $maxReq * 80) + 20 : 0;
              $bg   = $cell ? "rgba(185,28,28,{$pct}%)" : '#f9fafb';
              $fg   = $pct > 50 ? '#fff' : '#111';
            ?>
              <td style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;">
                <?php echo $cell ? $cell['open_requests'] : '—'; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <p class="text-muted fs-85" style="margin-top:.5rem;">Cell = open blood requests (darker = more critical).</p>
    <?php endif; ?>
    </div>
  </section>

  <!-- Fulfillment + TTF side by side -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:2rem;">

    <section class="card">
      <h2 class="card-header">Fulfillment Rate by Blood Type</h2>
      <div class="card-body">
      <?php if (empty($fulfillment)): ?>
        <p class="text-muted">Insufficient data.</p>
      <?php else: foreach ($fulfillment as $f):
        $color = $f['pct']>=70?'var(--color-success)':($f['pct']>=40?'#f59e0b':'var(--color-primary)');
      ?>
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;">
          <span style="width:3rem;text-align:right;font-weight:600;"><?php echo htmlspecialchars($f['blood_type']); ?></span>
          <div style="flex:1;background:#e5e7eb;border-radius:.25rem;height:1.1rem;overflow:hidden;">
            <div style="width:<?php echo (int)$f['pct']; ?>%;background:<?php echo $color; ?>;height:100%;"></div>
          </div>
          <span class="fs-85"><?php echo $f['pct']; ?>% <span class="text-muted">(n=<?php echo $f['total']; ?>)</span></span>
        </div>
      <?php endforeach; endif; ?>
      </div>
    </section>

    <section class="card">
      <h2 class="card-header">Average Time-to-Fill (Hours)</h2>
      <div class="card-body">
      <?php if (empty($ttf)): ?>
        <p class="text-muted">No fulfilled requests in range.</p>
      <?php else: ?>
        <table class="admin-table fs-90">
          <thead><tr><th>Blood type</th><th>Region</th><th>Avg&nbsp;hrs</th><th>n</th></tr></thead>
          <tbody>
          <?php foreach ($ttf as $t):
            $h = (float)$t['avg_hours'];
            $c = $h<=4?'var(--color-success)':($h<=24?'#f59e0b':'var(--color-primary)');
          ?>
            <tr>
              <td><?php echo htmlspecialchars($t['blood_type']); ?></td>
              <td><?php echo htmlspecialchars($t['region']); ?></td>
              <td><strong style="color:<?php echo $c; ?>"><?php echo $h; ?>h</strong></td>
              <td class="text-muted"><?php echo $t['fulfilled_count']; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      </div>
    </section>
  </div>

  <!-- Donor availability + top regions -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:2rem;">

    <section class="card">
      <h2 class="card-header">Available Donors by Blood Type</h2>
      <div class="card-body">
      <?php if (empty($donorAvail)): ?>
        <p class="text-muted">No available donors above floor.</p>
      <?php else: ?>
        <table class="admin-table fs-90">
          <thead><tr><th>Blood type</th><th>Region</th><th>Available</th></tr></thead>
          <tbody>
          <?php foreach ($donorAvail as $d): ?>
            <tr>
              <td><?php echo htmlspecialchars($d['blood_type']); ?></td>
              <td><?php echo htmlspecialchars($d['region']); ?></td>
              <td><?php echo $d['available_donors']; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <h2 class="card-header">Top Shortage Regions</h2>
      <div class="card-body">
      <?php if (empty($topRegions)): ?>
        <p class="text-muted">No data.</p>
      <?php else:
        $maxR = max(array_column($topRegions,'open_requests'));
        foreach ($topRegions as $i => $r):
          $pct = round($r['open_requests'] / $maxR * 100);
      ?>
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.4rem;">
          <span class="text-muted fs-80" style="width:1.2rem;"><?php echo $i+1; ?></span>
          <span style="width:9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($r['region']); ?></span>
          <div style="flex:1;background:#e5e7eb;border-radius:.25rem;height:.9rem;overflow:hidden;">
            <div style="width:<?php echo $pct; ?>%;background:var(--color-primary);height:100%;"></div>
          </div>
          <span class="fs-85"><?php echo $r['open_requests']; ?></span>
        </div>
      <?php endforeach; endif; ?>
      </div>
    </section>
  </div>

  <p class="text-muted fs-85">
    All data de-identified. Cohorts &lt;<?php echo MIN_COHORT_SIZE; ?> suppressed.
    <a href="dpo_dashboard.php">DPO Dashboard</a> &middot;
    <a href="<?php echo baseUrl(); ?>/api/v1/analytics.php">Machine-readable API</a>
  </p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
