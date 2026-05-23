<?php
/**
 * LifeLine Blood Network - Database Connection
 * Uses environment-based configuration
 */

require_once __DIR__ . '/config.php';

// Get database configuration from environment
$dbConfig = Config::getDatabaseConfig();

$connection = $dbConfig['connection'] ?? 'mysql';
$dsn = "{$connection}:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";

// For MySQL, we might want to specify the charset
if ($connection === 'mysql') {
    $dsn .= ";charset=utf8mb4";
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => true, // Connection pooling
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

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    $sessionLifetime = Config::getInt('SESSION_LIFETIME', 1440) * 60;
    ini_set('session.gc_maxlifetime', $sessionLifetime);
    ini_set('session.cookie_lifetime', $sessionLifetime);
    
    // Secure cookie settings for production
    if (Config::isProduction()) {
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
    }
    
    session_start();
}
