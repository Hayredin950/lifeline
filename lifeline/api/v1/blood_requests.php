<?php
/**
 * GET  /api/v1/blood_requests          — paginated list of open requests
 * GET  /api/v1/blood_requests?id=<n>   — single request
 * POST /api/v1/blood_requests          — create a new request (hospital scope)
 *
 * POST body (JSON):
 *   patient_blood_type, units_needed, urgency, required_date (opt),
 *   notes (opt), city (opt), state (opt)
 */
require_once '_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) apiError(405, 'Method not allowed');

$apiKey = requireApiKey($pdo, $method === 'POST' ? ['requests:write'] : ['requests:read']);

$pdoR = getReadPdo();

// --- Single ---
if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdoR->prepare("
        SELECT br.id, br.patient_blood_type, br.units_needed, br.urgency,
               br.status, br.required_date, br.notes, br.created_at,
               hp.hospital_name, hp.city, hp.state, hp.country
        FROM blood_requests br
        JOIN hospital_profiles hp ON br.hospital_id = hp.user_id
        WHERE br.id = ?
    ");
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) apiError(404, 'Blood request not found');
    apiOk(['data' => $req]);
}

// --- List ---
if ($method === 'GET') {
    $page    = pageParam();
    $perPage = perPageParam(100);
    $offset  = ($page - 1) * $perPage;

    $conditions = ['1=1'];
    $params     = [];

    $status = $_GET['status'] ?? 'open';
    if (in_array($status, ['open', 'fulfilled', 'cancelled'], true)) {
        $conditions[] = 'br.status = ?';
        $params[]     = $status;
    }
    if (!empty($_GET['blood_type']) && isValidBloodType($_GET['blood_type'])) {
        $conditions[] = 'br.patient_blood_type = ?';
        $params[]     = $_GET['blood_type'];
    }
    if (!empty($_GET['urgency']) && in_array($_GET['urgency'], ['routine', 'urgent', 'critical'], true)) {
        $conditions[] = 'br.urgency = ?';
        $params[]     = $_GET['urgency'];
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);
    $base  = "FROM blood_requests br JOIN hospital_profiles hp ON br.hospital_id = hp.user_id $where";

    $countStmt = $pdoR->prepare("SELECT COUNT(*) $base");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $listStmt = $pdoR->prepare("
        SELECT br.id, br.patient_blood_type, br.units_needed, br.urgency,
               br.status, br.required_date, br.created_at,
               hp.hospital_name, hp.city, hp.state
        $base ORDER BY br.urgency = 'critical' DESC, br.urgency = 'urgent' DESC, br.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $listStmt->execute(array_merge($params, [$perPage, $offset]));

    apiOk([
        'data' => $listStmt->fetchAll(),
        'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / max(1, $perPage))],
    ]);
}

// --- Create (POST) ---
if ($method === 'POST') {
    // Must be a hospital account.
    $hospitalId = (int)($apiKey['user_id'] ?? 0);
    if (!$hospitalId) apiError(403, 'API key is not linked to a hospital account');

    $body = jsonBody();

    $bloodType = $body['patient_blood_type'] ?? '';
    $units     = (int)($body['units_needed'] ?? 1);
    $urgency   = $body['urgency'] ?? 'routine';
    $reqDate   = $body['required_date'] ?? null;
    $notes     = trim($body['notes'] ?? '');

    if (!isValidBloodType($bloodType))                                      apiError(422, 'Invalid patient_blood_type');
    if ($units < 1 || $units > 100)                                         apiError(422, 'units_needed must be 1–100');
    if (!in_array($urgency, ['routine', 'urgent', 'critical'], true))       apiError(422, 'Invalid urgency');
    if ($reqDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqDate))        apiError(422, 'required_date must be YYYY-MM-DD');

    // Confirm hospital account exists and is active.
    $hStmt = $pdo->prepare("SELECT id FROM hospital_profiles WHERE user_id = ?");
    $hStmt->execute([$hospitalId]);
    if (!$hStmt->fetch()) apiError(403, 'Hospital profile not found for this API key');

    $ins = $pdo->prepare("
        INSERT INTO blood_requests (hospital_id, patient_blood_type, units_needed, urgency, required_date, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, 'open')
    ");
    $ins->execute([$hospitalId, $bloodType, $units, $urgency, $reqDate ?: null, $notes ?: null]);
    $newId = (int)$pdo->lastInsertId();

    apiOk(['data' => ['id' => $newId, 'status' => 'open']], 201);
}
