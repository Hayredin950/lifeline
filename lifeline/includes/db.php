<?php
/**
 * LifeLine Blood Network - Database Connection
 * Uses environment-based configuration
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/region.php';

// Security + region headers sent before any output or session_start.
// Skipped in CLI (worker) context where headers are meaningless.
if (PHP_SAPI !== 'cli') {
    sendSecurityHeaders();
    emitRegionHeader();
}

// Get database configuration from environment
$dbConfig = Config::getDatabaseConfig();

$connection = $dbConfig['connection'] ?? 'mysql';
$dsn = "{$connection}:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";

// For MySQL, we might want to specify the charset
if ($connection === 'mysql') {
    $dsn .= ";charset=utf8mb4";
}

// DEF-16: do NOT use PDO persistent connections. With php-fpm/Apache prefork each
// worker pins a backend connection for its lifetime, so persistence multiplies
// idle connections (workers × app nodes) and exhausts MySQL's max_connections long
// before real load demands it — and it leaks session state (locks, temp tables,
// user vars) across unrelated requests. Real pooling belongs in a dedicated pooler
// (ProxySQL) in front of MySQL; the app opens a fresh, short-lived connection per
// request. See docs/12 Tier-3 and docs/04.
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
];

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
} catch (\PDOException $e) {
    if (Config::isDebug()) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        error_log("Database connection failed: " . $e->getMessage());
        die("Service temporarily unavailable. Please try again later.");
    }
}

// Session configuration (NFR-03/04)
if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = Config::getInt('SESSION_LIFETIME', 1440) * 60;
    ini_set('session.gc_maxlifetime', $sessionLifetime);
    ini_set('session.cookie_lifetime', $sessionLifetime);

    // Externalize sessions to Redis when phpredis is available + REDIS_HOST is set.
    // This makes every app node stateless so N nodes can run behind a load balancer
    // without sticky sessions. When Redis is absent the file handler is used — fine
    // for single-node Tier 0 / dev. Install phpredis and uncomment REDIS_HOST in .env
    // to activate.
    $redisHost = Config::get('REDIS_HOST', '');
    if ($redisHost !== '' && extension_loaded('redis')) {
        $redisPort   = Config::getInt('REDIS_PORT', 6379);
        $redisPass   = Config::get('REDIS_PASSWORD', '');
        $redisSavePath = 'tcp://' . $redisHost . ':' . $redisPort
            . '?lifetime=' . $sessionLifetime
            . '&prefix=' . urlencode(Config::get('REDIS_SESSION_PREFIX', 'lifeline_sess:'))
            . ($redisPass !== '' ? '&auth=' . urlencode($redisPass) : '');
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', $redisSavePath);
    }

    // Secure cookie settings for production
    if (Config::isProduction()) {
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
    }

    session_start();
}

/**
 * Return a PDO connection to the read replica (Doc 12 Tier 3).
 *
 * When DB_READ_HOST is configured (e.g. ProxySQL read port or a MySQL replica),
 * heavy SELECT-only paths call getReadPdo() instead of using $pdo so writes
 * always hit the primary while reads scale across replicas.
 *
 * Falls back to the primary $pdo when the read replica is not configured —
 * safe for Tier 0 / single-node dev with no config change required.
 */
function getReadPdo(): PDO {
    global $pdo;
    static $pdoRead = null;

    if ($pdoRead !== null) {
        return $pdoRead;
    }

    $readHost = Config::get('DB_READ_HOST', '');
    if ($readHost === '') {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ];

    $readPort = Config::getInt('DB_READ_PORT', 3306);
    $readName = Config::get('DB_READ_DATABASE', Config::get('DB_DATABASE', 'lifeline_db_mysql'));
    $readUser = Config::get('DB_READ_USERNAME', Config::get('DB_USERNAME', 'root'));
    $readPass = Config::get('DB_READ_PASSWORD', Config::get('DB_PASSWORD', ''));
    $dsn = "mysql:host={$readHost};port={$readPort};dbname={$readName};charset=utf8mb4";

    try {
        $pdoRead = new PDO($dsn, $readUser, $readPass, $options);
    } catch (\PDOException $e) {
        error_log("Read replica connection failed, falling back to primary: " . $e->getMessage());
        $pdoRead = $pdo;
    }

    return $pdoRead;
}
