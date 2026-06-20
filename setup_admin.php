<?php
/**
 * Run this script once to create (or reset) the admin user.
 * Usage: php setup_admin.php
 *
 * Security (Doc 07): this no longer ships a hard-coded password. It generates a
 * strong random one-time password, prints it ONCE, and flags the account so the
 * admin is forced to choose a new password on first login. Re-running rotates the
 * one-time password and re-arms the forced change.
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

$email = getenv('ADMIN_EMAIL') ?: 'admin@bloodsystem.com';

// Generate a strong, URL-safe one-time password (>= 16 chars, mixed classes).
function generateOneTimePassword(): string {
    $sets = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnpqrstuvwxyz',
        '23456789',
        '!@#$%^&*-_=+',
    ];
    $pwd = '';
    foreach ($sets as $s) {                       // guarantee one of each class
        $pwd .= $s[random_int(0, strlen($s) - 1)];
    }
    $all = implode('', $sets);
    for ($i = 0; $i < 12; $i++) {
        $pwd .= $all[random_int(0, strlen($all) - 1)];
    }
    return str_shuffle($pwd);
}

$password = generateOneTimePassword();
$hash     = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (email, password, role, is_active, must_change_password)
    VALUES (?, ?, 'admin', 1, 1)
    ON DUPLICATE KEY UPDATE
        password = VALUES(password),
        role = 'admin',
        is_active = 1,
        must_change_password = 1
");
$stmt->execute([$email, $hash]);

echo "Admin user created/updated.\n";
echo "Email:               $email\n";
echo "One-time password:   $password\n";
echo "\n";
echo "IMPORTANT: log in with this password now. You will be required to set a new\n";
echo "password immediately on first login. This one-time password is shown only here.\n";
