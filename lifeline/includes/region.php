<?php
/**
 * LifeLine — Region-cell app-layer routing (P3 · Doc 12 Tier 4)
 *
 * Supports a locality-sharded deployment where each region cell (e.g. et-central,
 * et-north, et-south) runs its own MySQL stack. The app reads REGION_ID from .env
 * and routes writes to the local DB and reads to regional replicas.
 *
 * At Tier 0–3 (single DB) nothing changes — REGION_ID defaults to "et-central"
 * and getRegionalPdo() returns the global $pdo. The routing layer activates when
 * REGION_DB_HOST_{REGION_ID} is set in .env, enabling zero-code-change shard cutover.
 *
 * Defined regions (Ethiopian health zones):
 *   et-central  — Addis Ababa, Dire Dawa
 *   et-north    — Amhara, Tigray, Afar
 *   et-south    — SNNP, Sidama, Oromia (south)
 *   et-east     — Somali, Harari
 *   et-west     — Oromia (west), Gambella, Benishangul-Gumuz
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// ── Region registry ──────────────────────────────────────────────────────────
const REGION_REGISTRY = [
    'et-central' => [
        'label'  => 'Central (Addis Ababa)',
        'states' => ['Addis Ababa', 'Dire Dawa'],
    ],
    'et-north' => [
        'label'  => 'North (Amhara · Tigray · Afar)',
        'states' => ['Amhara', 'Tigray', 'Afar'],
    ],
    'et-south' => [
        'label'  => 'South (SNNP · Sidama)',
        'states' => ['SNNP', 'Sidama', 'Southern Ethiopia'],
    ],
    'et-east' => [
        'label'  => 'East (Somali · Harari)',
        'states' => ['Somali', 'Harari'],
    ],
    'et-west' => [
        'label'  => 'West (Oromia · Gambella · Benishangul-Gumuz)',
        'states' => ['Oromia', 'Gambella', 'Benishangul-Gumuz'],
    ],
];

/**
 * Return the configured REGION_ID (e.g. "et-central").
 * Validates against the registry; falls back to "et-central" if unknown.
 */
function getRegionId(): string {
    static $regionId = null;
    if ($regionId !== null) {
        return $regionId;
    }
    $configured = Config::get('REGION_ID', 'et-central');
    $regionId   = array_key_exists($configured, REGION_REGISTRY) ? $configured : 'et-central';
    return $regionId;
}

/**
 * Return metadata for the current region cell.
 */
function getRegion(): array {
    return REGION_REGISTRY[getRegionId()];
}

/**
 * Infer the best region for a given state/city string.
 * Used when routing cross-region requests at the app layer.
 */
function inferRegion(string $state, string $city = ''): string {
    $haystack = strtolower($state . ' ' . $city);
    foreach (REGION_REGISTRY as $id => $def) {
        foreach ($def['states'] as $s) {
            if (stripos($haystack, strtolower($s)) !== false) {
                return $id;
            }
        }
    }
    return 'et-central';  // default
}

/**
 * Return a PDO connection for the given region.
 *
 * At Tier 0–3 (REGION_DB_HOST_{id} not set), returns the global primary $pdo.
 * At Tier 4 (region cells deployed), each region has its own DB_HOST env var:
 *   REGION_DB_HOST_ET_CENTRAL=10.0.1.5
 *   REGION_DB_HOST_ET_NORTH=10.0.2.5
 *   …
 */
function getRegionalPdo(string $regionId = ''): PDO {
    global $pdo;
    static $pdoCache = [];

    if ($regionId === '') {
        $regionId = getRegionId();
    }
    if (!array_key_exists($regionId, REGION_REGISTRY)) {
        return $pdo;
    }
    if (isset($pdoCache[$regionId])) {
        return $pdoCache[$regionId];
    }

    $envKey  = 'REGION_DB_HOST_' . strtoupper(str_replace('-', '_', $regionId));
    $regHost = Config::get($envKey, '');
    if ($regHost === '') {
        return $pdo;  // Tier 0–3: single node
    }

    $dbConfig = Config::getDatabaseConfig();
    $regPort  = Config::getInt('REGION_DB_PORT_' . strtoupper(str_replace('-', '_', $regionId)), (int)$dbConfig['port']);
    $dsn = "mysql:host={$regHost};port={$regPort};dbname={$dbConfig['name']};charset=utf8mb4";

    try {
        $pdoCache[$regionId] = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ]);
    } catch (\PDOException $e) {
        error_log("Regional PDO [{$regionId}] failed, falling back to primary: " . $e->getMessage());
        $pdoCache[$regionId] = $pdo;
    }

    return $pdoCache[$regionId];
}

/**
 * Emit the X-Region header on all responses (API + HTML).
 * Called from db.php so every request is tagged — enables load-balancer
 * routing logs and health dashboards to attribute requests by region.
 */
function emitRegionHeader(): void {
    if (!headers_sent()) {
        header('X-Region: ' . getRegionId());
        header('X-Region-Label: ' . getRegion()['label']);
    }
}
