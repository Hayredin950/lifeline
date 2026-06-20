<?php
/**
 * GET /api/v1/donors          — paginated list
 * GET /api/v1/donors?id=<n>   — single donor (public fields only; no PII)
 *
 * Query params (list):
 *   blood_type, city, state, country, available=1, page, per_page
 */
require_once '_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') apiError(405, 'Method not allowed');
requireApiKey($pdo, ['donors:read']);

$pdoR = getReadPdo();
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- Single donor ---
if ($id) {
    $stmt = $pdoR->prepare("
        SELECT dp.user_id AS id, dp.full_name, dp.blood_type,
               dp.city, dp.state, dp.country,
               dp.is_available, dp.tier, dp.total_donations, dp.donation_points,
               dp.last_donation_date
        FROM donor_profiles dp
        JOIN users u ON dp.user_id = u.id
        WHERE dp.user_id = ? AND u.is_active = 1 AND u.deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    $donor = $stmt->fetch();
    if (!$donor) apiError(404, 'Donor not found');
    apiOk(['data' => $donor]);
}

// --- List ---
$page    = pageParam();
$perPage = perPageParam(100);
$offset  = ($page - 1) * $perPage;

$conditions = ['u.is_active = 1', 'u.deleted_at IS NULL'];
$params     = [];

if (!empty($_GET['blood_type']) && isValidBloodType($_GET['blood_type'])) {
    $conditions[] = 'dp.blood_type = ?';
    $params[]     = $_GET['blood_type'];
}
if (!empty($_GET['city'])) {
    $conditions[] = 'dp.city LIKE ?';
    $params[]     = '%' . $_GET['city'] . '%';
}
if (!empty($_GET['state'])) {
    $conditions[] = 'dp.state LIKE ?';
    $params[]     = '%' . $_GET['state'] . '%';
}
if (!empty($_GET['country'])) {
    $conditions[] = 'dp.country = ?';
    $params[]     = $_GET['country'];
}
if (filter_var($_GET['available'] ?? '', FILTER_VALIDATE_BOOLEAN)) {
    $conditions[] = 'dp.is_available = 1';
    $conditions[] = '(dp.last_donation_date IS NULL OR dp.last_donation_date <= DATE_SUB(CURDATE(), INTERVAL ? DAY))';
    $params[]     = DONATION_COOLOFF_DAYS;
}

$where = 'WHERE ' . implode(' AND ', $conditions);
$base  = "FROM donor_profiles dp JOIN users u ON dp.user_id = u.id $where";

$countStmt = $pdoR->prepare("SELECT COUNT(*) $base");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$listStmt = $pdoR->prepare("
    SELECT dp.user_id AS id, dp.full_name, dp.blood_type,
           dp.city, dp.state, dp.country,
           dp.is_available, dp.tier, dp.total_donations, dp.donation_points
    $base ORDER BY dp.full_name LIMIT ? OFFSET ?
");
$listStmt->execute(array_merge($params, [$perPage, $offset]));

apiOk([
    'data' => $listStmt->fetchAll(),
    'meta' => [
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => (int)ceil($total / max(1, $perPage)),
    ],
]);
