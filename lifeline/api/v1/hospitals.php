<?php
/**
 * GET /api/v1/hospitals         — paginated list of verified hospitals
 * GET /api/v1/hospitals?id=<n>  — single hospital
 */
require_once '_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') apiError(405, 'Method not allowed');
requireApiKey($pdo, ['hospitals:read']);

$pdoR = getReadPdo();
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    $stmt = $pdoR->prepare("
        SELECT hp.user_id AS id, hp.hospital_name, hp.city, hp.state, hp.country,
               hp.is_verified, hp.created_at
        FROM hospital_profiles hp
        JOIN users u ON hp.user_id = u.id
        WHERE hp.user_id = ? AND u.is_active = 1 AND u.deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    $hospital = $stmt->fetch();
    if (!$hospital) apiError(404, 'Hospital not found');
    apiOk(['data' => $hospital]);
}

$page    = pageParam();
$perPage = perPageParam(100);
$offset  = ($page - 1) * $perPage;

$conditions = ['u.is_active = 1', 'u.deleted_at IS NULL'];
$params     = [];

if (filter_var($_GET['verified'] ?? '', FILTER_VALIDATE_BOOLEAN)) {
    $conditions[] = 'hp.is_verified = 1';
}
if (!empty($_GET['city'])) {
    $conditions[] = 'hp.city LIKE ?';
    $params[]     = '%' . $_GET['city'] . '%';
}
if (!empty($_GET['state'])) {
    $conditions[] = 'hp.state LIKE ?';
    $params[]     = '%' . $_GET['state'] . '%';
}

$where = 'WHERE ' . implode(' AND ', $conditions);
$base  = "FROM hospital_profiles hp JOIN users u ON hp.user_id = u.id $where";

$countStmt = $pdoR->prepare("SELECT COUNT(*) $base");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$listStmt = $pdoR->prepare("
    SELECT hp.user_id AS id, hp.hospital_name, hp.city, hp.state, hp.country, hp.is_verified
    $base ORDER BY hp.hospital_name LIMIT ? OFFSET ?
");
$listStmt->execute(array_merge($params, [$perPage, $offset]));

apiOk([
    'data' => $listStmt->fetchAll(),
    'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / max(1, $perPage))],
]);
