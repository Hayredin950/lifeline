<?php
/**
 * Minimal RFC 6238 TOTP implementation (no dependencies).
 *
 * Usage:
 *   $secret = Totp::generateSecret();   // random Base32 secret
 *   $uri    = Totp::otpAuthUri($secret, $label);
 *   $ok     = Totp::verify($secret, $_POST['code']);
 */
class Totp
{
    private const DIGITS     = 6;
    private const PERIOD     = 30;      // seconds
    private const WINDOW     = 1;       // ±1 period tolerance
    private const ALPHABET   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const SECRET_LEN = 20;      // bytes → 32-char Base32

    // ── Secret generation ──────────────────────────────────────────────────

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_LEN));
    }

    // ── OTP URI for QR codes ───────────────────────────────────────────────

    public static function otpAuthUri(string $secret, string $label, string $issuer = 'LifeLine'): string
    {
        return 'otpauth://totp/'
            . rawurlencode($issuer . ':' . $label)
            . '?secret='  . $secret
            . '&issuer='  . rawurlencode($issuer)
            . '&digits='  . self::DIGITS
            . '&period='  . self::PERIOD
            . '&algorithm=SHA1';
    }

    // ── Verification ───────────────────────────────────────────────────────

    public static function verify(string $secret, string $userCode): bool
    {
        $userCode = preg_replace('/\s+/', '', $userCode);
        if (strlen($userCode) !== self::DIGITS || !ctype_digit($userCode)) {
            return false;
        }
        $key       = self::base32Decode($secret);
        $timestamp = (int)(time() / self::PERIOD);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals(self::hotp($key, $timestamp + $i), $userCode)) {
                return true;
            }
        }
        return false;
    }

    // ── Backup codes ───────────────────────────────────────────────────────

    /** Returns 8 plain-text codes; caller stores hashed versions. */
    public static function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));  // 8 hex chars
        }
        return $codes;
    }

    public static function hashBackupCode(string $code): string
    {
        return hash('sha256', strtoupper(trim($code)));
    }

    public static function verifyBackupCode(string $code, array $hashes): bool
    {
        $h = self::hashBackupCode($code);
        foreach ($hashes as $stored) {
            if (hash_equals($stored, $h)) return true;
        }
        return false;
    }

    // ── Internal ───────────────────────────────────────────────────────────

    private static function hotp(string $key, int $counter): string
    {
        $msg  = pack('J', $counter);    // big-endian uint64
        $hash = hash_hmac('sha1', $msg, $key, true);
        $off  = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$off])     & 0x7F) << 24) |
            ((ord($hash[$off + 1]) & 0xFF) << 16) |
            ((ord($hash[$off + 2]) & 0xFF) <<  8) |
            ((ord($hash[$off + 3]) & 0xFF))
        ) % (10 ** self::DIGITS);
        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $out    = '';
        $buf    = 0;
        $bufLen = 0;
        foreach (str_split($data) as $char) {
            $buf    = ($buf << 8) | ord($char);
            $bufLen += 8;
            while ($bufLen >= 5) {
                $bufLen -= 5;
                $out    .= self::ALPHABET[($buf >> $bufLen) & 0x1F];
            }
        }
        if ($bufLen > 0) {
            $out .= self::ALPHABET[($buf << (5 - $bufLen)) & 0x1F];
        }
        return $out;
    }

    private static function base32Decode(string $data): string
    {
        $data   = strtoupper(preg_replace('/[^A-Z2-7]/', '', $data));
        $out    = '';
        $buf    = 0;
        $bufLen = 0;
        foreach (str_split($data) as $char) {
            $pos    = strpos(self::ALPHABET, $char);
            if ($pos === false) continue;
            $buf    = ($buf << 5) | $pos;
            $bufLen += 5;
            if ($bufLen >= 8) {
                $bufLen -= 8;
                $out    .= chr(($buf >> $bufLen) & 0xFF);
            }
        }
        return $out;
    }
}
