<?php
/**
 * Run this script once to create the admin user.
 * Usage: php setup_admin.php
 */

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_DATABASE') ?: 'lifeline_db_mysql';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

if ($pass === '' && getenv('DB_PASSWORD') === false) {
    echo "Enter your MySQL password (leave empty if none): ";
    $pass = trim(fgets(STDIN));
}

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

$email    = 'admin@bloodsystem.com';
$password = 'SecureAdmin2024!';
$hash     = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (email, password, role, is_active)
    VALUES (?, ?, 'admin', true)
    ON DUPLICATE KEY UPDATE password = VALUES(password), role = 'admin', is_active = true
");
$stmt->execute([$email, $hash]);

echo "Admin user created/updated.\n";
echo "Email:    $email\n";
echo "Password: $password\n";
