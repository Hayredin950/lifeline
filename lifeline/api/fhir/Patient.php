<?php
/**
 * FHIR R4 — Patient resource (P3)
 *
 * Maps LifeLine donor_profiles → FHIR Patient
 *
 * GET /api/fhir/Patient/{id}            — read by FHIR UUID
 * GET /api/fhir/Patient?_id={uuid}
 * GET /api/fhir/Patient?blood-type=A%2B  — search by blood type (returns Bundle)
 * GET /api/fhir/Patient?city=Addis+Ababa
 */

require_once __DIR__ . '/_bootstrap.php';

$key = requireFhirKey($pdo);

$route = fhirRoute();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    fhirError(405, 'fatal', 'not-supported', 'FHIR Patient is read-only from this endpoint');
}

$resourceId = $route['id'] ?? ($_GET['_id'] ?? null);

// ── Single Patient read ──────────────────────────────────────────────────────
if ($resourceId !== null) {
    $stmt = $pdo->prepare("
        SELECT d.*, u.email, u.created_at AS user_created_at
        FROM donor_profiles d
        JOIN users u ON u.id = d.user_id
        WHERE d.fhir_id = ? AND u.is_active = 1 AND u.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$resourceId]);
    $row = $stmt->fetch();

    if (!$row) {
        fhirError(404, 'error', 'not-found', "Patient/{$resourceId} not found");
    }

    fhirOk(buildPatient($pdo, $row));
}

// ── Search ────────────────────────────────────────────────────────────────────
$where  = ['u.is_active = 1', 'u.deleted_at IS NULL'];
$params = [];

if (isset($_GET['blood-type'])) {
    $bt = strtoupper(trim($_GET['blood-type']));
    $where[]  = 'd.blood_type = ?';
    $params[] = $bt;
}
if (isset($_GET['city'])) {
    $where[]  = 'd.city = ?';
    $params[] = trim($_GET['city']);
}
if (isset($_GET['state'])) {
    $where[]  = 'd.state = ?';
    $params[] = trim($_GET['state']);
}

$page    = max(1, (int)($_GET['_page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['_count'] ?? 20)));
$offset  = ($page - 1) * $perPage;

$sql = "
    SELECT d.*, u.email, u.created_at AS user_created_at
    FROM donor_profiles d
    JOIN users u ON u.id = d.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY d.id ASC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totalStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM donor_profiles d JOIN users u ON u.id = d.user_id WHERE " . implode(' AND ', $where)
);
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$entries = [];
foreach ($rows as $row) {
    $entries[] = [
        'fullUrl'  => FHIR_BASE_URL . '/Patient/' . fhirUuid($pdo, 'donor_profiles', (int)$row['user_id'], $row['fhir_id'] ?? null),
        'resource' => buildPatient($pdo, $row),
        'search'   => ['mode' => 'match'],
    ];
}

fhirOk([
    'resourceType' => 'Bundle',
    'type'         => 'searchset',
    'total'        => $total,
    'link'         => [[
        'relation' => 'self',
        'url'      => FHIR_BASE_URL . '/Patient?' . http_build_query($_GET),
    ]],
    'entry'        => $entries,
]);

// ── Builder ───────────────────────────────────────────────────────────────────
function buildPatient(PDO $pdo, array $row): array {
    $uuid = fhirUuid($pdo, 'donor_profiles', (int)$row['user_id'], $row['fhir_id'] ?? null);
    [$loincCode, $loincDisplay] = bloodTypeLoinc($row['blood_type'] ?? '');

    $patient = [
        'resourceType' => 'Patient',
        'id'           => $uuid,
        'meta'         => [
            'versionId'   => '1',
            'lastUpdated' => fhirInstant($row['updated_at'] ?? $row['user_created_at']),
            'profile'     => ['http://hl7.org/fhir/StructureDefinition/Patient'],
        ],
        'identifier' => [[
            'system' => FHIR_BASE_URL . '/namingsystem/donor-id',
            'value'  => (string)$row['user_id'],
        ]],
        'active' => true,
        'name'   => [[
            'use'  => 'official',
            'text' => $row['full_name'] ?? '',
        ]],
        'telecom' => [],
        'address' => [],
        'extension' => [],
    ];

    if (!empty($row['email'])) {
        $patient['telecom'][] = ['system' => 'email', 'value' => $row['email'], 'use' => 'work'];
    }
    if (!empty($row['phone'])) {
        $patient['telecom'][] = ['system' => 'phone', 'value' => $row['phone'], 'use' => 'mobile'];
    }
    if (!empty($row['date_of_birth'])) {
        $patient['birthDate'] = fhirDate($row['date_of_birth']);
    }
    if (!empty($row['gender'])) {
        $patient['gender'] = strtolower($row['gender']) === 'female' ? 'female' : 'male';
    }

    if (!empty($row['city']) || !empty($row['state'])) {
        $patient['address'][] = array_filter([
            'use'     => 'home',
            'city'    => $row['city'] ?? null,
            'state'   => $row['state'] ?? null,
            'country' => $row['country'] ?? 'Ethiopia',
        ]);
    }

    // Blood group as FHIR extension (no standard R4 element for blood type on Patient).
    $patient['extension'][] = [
        'url'            => 'http://hl7.org/fhir/StructureDefinition/patient-bloodType',
        'valueCodeableConcept' => [
            'coding' => [[
                'system'  => 'http://loinc.org',
                'code'    => $loincCode,
                'display' => $loincDisplay,
            ]],
            'text' => $row['blood_type'] ?? '',
        ],
    ];

    return array_filter($patient, fn($v) => $v !== null && $v !== [] && $v !== '');
}
