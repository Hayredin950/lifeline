<?php
/**
 * Worker: compute demand forecasts + donor propensity scores.
 * Run nightly via cron: php worker/compute_forecasts.php
 *
 * DEMAND FORECAST: naive weighted moving average over the last 4 weeks of
 * closed blood requests per (blood_type × region). No PII involved.
 *
 * DONOR PROPENSITY: Laplace-smoothed score from recency, frequency, response
 * rate, and cool-off position. De-identified (only donor_id stored).
 */

require_once __DIR__ . '/../lifeline/includes/functions.php';

// $pdo is initialized by includes/db.php (loaded via functions.php).
$today     = date('Y-m-d');
$nextMonday = date('Y-m-d', strtotime('next monday', strtotime($today)));

echo "[forecast] Starting at {$today}\n";

// ── 1. Demand forecast ───────────────────────────────────────────────────────
// Look at weekly request volumes for the past 12 weeks, per blood_type.
// Region is inferred from hospital location (simplified to a single region for
// this implementation; extend with region JOIN when multi-region data exists).

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$regionId   = defined('REGION_ID') ? REGION_ID : 'et-central';

// Fetch weekly counts for last 12 weeks.
$weeklyCounts = $pdo->query("
    SELECT patient_blood_type,
           DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) AS week_start,
           COUNT(*) AS cnt
    FROM blood_requests
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
    GROUP BY patient_blood_type, week_start
    ORDER BY week_start ASC
")->fetchAll();

$weekData = [];
foreach ($weeklyCounts as $row) {
    $weekData[$row['patient_blood_type']][$row['week_start']] = (int)$row['cnt'];
}

// Weighted moving average: weights 1,2,3,4 on oldest→newest 4 weeks.
$weights = [1, 2, 3, 4];
$weightSum = array_sum($weights);

foreach ($bloodTypes as $bt) {
    $series = $weekData[$bt] ?? [];
    arsort($series); // newest first
    $recent = array_slice(array_values($series), 0, 4);
    $recent = array_reverse($recent); // oldest→newest

    $predicted = 0;
    if (count($recent) > 0) {
        $wma = 0;
        foreach ($recent as $i => $cnt) {
            $w    = $weights[$i] ?? 1;
            $wma += $cnt * $w;
        }
        $predicted = (int)ceil($wma / $weightSum);
    }

    // Confidence: higher when we have more weeks of data.
    $confidence = min(90, count($recent) * 20 + 10);

    $pdo->prepare("
        INSERT INTO demand_forecasts (blood_type, region_id, period_start, predicted_units, confidence)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE predicted_units = VALUES(predicted_units), confidence = VALUES(confidence)
    ")->execute([$bt, $regionId, $nextMonday, $predicted, $confidence]);
}

// Back-fill actual units for the week that just closed (last Monday).
$lastMonday = date('Y-m-d', strtotime('last monday', strtotime($today)));
$actStmt = $pdo->prepare("
    SELECT patient_blood_type, COUNT(*) AS cnt
    FROM blood_requests
    WHERE DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) = ?
    GROUP BY patient_blood_type
");
$actStmt->execute([$lastMonday]);
foreach ($actStmt->fetchAll() as $row) {
    $pdo->prepare("UPDATE demand_forecasts SET actual_units = ? WHERE blood_type = ? AND region_id = ? AND period_start = ?")
        ->execute([(int)$row['cnt'], $row['patient_blood_type'], $regionId, $lastMonday]);
}

echo "[forecast] Demand forecasts written for week {$nextMonday}\n";

// ── 2. Donor propensity scores ───────────────────────────────────────────────
// Features (all from existing tables, no PII stored in output):
//   recency_days:       days since last donation (null if never)
//   frequency_6m:       count of donations in last 6 months
//   response_rate:      (matched contacted/confirmed + donated) / total contacted
//   cool_off_fraction:  elapsed / 56 days (0 if beyond cool-off or never donated)
//
// Score = 0.4*(1 - recency_norm) + 0.3*freq_norm + 0.2*response + 0.1*(1 - cof)
// All terms Laplace-smoothed to avoid 0/1 extremes.

$donors = $pdo->query("
    SELECT dp.user_id,
           DATEDIFF(NOW(), dp.last_donation_date) AS recency_days,
           (SELECT COUNT(*) FROM donation_history dh WHERE dh.donor_id = dp.user_id
            AND dh.donation_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)) AS freq_6m,
           (SELECT COUNT(*) FROM donor_matches dm WHERE dm.donor_id = dp.user_id
            AND dm.status IN ('contacted','confirmed','donated')) AS contacted,
           (SELECT COUNT(*) FROM donor_matches dm WHERE dm.donor_id = dp.user_id
            AND dm.status = 'donated') AS donated
    FROM donor_profiles dp
    WHERE dp.is_available = 1
")->fetchAll();

$maxFreq = 1;
foreach ($donors as $d) {
    if ((int)$d['freq_6m'] > $maxFreq) $maxFreq = (int)$d['freq_6m'];
}

$ins = $pdo->prepare("
    INSERT INTO donor_propensity_scores
        (donor_id, score, recency_days, frequency_6m, response_rate, cool_off_fraction)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        score = VALUES(score), recency_days = VALUES(recency_days),
        frequency_6m = VALUES(frequency_6m), response_rate = VALUES(response_rate),
        cool_off_fraction = VALUES(cool_off_fraction)
");

$cooloff = DONATION_COOLOFF_DAYS;

foreach ($donors as $d) {
    $recencyDays = ($d['recency_days'] !== null) ? (int)$d['recency_days'] : null;
    $freq6m      = (int)$d['freq_6m'];
    $contacted   = (int)$d['contacted'];
    $donated     = (int)$d['donated'];

    // Laplace-smoothed response rate.
    $responseRate = ($donated + 0.5) / ($contacted + 1);

    // Recency: 0 if never donated, else normalised over 365 days.
    $recencyNorm = ($recencyDays !== null) ? min(1.0, $recencyDays / 365.0) : 1.0;

    // Frequency: normalised by max in the cohort.
    $freqNorm = ($freq6m + 0.5) / ($maxFreq + 1);

    // Cool-off fraction: how far into the 56-day window (0 = just donated; 1 = eligible again).
    $cof = 0.0;
    if ($recencyDays !== null && $recencyDays < $cooloff) {
        $cof = $recencyDays / $cooloff;
    }

    $score = round(
        0.4 * (1.0 - $recencyNorm) +
        0.3 * $freqNorm +
        0.2 * $responseRate +
        0.1 * (1.0 - $cof),
        4
    );
    $score = max(0.0, min(1.0, $score));

    $ins->execute([
        (int)$d['user_id'],
        $score,
        $recencyDays,
        $freq6m,
        round($responseRate, 3),
        round($cof, 3),
    ]);
}

echo "[forecast] Propensity scores computed for " . count($donors) . " donors\n";
echo "[forecast] Done.\n";
