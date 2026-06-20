<?php
/**
 * Health Check Endpoint for Monitoring
 *
 * DEF-15: two tiers of disclosure.
 *   - Anonymous (default): a shallow liveness/readiness probe safe for a public
 *     load balancer — only {status, timestamp} and a 200/503 code. No version,
 *     no environment, no error strings.
 *   - Authorized (admin session OR ?token=<HEALTH_TOKEN>): the full deep check
 *     with component detail for operators.
 */

require_once 'includes/config.php';
require_once 'includes/region.php';

// Session is file-based and does not require the DB, so we can detect an admin
// operator even when the database is down.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Authorization for the deep view.
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
$healthToken = Config::get('HEALTH_TOKEN', '');
$providedToken = $_GET['token'] ?? '';
$tokenOk = $healthToken !== '' && hash_equals($healthToken, (string)$providedToken);
$deep = $isAdmin || $tokenOk;

// Lightweight DB ping — boolean outcome only at the shallow tier.
$dbOk = false;
$dbError = null;
try {
    $dbConfig = Config::getDatabaseConfig();
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
    $probe = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    $probe->query('SELECT 1');
    $dbOk = true;
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    error_log('health.php DB check failed: ' . $dbError);
}

if (!$dbOk) {
    http_response_code(503);
}

// --- Shallow (anonymous) response ---
if (!$deep) {
    echo json_encode([
        'status'    => $dbOk ? 'healthy' : 'unhealthy',
        'timestamp' => date('c'),
    ], JSON_PRETTY_PRINT);
    exit;
}

// --- Deep (authorized) response ---
$region = getRegion();
echo json_encode([
    'status'    => $dbOk ? 'healthy' : 'unhealthy',
    'timestamp' => date('c'),
    'version'   => '1.0.0',
    'region'    => [
        'id'    => getRegionId(),
        'label' => $region['label'],
    ],
    'checks'    => [
        'database'    => $dbOk ? 'ok' : ('error: ' . $dbError),
        'email'       => Config::isEmailConfigured() ? 'configured' : 'not_configured',
        'environment' => Config::get('APP_ENV', 'unknown'),
        'debug_mode'  => Config::isDebug() ? 'enabled' : 'disabled',
    ],
], JSON_PRETTY_PRINT);
