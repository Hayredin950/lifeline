<?php
/**
 * LifeLine Blood Network - Configuration Management
 * Loads environment variables from .env file
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Single source of truth for the whole-blood donation cool-off period (DEF-01).
// Aligned to India NBTC guidance of ~3 months between whole-blood donations.
// Defined here (the common ancestor of functions.php and email_service.php) so
// every consumer sees the same number and it can never drift apart again.
if (!defined('DONATION_COOLOFF_DAYS')) {
    define('DONATION_COOLOFF_DAYS', 90);
}

class Config {
    private static array $variables = [];
    private static bool $loaded = false;

    /**
     * Load environment variables from .env file
     */
    public static function load(string $envPath = __DIR__ . '/../.env'): void {
        if (self::$loaded) {
            return;
        }

        if (!file_exists($envPath)) {
            self::$loaded = true;
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse KEY=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }
                
                self::$variables[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get configuration value
     */
    public static function get(string $key, string $default = ''): string {
        if (!self::$loaded) {
            self::load();
        }

        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        $envValue = getenv($key);
        if ($envValue !== false) {
            return (string) $envValue;
        }

        if (isset($_ENV[$key])) {
            return (string) $_ENV[$key];
        }

        return $default;
    }

    /**
     * Get configuration value as integer
     */
    public static function getInt(string $key, int $default = 0): int {
        return (int) self::get($key, (string) $default);
    }

    /**
     * Get configuration value as boolean
     */
    public static function getBool(string $key, bool $default = false): bool {
        $value = strtolower(self::get($key, $default ? 'true' : 'false'));
        return in_array($value, ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Check if application is in debug mode
     */
    public static function isDebug(): bool {
        return self::getBool('APP_DEBUG', false);
    }

    /**
     * Check if application is in production
     */
    public static function isProduction(): bool {
        return self::get('APP_ENV', 'production') === 'production';
    }

    /**
     * Get database configuration
     */
    public static function getDatabaseConfig(): array {
        return [
            'connection' => self::get('DB_CONNECTION', 'mysql'),
            'host'       => self::get('DB_HOST',       'localhost'),
            'name'       => self::get('DB_DATABASE',   'lifeline_db_mysql'),
            'user'       => self::get('DB_USERNAME',   'root'),
            'pass'       => self::get('DB_PASSWORD',   ''),
            'port'       => self::get('DB_PORT',       '3306'),
        ];
    }

    /**
     * Get mail configuration
     */
    public static function getMailConfig(): array {
        return [
            'host' => self::get('MAIL_HOST', 'localhost'),
            'port' => self::getInt('MAIL_PORT', 587),
            'username' => self::get('MAIL_USERNAME'),
            'password' => self::get('MAIL_PASSWORD'),
            'from_address' => self::get('MAIL_FROM_ADDRESS', 'noreply@lifelineblood.network'),
            'from_name' => self::get('MAIL_FROM_NAME', 'LifeLine Blood Network'),
            'encryption' => self::get('MAIL_ENCRYPTION', 'tls'),
        ];
    }

    /**
     * Check if email is configured
     */
    public static function isEmailConfigured(): bool {
        $config = self::getMailConfig();
        return !empty($config['host']) && !empty($config['username']) && !empty($config['password']);
    }
}

// Load configuration on first use
Config::load();
