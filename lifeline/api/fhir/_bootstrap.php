<?php
/**
 * LifeLine FHIR R4 API — shared bootstrap (P3 · Doc 06 §FHIR)
 *
 * FHIR R4 base URL: /api/fhir/{ResourceType}[/{id}]
 *
 * Auth: same Bearer-token system as /api/v1 — key must carry scope "fhir" or "*".
 * Content-Type: application/fhir+json  (FHIR §2.1.0.6)
 *
 * Provided helpers:
 *   requireFhirKey()     — authenticate + scope check
 *   fhirOk()             — send FHIR resource with 200
 *   fhirCreated()        — send FHIR resource with 201 + Location header
 *   fhirError()          — FHIR OperationOutcome error
 *   fhirUuid()           — generate or lazily assign a UUID fhir_id
 *   fhirInstant()        — format a MySQL TIMESTAMP as FHIR instant
 *   fhirDate()           — format a MySQL DATE as FHIR date
 *   bloodTypeLoinc()     — map internal blood_type to LOINC code
 */

require_once __DIR__ . '/../../includes/functions.php';

define('FHIR_BASE_URL', rtrim(baseUrl(), '/') . '/api/fhir');
define('FHIR_VERSION', 'R4');

// FHIR always speaks application/fhir+json.
header('Content-Type: application/fhir+json; charset=utf-8');
header('X-FHIR-Version: ' . FHIR_VERSION);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    http_response_code(204);
    exit;
}

header('Access-Control-Allow-Origin: *');

// ── FHIR OperationOutcome error (exit) ──────────────────────────────────────
function fhirError(int $code, string $severity, string $code_text, string $detail): never {
    http_response_code($code);
    echo json_encode([
        'resourceType' => 'OperationOutcome',
        'issue' => [[
            'severity'   => $severity,
            'code'       => $code_text,
            'details'    => ['text' => $detail],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// ── Send a FHIR resource ─────────────────────────────────────────────────────
function fhirOk(array $resource): never {
    http_response_code(200);
    echo json_encode($resource, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function fhirCreated(array $resource, string $resourceType, string $id): never {
    http_response_code(201);
    header('Location: ' . FHIR_BASE_URL . '/' . $resourceType . '/' . $id);
    echo json_encode($resource, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// ── Bearer-auth reusing the /api/v1 key store ────────────────────────────────
function requireFhirKey(PDO $pdo): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        fhirError(401, 'fatal', 'login', 'Authorization: Bearer <key> header required');
    }
    $hash = hash('sha256', $m[1]);
    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE key_hash = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$hash]);
    $key = $stmt->fetch();
    if (!$key) {
        fhirError(401, 'fatal', 'login', 'Invalid or revoked API key');
    }
    if (rateLimitHit($pdo, 'fhir:' . substr($hash, 0, 16), (int)$key['rate_limit'], 60)) {
        fhirError(429, 'fatal', 'throttled', 'Rate limit exceeded (' . $key['rate_limit'] . ' req/min)');
    }
    $keyScopes = json_decode($key['scopes'] ?? '[]', true) ?: [];
    if (!in_array('*', $keyScopes, true) && !in_array('fhir', $keyScopes, true)) {
        fhirError(403, 'fatal', 'forbidden', 'Scope "fhir" required');
    }
    $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?")->execute([$key['id']]);
    return $key;
}

// ── UUID helpers ─────────────────────────────────────────────────────────────
function generateUuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Return (and lazily assign) the FHIR UUID for a table row.
 * If fhir_id is NULL, generates a UUID and persists it.
 */
function fhirUuid(PDO $pdo, string $table, int $rowId, ?string $existingFhirId): string {
    if ($existingFhirId !== null && $existingFhirId !== '') {
        return $existingFhirId;
    }
    $uuid = generateUuid();
    $pdo->prepare("UPDATE `{$table}` SET fhir_id = ? WHERE id = ?")->execute([$uuid, $rowId]);
    return $uuid;
}

// ── Timestamp helpers ─────────────────────────────────────────────────────────
function fhirInstant(?string $ts): ?string {
    return $ts ? gmdate('Y-m-d\TH:i:s\Z', strtotime($ts)) : null;
}

function fhirDate(?string $d): ?string {
    return $d ? date('Y-m-d', strtotime($d)) : null;
}

// ── LOINC blood-group mapping ─────────────────────────────────────────────────
function bloodTypeLoinc(string $type): array {
    $map = [
        'A+'  => ['882-1', 'Blood group A Rh(D) positive'],
        'A-'  => ['883-9', 'Blood group A Rh(D) negative'],
        'B+'  => ['884-7', 'Blood group B Rh(D) positive'],
        'B-'  => ['885-4', 'Blood group B Rh(D) negative'],
        'AB+' => ['886-2', 'Blood group AB Rh(D) positive'],
        'AB-' => ['887-0', 'Blood group AB Rh(D) negative'],
        'O+'  => ['888-8', 'Blood group O Rh(D) positive'],
        'O-'  => ['889-6', 'Blood group O Rh(D) negative'],
    ];
    return $map[$type] ?? ['LA11884-6', $type];
}

// ── JSON request body ─────────────────────────────────────────────────────────
function fhirBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $parsed = json_decode($raw, true);
    return is_array($parsed) ? $parsed : [];
}

// ── Route: parse /api/fhir/{ResourceType}[/{id}] ─────────────────────────────
function fhirRoute(): array {
    $path = $_SERVER['PATH_INFO']
         ?? explode('?', $_SERVER['REQUEST_URI'])[0];
    // Strip prefix up to /api/fhir/
    $path = preg_replace('#^.*?/api/fhir/#', '', $path);
    $parts = array_filter(explode('/', trim($path, '/')));
    $parts = array_values($parts);
    return [
        'resourceType' => $parts[0] ?? '',
        'id'           => $parts[1] ?? null,
        'operation'    => $parts[2] ?? null,
    ];
}
