<?php
/**
 * LifeLine REST API v1 — Public-health shortage analytics (P3 · Doc 13 §4)
 *
 * GET /api/v1/analytics.php?type=shortage[&range=90d][&region=Addis+Ababa]
 * GET /api/v1/analytics.php?type=fulfillment[&range=90d]
 * GET /api/v1/analytics.php?type=donors[&blood_type=O%2B]
 *
 * Auth: Bearer token with scope "analytics" or "*".
 * All responses are de-identified aggregates — no PII, cohorts < 5 suppressed.
 */

require_once __DIR__ . '/_bootstrap.php';

$key = requireApiKey($pdo, ['analytics']);

define('ANALYTICS_MIN_COHORT', 5);

$type = strtolower(trim($_GET['type'] ?? 'shortage'));

// Date range
$rangeMap = ['30d' => 30, '90d' => 90, '180d' => 180, '365d' => 365];
$range    = array_key_exists($_GET['range'] ?? '', $rangeMap) ? $_GET['range'] : '90d';
$days     = $rangeMap[$range];
$dateWhere = "AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";

switch ($type) {

    // ── Shortage heatmap ──────────────────────────────────────────────────────
    case 'shortage':
        $where  = ["status = 'open'"];
        $params = [];
        if (isset($_GET['region'])) {
            $where[]  = 'state = ?';
            $params[] = trim($_GET['region']);
        }
        $stmt = $pdo->prepare("
            SELECT blood_type,
                   COALESCE(state,'Unknown') AS region,
                   COUNT(*)                  AS open_requests,
                   SUM(units_needed)         AS units_needed
            FROM   blood_requests
            WHERE  " . implode(' AND ', $where) . "
            GROUP  BY blood_type, region
            HAVING open_requests >= " . ANALYTICS_MIN_COHORT . "
            ORDER  BY open_requests DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        apiOk([
            'type'            => 'shortage',
            'range'           => $range,
            'min_cohort_size' => ANALYTICS_MIN_COHORT,
            'generated_at'    => gmdate('Y-m-d\TH:i:s\Z'),
            'data'            => $rows,
            'note'            => 'Cohorts below ' . ANALYTICS_MIN_COHORT . ' are suppressed. No personally-identifying information is included.',
        ]);
        break;

    // ── Fulfillment rate ──────────────────────────────────────────────────────
    case 'fulfillment':
        $stmt = $pdo->prepare("
            SELECT blood_type,
                   COUNT(*)                                         AS total_requests,
                   SUM(status = 'fulfilled')                       AS fulfilled,
                   ROUND(SUM(status='fulfilled') / COUNT(*) * 100) AS fulfillment_pct,
                   ROUND(AVG(TIMESTAMPDIFF(
                       HOUR,
                       r.created_at,
                       (SELECT MIN(d.created_at) FROM donation_history d WHERE d.request_id = r.id)
                   )), 1) AS avg_hours_to_fill
            FROM   blood_requests r
            WHERE  1=1 {$dateWhere}
            GROUP  BY blood_type
            HAVING total_requests >= " . ANALYTICS_MIN_COHORT . "
            ORDER  BY fulfillment_pct ASC
        ");
        $stmt->execute();

        apiOk([
            'type'            => 'fulfillment',
            'range'           => $range,
            'min_cohort_size' => ANALYTICS_MIN_COHORT,
            'generated_at'    => gmdate('Y-m-d\TH:i:s\Z'),
            'data'            => $stmt->fetchAll(),
        ]);
        break;

    // ── Donor availability ────────────────────────────────────────────────────
    case 'donors':
        $where  = ['u.is_active = 1', 'u.deleted_at IS NULL', 'd.is_available = 1'];
        $params = [];
        if (isset($_GET['blood_type'])) {
            $where[]  = 'd.blood_type = ?';
            $params[] = strtoupper(trim($_GET['blood_type']));
        }
        if (isset($_GET['region'])) {
            $where[]  = 'd.state = ?';
            $params[] = trim($_GET['region']);
        }
        $stmt = $pdo->prepare("
            SELECT d.blood_type,
                   COALESCE(d.state,'Unknown') AS region,
                   COUNT(*)                    AS available_donors
            FROM   donor_profiles d
            JOIN   users u ON u.id = d.user_id
            WHERE  " . implode(' AND ', $where) . "
            GROUP  BY d.blood_type, region
            HAVING available_donors >= " . ANALYTICS_MIN_COHORT . "
            ORDER  BY available_donors DESC
        ");
        $stmt->execute($params);

        apiOk([
            'type'            => 'donors',
            'min_cohort_size' => ANALYTICS_MIN_COHORT,
            'generated_at'    => gmdate('Y-m-d\TH:i:s\Z'),
            'data'            => $stmt->fetchAll(),
        ]);
        break;

    // ── Regional demand trend ─────────────────────────────────────────────────
    case 'trend':
        $stmt = $pdo->prepare("
            SELECT blood_type,
                   DATE_FORMAT(created_at, '%Y-W%u') AS week,
                   COUNT(*)                           AS requests
            FROM   blood_requests
            WHERE  1=1 {$dateWhere}
            GROUP  BY blood_type, week
            HAVING requests >= " . ANALYTICS_MIN_COHORT . "
            ORDER  BY week ASC, blood_type
        ");
        $stmt->execute();

        apiOk([
            'type'            => 'trend',
            'range'           => $range,
            'min_cohort_size' => ANALYTICS_MIN_COHORT,
            'generated_at'    => gmdate('Y-m-d\TH:i:s\Z'),
            'data'            => $stmt->fetchAll(),
        ]);
        break;

    default:
        apiError(400, 'Invalid type. Supported: shortage, fulfillment, donors, trend');
}
