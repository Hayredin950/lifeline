<?php
/**
 * FHIR R4 — Observation resource (P3)
 *
 * Maps LifeLine donation_history → FHIR Observation
 * (each donation event = blood-group observation on the donor)
 *
 * GET /api/fhir/Observation/{id}                — read by FHIR UUID
 * GET /api/fhir/Observation?subject=Patient/{id} — all donations for a donor
 */

require_once __DIR__ . '/_bootstrap.php';

$key    = requireFhirKey($pdo);
$route  = fhirRoute();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    fhirError(405, 'fatal', 'not-supported', 'Observation is read-only');
}

$resourceId = $route['id'] ?? ($_GET['_id'] ?? null);

// ── Single read ──────────────────────────────────────────────────────────────
if ($resourceId !== null) {
    $stmt = $pdo->prepare("
        SELECT h.*, d.fhir_id AS donor_fhir_id
        FROM donation_history h
        LEFT JOIN donor_profiles d ON d.user_id = h.donor_id
        WHERE h.fhir_id = ?
        LIMIT 1
    ");
    $stmt->execute([$resourceId]);
    $row = $stmt->fetch();
    if (!$row) fhirError(404, 'error', 'not-found', "Observation/{$resourceId} not found");
    fhirOk(buildObservation($pdo, $row));
}

// ── Search by subject (Patient FHIR UUID) ────────────────────────────────────
if (isset($_GET['subject'])) {
    $ref = $_GET['subject'];
    // Accept "Patient/{uuid}" or bare UUID.
    $patientUuid = preg_replace('/^Patient\//', '', $ref);

    $donorStmt = $pdo->prepare("SELECT user_id FROM donor_profiles WHERE fhir_id = ?");
    $donorStmt->execute([$patientUuid]);
    $donor = $donorStmt->fetch();
    if (!$donor) fhirError(404, 'error', 'not-found', "Patient/{$patientUuid} not found");

    $page    = max(1, (int)($_GET['_page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['_count'] ?? 20)));
    $offset  = ($page - 1) * $perPage;

    $rows = $pdo->prepare("
        SELECT h.*, dp.fhir_id AS donor_fhir_id
        FROM donation_history h
        LEFT JOIN donor_profiles dp ON dp.user_id = h.donor_id
        WHERE h.donor_id = ?
        ORDER BY h.donation_date DESC
        LIMIT {$perPage} OFFSET {$offset}
    ");
    $rows->execute([$donor['user_id']]);
    $rows = $rows->fetchAll();

    $entries = [];
    foreach ($rows as $row) {
        $entries[] = [
            'fullUrl'  => FHIR_BASE_URL . '/Observation/' . fhirUuid($pdo, 'donation_history', (int)$row['id'], $row['fhir_id'] ?? null),
            'resource' => buildObservation($pdo, $row),
            'search'   => ['mode' => 'match'],
        ];
    }

    fhirOk([
        'resourceType' => 'Bundle',
        'type'         => 'searchset',
        'total'        => count($entries),
        'entry'        => $entries,
    ]);
}

fhirError(400, 'error', 'required', 'Provide an Observation id or ?subject=Patient/{id}');

// ── Builder ───────────────────────────────────────────────────────────────────
function buildObservation(PDO $pdo, array $row): array {
    $uuid = fhirUuid($pdo, 'donation_history', (int)$row['id'], $row['fhir_id'] ?? null);
    [$loincCode, $loincDisplay] = bloodTypeLoinc($row['blood_type'] ?? '');

    $obs = [
        'resourceType'      => 'Observation',
        'id'                => $uuid,
        'meta'              => ['lastUpdated' => fhirInstant($row['created_at'])],
        'status'            => 'final',
        'category'          => [[
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/observation-category',
                'code'    => 'procedure',
                'display' => 'Procedure',
            ]],
        ]],
        'code' => [
            'coding' => [[
                'system'  => 'http://loinc.org',
                'code'    => '882-1',
                'display' => 'ABO and Rh group [Type] in Blood',
            ]],
            'text' => 'Blood donation',
        ],
        'effectiveDateTime' => fhirDate($row['donation_date']),
        'issued'            => fhirInstant($row['created_at']),
        'valueCodeableConcept' => [
            'coding' => [[
                'system'  => 'http://loinc.org',
                'code'    => $loincCode,
                'display' => $loincDisplay,
            ]],
            'text' => $row['blood_type'] ?? '',
        ],
        'component' => [[
            'code' => [
                'coding' => [[
                    'system'  => 'http://loinc.org',
                    'code'    => '33745-7',
                    'display' => 'Units of packed red blood cells transfused',
                ]],
            ],
            'valueQuantity' => [
                'value'  => (int)($row['units'] ?? 1),
                'unit'   => 'unit',
                'system' => 'http://unitsofmeasure.org',
                'code'   => '[arb\'U]',
            ],
        ]],
    ];

    if (!empty($row['donor_fhir_id'])) {
        $obs['subject'] = ['reference' => 'Patient/' . $row['donor_fhir_id']];
    }

    return $obs;
}
