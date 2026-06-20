<?php
/**
 * FHIR R4 — ServiceRequest resource (P3)
 *
 * Maps LifeLine blood_requests → FHIR ServiceRequest
 * (blood request = "order" for blood product provision)
 *
 * GET  /api/fhir/ServiceRequest/{id}          — read by FHIR UUID
 * GET  /api/fhir/ServiceRequest?status=active — search
 * POST /api/fhir/ServiceRequest               — create (EHR → LifeLine)
 */

require_once __DIR__ . '/_bootstrap.php';

$key    = requireFhirKey($pdo);
$route  = fhirRoute();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    $resourceId = $route['id'] ?? ($_GET['_id'] ?? null);

    // ── Single read ──────────────────────────────────────────────────────────
    if ($resourceId !== null) {
        $stmt = $pdo->prepare("
            SELECT r.*, h.name AS hospital_name
            FROM blood_requests r
            LEFT JOIN hospital_profiles h ON h.user_id = r.hospital_id
            WHERE r.fhir_id = ?
            LIMIT 1
        ");
        $stmt->execute([$resourceId]);
        $row = $stmt->fetch();
        if (!$row) fhirError(404, 'error', 'not-found', "ServiceRequest/{$resourceId} not found");
        fhirOk(buildServiceRequest($pdo, $row));
    }

    // ── Search ──────────────────────────────────────────────────────────────
    $where  = ['1=1'];
    $params = [];

    if (isset($_GET['status'])) {
        $map = ['active' => 'open', 'completed' => 'fulfilled', 'cancelled' => 'cancelled'];
        $fhirStatus = strtolower(trim($_GET['status']));
        if (isset($map[$fhirStatus])) {
            $where[]  = 'r.status = ?';
            $params[] = $map[$fhirStatus];
        }
    }
    if (isset($_GET['blood-type'])) {
        $where[]  = 'r.blood_type = ?';
        $params[] = strtoupper(trim($_GET['blood-type']));
    }
    if (isset($_GET['city'])) {
        $where[]  = 'r.city = ?';
        $params[] = trim($_GET['city']);
    }
    if (isset($_GET['urgency'])) {
        $map2 = ['stat' => 'critical', 'urgent' => 'urgent', 'routine' => 'standard'];
        $u = strtolower(trim($_GET['urgency']));
        if (isset($map2[$u])) {
            $where[]  = 'r.urgency = ?';
            $params[] = $map2[$u];
        }
    }

    $page    = max(1, (int)($_GET['_page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['_count'] ?? 20)));
    $offset  = ($page - 1) * $perPage;

    $sql = "
        SELECT r.*, h.name AS hospital_name
        FROM blood_requests r
        LEFT JOIN hospital_profiles h ON h.user_id = r.hospital_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $total = (int)$pdo->prepare(
        "SELECT COUNT(*) FROM blood_requests r WHERE " . implode(' AND ', $where)
    )->execute($params) ? 0 : 0;
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests r WHERE " . implode(' AND ', $where));
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $entries = [];
    foreach ($rows as $row) {
        $entries[] = [
            'fullUrl'  => FHIR_BASE_URL . '/ServiceRequest/' . fhirUuid($pdo, 'blood_requests', (int)$row['id'], $row['fhir_id'] ?? null),
            'resource' => buildServiceRequest($pdo, $row),
            'search'   => ['mode' => 'match'],
        ];
    }

    fhirOk([
        'resourceType' => 'Bundle',
        'type'         => 'searchset',
        'total'        => $total,
        'entry'        => $entries,
    ]);

} elseif ($method === 'POST') {

    // ── Create from FHIR payload ─────────────────────────────────────────────
    $body = fhirBody();
    if (($body['resourceType'] ?? '') !== 'ServiceRequest') {
        fhirError(400, 'error', 'invalid', 'resourceType must be ServiceRequest');
    }

    // Extract blood type from orderDetail coding.
    $bloodType = null;
    foreach ($body['orderDetail'] ?? [] as $od) {
        foreach ($od['coding'] ?? [] as $c) {
            if (($c['system'] ?? '') === 'http://loinc.org') {
                // Reverse LOINC lookup.
                $loincToType = [
                    '882-1'=>'A+','883-9'=>'A-','884-7'=>'B+','885-4'=>'B-',
                    '886-2'=>'AB+','887-0'=>'AB-','888-8'=>'O+','889-6'=>'O-',
                ];
                $bloodType = $loincToType[$c['code'] ?? ''] ?? null;
            }
        }
        $bloodType = $bloodType ?? ($od['text'] ?? null);
    }

    $urgencyMap = ['stat' => 'critical', 'urgent' => 'urgent', 'routine' => 'standard'];
    $urgency    = $urgencyMap[strtolower($body['priority'] ?? 'routine')] ?? 'standard';

    $note    = implode(' ', array_column($body['note'] ?? [], 'text'));
    $subject = $body['subject']['reference'] ?? '';  // e.g. "Patient/{uuid}"

    if (!$bloodType) {
        fhirError(422, 'error', 'required', 'orderDetail with blood-type LOINC code is required');
    }

    // Find the hospital_id from the requester (Organization reference).
    $hospitalId = null;
    $requesterRef = $body['requester']['reference'] ?? '';
    if (preg_match('/Organization\/([a-f0-9-]{36})/', $requesterRef, $m)) {
        $stmt = $pdo->prepare("SELECT user_id FROM hospital_profiles WHERE fhir_id = ?");
        $stmt->execute([$m[1]]);
        $row = $stmt->fetch();
        $hospitalId = $row ? (int)$row['user_id'] : null;
    }

    $ins = $pdo->prepare("
        INSERT INTO blood_requests
          (hospital_id, blood_type, urgency, units_needed, status, additional_notes, city, state)
        VALUES (?, ?, ?, ?, 'open', ?, ?, ?)
    ");
    $ins->execute([
        $hospitalId,
        $bloodType,
        $urgency,
        (int)($body['quantity']['value'] ?? 1),
        $note ?: null,
        $body['locationReference']['display'] ?? null,
        null,
    ]);
    $newId = (int)$pdo->lastInsertId();

    // Assign FHIR UUID.
    $uuid = generateUuid();
    $pdo->prepare("UPDATE blood_requests SET fhir_id = ? WHERE id = ?")->execute([$uuid, $newId]);

    $stmt = $pdo->prepare("SELECT * FROM blood_requests WHERE id = ?");
    $stmt->execute([$newId]);
    $newRow = $stmt->fetch();
    $newRow['hospital_name'] = null;

    fhirCreated(buildServiceRequest($pdo, $newRow), 'ServiceRequest', $uuid);

} else {
    fhirError(405, 'fatal', 'not-supported', 'Method not allowed');
}

// ── Builder ───────────────────────────────────────────────────────────────────
function buildServiceRequest(PDO $pdo, array $row): array {
    $uuid = fhirUuid($pdo, 'blood_requests', (int)$row['id'], $row['fhir_id'] ?? null);
    [$loincCode, $loincDisplay] = bloodTypeLoinc($row['blood_type'] ?? '');

    $statusMap = ['open' => 'active', 'fulfilled' => 'completed', 'cancelled' => 'revoked'];
    $fhirStatus = $statusMap[$row['status'] ?? 'open'] ?? 'active';

    $priorityMap = ['critical' => 'stat', 'urgent' => 'urgent', 'standard' => 'routine'];
    $priority = $priorityMap[$row['urgency'] ?? 'standard'] ?? 'routine';

    $sr = [
        'resourceType' => 'ServiceRequest',
        'id'           => $uuid,
        'meta'         => [
            'lastUpdated' => fhirInstant($row['updated_at'] ?? $row['created_at']),
        ],
        'status'       => $fhirStatus,
        'intent'       => 'order',
        'priority'     => $priority,
        'category'     => [[
            'coding' => [[
                'system'  => 'http://snomed.info/sct',
                'code'    => '410606002',
                'display' => 'Blood transfusion therapy',
            ]],
        ]],
        'code' => [
            'coding' => [[
                'system'  => 'http://snomed.info/sct',
                'code'    => '116154003',
                'display' => 'Patient requires blood',
            ]],
            'text' => 'Blood request — ' . ($row['blood_type'] ?? ''),
        ],
        'orderDetail' => [[
            'coding' => [[
                'system'  => 'http://loinc.org',
                'code'    => $loincCode,
                'display' => $loincDisplay,
            ]],
            'text' => $row['blood_type'] ?? '',
        ]],
        'quantity' => [
            'value'  => (int)($row['units_needed'] ?? 1),
            'unit'   => 'unit',
            'system' => 'http://unitsofmeasure.org',
            'code'   => '[arb\'U]',
        ],
        'authoredOn' => fhirInstant($row['created_at']),
    ];

    if (!empty($row['hospital_name'])) {
        $sr['requester'] = ['display' => $row['hospital_name']];
    }
    if (!empty($row['city'])) {
        $sr['locationReference'] = [
            'display' => trim(($row['city'] ?? '') . ', ' . ($row['state'] ?? ''), ', '),
        ];
    }
    if (!empty($row['additional_notes'])) {
        $sr['note'] = [['text' => $row['additional_notes']]];
    }

    return $sr;
}
