<?php
/**
 * Admin: demand forecasting dashboard + donor propensity scoring widget.
 * Reads from demand_forecasts and donor_propensity_scores (de-identified).
 */
require_once '../includes/functions.php';
requireAdmin();

// ── Demand forecasts: next 4 weeks ──────────────────────────────────────────
$forecasts = $pdo->query("
    SELECT blood_type, period_start, predicted_units, actual_units, confidence
    FROM demand_forecasts
    WHERE period_start >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
    ORDER BY period_start ASC, blood_type ASC
")->fetchAll();

// Group by week.
$byWeek = [];
$weeks  = [];
foreach ($forecasts as $f) {
    $byWeek[$f['period_start']][$f['blood_type']] = $f;
    $weeks[$f['period_start']] = true;
}
$weeks = array_keys($weeks);
sort($weeks);

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// ── Propensity scores: top and bottom donors ─────────────────────────────────
$topPropensity = $pdo->query("
    SELECT dps.*, dp.full_name, dp.blood_type AS donor_blood_type, dp.city
    FROM donor_propensity_scores dps
    JOIN donor_profiles dp ON dp.user_id = dps.donor_id
    ORDER BY dps.score DESC
    LIMIT 15
")->fetchAll();

$propensityCount = $pdo->query("SELECT COUNT(*) FROM donor_propensity_scores")->fetchColumn();
$avgScore        = $pdo->query("SELECT ROUND(AVG(score), 3) FROM donor_propensity_scores")->fetchColumn();

// ── Accuracy of past forecasts (where actual known) ──────────────────────────
$accuracy = $pdo->query("
    SELECT blood_type,
           ROUND(AVG(ABS(actual_units - predicted_units)), 1) AS mae,
           COUNT(*) AS weeks
    FROM demand_forecasts
    WHERE actual_units IS NOT NULL
    GROUP BY blood_type
    ORDER BY blood_type
")->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Demand Forecasting &amp; Propensity Scores</h1>
        <div class="flex gap-8">
            <form method="POST" action="<?php echo baseUrl(); ?>/admin/run_forecast.php">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <button type="submit" class="btn btn-secondary">Run Forecast Worker</button>
            </form>
            <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back</a>
        </div>
    </div>
    <p class="text-muted fs-90">
        Naive weighted-moving-average demand forecast (4-week WMA, weights 1-2-3-4).
        Donor propensity scores are de-identified (donor_id + feature vector only).
        Scores update nightly via <code>worker/compute_forecasts.php</code>.
    </p>
</div>

<div class="card">
    <h2>Weekly Demand Forecasts</h2>
    <?php if ($byWeek): ?>
    <div class="table-wrapper">
        <table aria-label="Weekly blood type demand forecasts">
            <thead>
                <tr>
                    <th scope="col">Blood Type</th>
                    <?php foreach ($weeks as $w): ?>
                    <th scope="col" class="text-center"><?php echo htmlspecialchars($w); ?><br><span class="fs-75 text-muted">Pred / Actual</span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bloodTypes as $bt): ?>
                <tr>
                    <td><strong><?php echo $bt; ?></strong></td>
                    <?php foreach ($weeks as $w):
                        $cell = $byWeek[$w][$bt] ?? null;
                        if (!$cell): ?>
                        <td class="text-center text-muted fs-80">—</td>
                        <?php else:
                            $pred   = (int)$cell['predicted_units'];
                            $actual = $cell['actual_units'];
                            $conf   = (int)$cell['confidence'];
                            $delta  = ($actual !== null) ? ($actual - $pred) : null;
                            $deltaClass = ($delta === null) ? '' : ($delta > 0 ? 'text-crimson' : 'text-success-dark');
                        ?>
                        <td class="text-center fs-85">
                            <span title="Confidence: <?php echo $conf; ?>%"><?php echo $pred; ?></span>
                            <?php if ($actual !== null): ?>
                            / <span class="<?php echo $deltaClass; ?>"><?php echo $actual; ?></span>
                            <?php else: ?>
                            / <span class="text-muted">?</span>
                            <?php endif; ?>
                            <div class="fs-75 text-muted"><?php echo $conf; ?>% conf</div>
                        </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="fs-80 text-muted mt-8">Pred = predicted units for the week. Actual = closed requests (filled in after week ends). Confidence rises with historical data.</p>
    <?php else: ?>
    <p class="text-muted">No forecast data yet. Run the forecast worker to generate predictions.</p>
    <?php endif; ?>
</div>

<?php if ($accuracy): ?>
<div class="card">
    <h2>Forecast Accuracy (MAE by Blood Type)</h2>
    <div class="flex flex-wrap gap-12">
        <?php foreach ($accuracy as $a): ?>
        <div class="stat-card" style="min-width:100px">
            <h3><?php echo htmlspecialchars($a['blood_type']); ?></h3>
            <p>MAE: <strong><?php echo $a['mae']; ?></strong><br>
            <span class="fs-75 text-muted"><?php echo (int)$a['weeks']; ?> weeks</span></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h2>Donor Propensity Scores</h2>
    <div class="flex gap-16 flex-wrap mb-16">
        <div class="stat-card">
            <h3><?php echo (int)$propensityCount; ?></h3>
            <p>Scored Donors</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $avgScore !== null ? $avgScore : '—'; ?></h3>
            <p>Average Score</p>
        </div>
    </div>
    <p class="text-muted fs-85 mb-12">Score 0–1: higher = more likely to donate in next 30 days. Derived from recency, 6-month frequency, response rate, cool-off position. No PII in this table.</p>
    <?php if ($topPropensity): ?>
    <h3 class="fs-100 mb-8">Top 15 by Propensity</h3>
    <div class="table-wrapper">
        <table aria-label="Top donor propensity scores">
            <thead>
                <tr>
                    <th>Donor</th>
                    <th>Blood Type</th>
                    <th>City</th>
                    <th>Score</th>
                    <th>Recency (days)</th>
                    <th>Freq (6m)</th>
                    <th>Response Rate</th>
                    <th>Cool-Off %</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($topPropensity as $d):
                $score = (float)$d['score'];
                $scoreClass = $score >= 0.7 ? 'text-success-dark fw-600' : ($score >= 0.4 ? '' : 'text-muted');
            ?>
            <tr>
                <td><?php echo htmlspecialchars($d['full_name']); ?></td>
                <td><strong><?php echo htmlspecialchars($d['donor_blood_type']); ?></strong></td>
                <td class="fs-85"><?php echo htmlspecialchars($d['city'] ?? '—'); ?></td>
                <td class="<?php echo $scoreClass; ?>"><?php echo number_format($score, 3); ?></td>
                <td><?php echo $d['recency_days'] !== null ? (int)$d['recency_days'] : '—'; ?></td>
                <td><?php echo (int)$d['frequency_6m']; ?></td>
                <td><?php echo number_format((float)$d['response_rate'] * 100, 0); ?>%</td>
                <td><?php echo number_format((float)$d['cool_off_fraction'] * 100, 0); ?>%</td>
                <td class="fs-80 text-muted"><?php echo htmlspecialchars(substr($d['computed_at'], 0, 10)); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted">No propensity scores yet. Run the forecast worker.</p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
