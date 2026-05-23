<?php
/**
 * Health Check Endpoint for Monitoring
 * Returns JSON status for uptime monitoring tools
 */

require_once 'includes/config.php';

header('Content-Type: application/json');

$status = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'checks' => []
];

// Database check
try {
    $dbConfig = Config::getDatabaseConfig();
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3
    ]);
    $pdo->query('SELECT 1');
    $status['checks']['database'] = 'ok';
} catch (Exception $e) {
    $status['status'] = 'unhealthy';
    $status['checks']['database'] = 'error: ' . $e->getMessage();
    http_response_code(503);
}

// Email configuration check
if (Config::isEmailConfigured()) {
    $status['checks']['email'] = 'configured';
} else {
    $status['checks']['email'] = 'not_configured';
}

// Environment check
$status['checks']['environment'] = Config::get('APP_ENV', 'unknown');
$status['checks']['debug_mode'] = Config::isDebug() ? 'enabled' : 'disabled';

echo json_encode($status, JSON_PRETTY_PRINT);
