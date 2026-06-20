<?php
/**
 * GET /api/v1/blood_banks         — paginated list
 * GET /api/v1/blood_banks?id=<n>  — single blood bank
 *
 * Query params: city, state, blood_type, page, per_page
 */
require_once '_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') apiError(405, 'Method not allowed');
requireApiKey($pdo, ['blood_banks:read']);

$pdoR = getReadPdo();
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    $stmt = $pdoR->prepare("SELECT * FROM blood_banks WHERE id = ?");
    $stmt->execute([$id]);
    $bb = $stmt->fetch();
    if (!$bb) apiError(404, 'Blood bank not found');
    apiOk(['data' => $bb]);
}

$page    = pageParam();
$perPage = perPageParam(100);
$offset  = ($page - 1) * $perPage;

$conditions = ['1=1'];
$params     = [];

if (!empty($_GET['city'])) {
    $conditions[] = 'city LIKE ?';
    $params[]     = '%' . $_GET['city'] . '%';
}
if (!empty($_GET['state'])) {
    $conditions[] = 'state LIKE ?';
    $params[]     = '%' . $_GET['state'] . '%';
}
if (!empty($_GET['blood_type']) && isValidBloodType($_GET['blood_type'])) {
    $conditions[] = 'blood_type = ?';
    $params[]     = $_GET['blood_type'];
}

$where = 'WHERE ' . implode(' AND ', $conditions);

$countStmt = $pdoR->prepare("SELECT COUNT(*) FROM blood_banks $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$listStmt = $pdoR->prepare("SELECT * FROM blood_banks $where ORDER BY name LIMIT ? OFFSET ?");
$listStmt->execute(array_merge($params, [$perPage, $offset]));

apiOk([
    'data' => $listStmt->fetchAll(),
    'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / max(1, $perPage))],
]);
