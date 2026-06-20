<?php
/**
 * LifeLine REST API v1 — shared bootstrap (Doc 06 §4)
 *
 * Loaded by every /api/v1/*.php endpoint. Provides:
 *   requireApiKey()  — validates Bearer token, enforces rate-limit + scopes
 *   apiOk()          — send 200 JSON response
 *   apiError()       — send error JSON response and exit
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: 1');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function apiOk(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiError(int $code, string $message): never {
    http_response_code($code);
    echo json_encode(['error' => $message, 'code' => $code], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Validate the Authorization: Bearer <key> header.
 * Returns the api_keys row. Exits with 401/403/429 on failure.
 *
 * @param PDO    $pdo
 * @param array  $scopes  Required scopes. Empty = any authenticated key.
 */
function requireApiKey(PDO $pdo, array $scopes = []): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        apiError(401, 'Authorization: Bearer <key> header required');
    }
    $raw  = $m[1];
    $hash = hash('sha256', $raw);

    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE key_hash = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$hash]);
    $key = $stmt->fetch();
    if (!$key) {
        apiError(401, 'Invalid or revoked API key');
    }

    // Per-key rate limiting — reuses the DB-backed limiter (DEF-12 pattern).
    if (rateLimitHit($pdo, 'apikey:' . substr($hash, 0, 16), (int)$key['rate_limit'], 60)) {
        apiError(429, 'Rate limit exceeded (' . $key['rate_limit'] . ' req/min)');
    }

    // Scope enforcement.
    if (!empty($scopes)) {
        $keyScopes = json_decode($key['scopes'] ?? '[]', true) ?: [];
        $granted   = in_array('*', $keyScopes, true) || !empty(array_intersect($scopes, $keyScopes));
        if (!$granted) {
            apiError(403, 'Required scope(s): ' . implode(', ', $scopes));
        }
    }

    $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?")->execute([$key['id']]);
    return $key;
}

/** Parse JSON body for POST/PATCH/PUT requests. */
function jsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $parsed = json_decode($raw, true);
    return is_array($parsed) ? $parsed : [];
}

/** Return int page param (min 1). */
function pageParam(): int {
    return max(1, (int)($_GET['page'] ?? 1));
}

/** Return int per_page param clamped to [1, $max]. */
function perPageParam(int $max = 100): int {
    return min($max, max(1, (int)($_GET['per_page'] ?? 20)));
}
