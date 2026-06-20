<?php
/**
 * LifeLine — Multi-country region-cell routing (P4.1 · Doc 12 Tier 4)
 *
 * P3: Ethiopian health-zone sharding (et-central … et-west).
 * P4: Extended to multi-country — each country is a top-level routing domain;
 *     each country's regions are its locality cells. Per-country compliance
 *     flags (GDPR/HIPAA/cooloff) are loaded from the country_config DB table.
 *
 * COUNTRY_ISO2 env var selects the active country (default: ET).
 * REGION_ID env var selects the locality cell within that country.
 *
 * At Tier 0–3 (single DB) nothing changes — both default to ET / et-central.
 * Tier-4 multi-country: set COUNTRY_ISO2 + REGION_DB_HOST_{REGION_ID} per cell.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// ── Country registry (static fallback — authoritative data is in country_config table) ─
const COUNTRY_REGISTRY = [
    'ET' => ['name' => 'Ethiopia',       'locale' => 'am', 'gdpr' => false, 'hipaa' => false, 'cooloff_days' => 56],
    'KE' => ['name' => 'Kenya',          'locale' => 'en', 'gdpr' => false, 'hipaa' => false, 'cooloff_days' => 56],
    'NG' => ['name' => 'Nigeria',        'locale' => 'en', 'gdpr' => false, 'hipaa' => false, 'cooloff_days' => 56],
    'GH' => ['name' => 'Ghana',          'locale' => 'en', 'gdpr' => false, 'hipaa' => false, 'cooloff_days' => 56],
    'TZ' => ['name' => 'Tanzania',       'locale' => 'en', 'gdpr' => false, 'hipaa' => false, 'cooloff_days' => 56],
    'UG' => ['name' => 'Uganda',         'locale' => 'en', 'gdpr' => false, 'hipaa' => false, 'cooloff_days' => 56],
    'SD' => ['name' => 'Sudan',          'locale' => 'en', 'gdpr' => false, 'hipaa' => false, 'cooloff_days' => 56],
    'RW' => ['name' => 'Rwanda',         'locale' => 'en', 'gdpr' => false, 'hipaa' => false, 'cooloff_days' => 56],
    'US' => ['name' => 'United States',  'locale' => 'en', 'gdpr' => false, 'hipaa' => true,  'cooloff_days' => 56],
    'GB' => ['name' => 'United Kingdom', 'locale' => 'en', 'gdpr' => true,  'hipaa' => false, 'cooloff_days' => 84],
    'DE' => ['name' => 'Germany',        'locale' => 'en', 'gdpr' => true,  'hipaa' => false, 'cooloff_days' => 56],
];

/**
 * Return the active country ISO-2 code from env (e.g. "ET", "KE").
 */
function getCountryIso(): string
{
    static $iso = null;
    if ($iso !== null) return $iso;
    $configured = strtoupper(trim(Config::get('COUNTRY_ISO2', 'ET')));
    $iso = isset(COUNTRY_REGISTRY[$configured]) ? $configured : 'ET';
    return $iso;
}

/**
 * Return the per-country compliance config.
 * Tries DB first (country_config table), falls back to COUNTRY_REGISTRY.
 */
function getCountryConfig(): array
{
    global $pdo;
    static $cache = null;
    if ($cache !== null) return $cache;

    $iso = getCountryIso();
    try {
        $stmt = $pdo->prepare("SELECT * FROM country_config WHERE iso2 = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$iso]);
        $row = $stmt->fetch();
        if ($row) {
            $cache = [
                'iso2'          => $row['iso2'],
                'name'          => $row['name'],
                'locale'        => $row['default_locale'],
                'gdpr'          => (bool)$row['gdpr_mode'],
                'hipaa'         => (bool)$row['hipaa_mode'],
                'cooloff_days'  => (int)$row['donation_cooloff_days'],
                'currency'      => $row['currency'],
            ];
            return $cache;
        }
    } catch (\Throwable $e) {
        // DB not ready — use static fallback.
    }

    $cache = COUNTRY_REGISTRY[$iso] ?? COUNTRY_REGISTRY['ET'];
    $cache['iso2']     = $iso;
    $cache['currency'] = 'USD';
    return $cache;
}

/**
 * Convenience: is GDPR-style processing required for this country?
 */
function isGdprCountry(): bool { return getCountryConfig()['gdpr']; }
function isHipaaCountry(): bool { return getCountryConfig()['hipaa']; }
function countryDonationCooloff(): int { return getCountryConfig()['cooloff_days']; }

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
        header('X-Country: ' . getCountryIso());
    }
}
