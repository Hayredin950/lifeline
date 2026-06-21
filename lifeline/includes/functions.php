<?php
require_once __DIR__ . '/db.php';

// ── i18n helpers ─────────────────────────────────────────────────────────────

/**
 * Translate a dot-notation key, interpolating {placeholder} tokens.
 * Falls back to English, then to the key itself.
 *
 * @param string $key      e.g. 'nav.login'
 * @param array  $replace  e.g. ['name' => 'Alice', 'count' => 3]
 */
function t(string $key, array $replace = []): string
{
    static $strings = null;
    if ($strings === null) {
        $locale  = $_SESSION['locale'] ?? 'en';
        $allowed = ['en', 'am'];
        if (!in_array($locale, $allowed, true)) $locale = 'en';

        $langFile = __DIR__ . '/../lang/' . $locale . '.php';
        $strings  = file_exists($langFile) ? require $langFile : [];

        // Fall-back layer: merge English under locale strings.
        if ($locale !== 'en') {
            $enFile  = __DIR__ . '/../lang/en.php';
            $strings = $strings + (file_exists($enFile) ? require $enFile : []);
        }
    }

    $text = $strings[$key] ?? $key;

    foreach ($replace as $placeholder => $value) {
        $text = str_replace('{' . $placeholder . '}', (string)$value, $text);
    }

    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/** Set the active locale and persist it in the session. */
function setAppLocale(string $locale): void
{
    $allowed = ['en', 'am'];
    $_SESSION['locale'] = in_array($locale, $allowed, true) ? $locale : 'en';
}

/** Returns a language switcher URL for the given locale. */
function localeSwitchUrl(string $locale): string
{
    return baseUrl() . '/set_locale.php?locale=' . urlencode($locale)
        . '&redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/');
}

// Rate limiting for login attempts.
// DB-backed (DEF-12): the legacy implementation tracked attempts in $_SESSION, so an
// attacker who dropped the session cookie reset the counter and bypassed lockout
// entirely. These now persist in the `rate_limits` table keyed by IP|email, so the
// limit holds across requests, processes, and cookie-less clients — matching the SOS
// limiter. Signatures are unchanged, so callers (login.php) need no edits.
function loginRateKey(string $identifier): string {
    return 'login:' . md5($identifier);
}

function isRateLimited(string $identifier): bool {
    global $pdo;
    $maxAttempts = Config::getInt('MAX_LOGIN_ATTEMPTS', 5);
    $window = Config::getInt('LOGIN_LOCKOUT_MINUTES', 15) * 60;
    try {
        $stmt = $pdo->prepare("
            SELECT hits FROM rate_limits
            WHERE rate_key = ? AND window_started_at >= (NOW() - INTERVAL ? SECOND)
        ");
        $stmt->execute([loginRateKey($identifier), $window]);
        $hits = (int)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {
        error_log('isRateLimited failed: ' . $e->getMessage());
        return false; // fail open — never lock a legitimate user out on infra error
    }
    return $hits >= $maxAttempts;
}

function recordLoginAttempt(string $identifier): void {
    global $pdo;
    $maxAttempts = Config::getInt('MAX_LOGIN_ATTEMPTS', 5);
    $window = Config::getInt('LOGIN_LOCKOUT_MINUTES', 15) * 60;
    // Atomic increment within the fixed window (shared helper handles upsert + reset).
    $res = rateLimitHit($pdo, loginRateKey($identifier), $maxAttempts, $window);
    if (!$res['allowed']) {
        error_log("Login rate limit triggered for: " . $identifier);
    }
}

function clearLoginAttempts(string $identifier): void {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE rate_key = ?");
        $stmt->execute([loginRateKey($identifier)]);
    } catch (Exception $e) {
        error_log('clearLoginAttempts failed: ' . $e->getMessage());
    }
}

function getRateLimitRemaining(string $identifier): array {
    global $pdo;
    $maxAttempts = Config::getInt('MAX_LOGIN_ATTEMPTS', 5);
    $window = Config::getInt('LOGIN_LOCKOUT_MINUTES', 15) * 60;
    try {
        $stmt = $pdo->prepare("
            SELECT hits, TIMESTAMPDIFF(SECOND, window_started_at, NOW()) AS elapsed
            FROM rate_limits
            WHERE rate_key = ? AND window_started_at >= (NOW() - INTERVAL ? SECOND)
        ");
        $stmt->execute([loginRateKey($identifier), $window]);
        $r = $stmt->fetch();
    } catch (Exception $e) {
        $r = false;
    }
    $hits = (int)($r['hits'] ?? 0);
    $elapsed = (int)($r['elapsed'] ?? 0);

    if ($hits >= $maxAttempts) {
        return [
            'locked' => true,
            'minutes_remaining' => max(1, (int)ceil(($window - $elapsed) / 60)),
            'attempts_remaining' => 0,
        ];
    }
    return [
        'locked' => false,
        'minutes_remaining' => 0,
        'attempts_remaining' => max(0, $maxAttempts - $hits),
    ];
}

// ── Fragment cache (NFR-01) ──────────────────────────────────────────────────
// Redis when phpredis + REDIS_HOST is configured; file-based fallback otherwise.
// Cache misses always return null — callers regenerate and store.

function _cacheRedis(): ?\Redis {
    static $r = false;
    if ($r === false) {
        $host = Config::get('REDIS_HOST', '');
        if ($host !== '' && extension_loaded('redis')) {
            try {
                $c = new \Redis();
                $c->connect($host, Config::getInt('REDIS_PORT', 6379));
                $pass = Config::get('REDIS_PASSWORD', '');
                if ($pass !== '') $c->auth($pass);
                $r = $c;
            } catch (\Exception $e) {
                $r = null;
            }
        } else {
            $r = null;
        }
    }
    return $r;
}

function cacheGet(string $key): mixed {
    $prefix = 'lifeline:';
    if ($r = _cacheRedis()) {
        $v = $r->get($prefix . $key);
        return $v !== false ? unserialize($v) : null;
    }
    $file = sys_get_temp_dir() . '/ll_cache_' . md5($key);
    if (!file_exists($file)) return null;
    [$exp, $data] = explode('|', file_get_contents($file), 2);
    if ((int)$exp < time()) { @unlink($file); return null; }
    return unserialize($data);
}

function cacheSet(string $key, mixed $value, int $ttl = 120): void {
    $prefix = 'lifeline:';
    if ($r = _cacheRedis()) {
        $r->setEx($prefix . $key, $ttl, serialize($value));
        return;
    }
    $file = sys_get_temp_dir() . '/ll_cache_' . md5($key);
    file_put_contents($file, (time() + $ttl) . '|' . serialize($value), LOCK_EX);
}

function cacheDel(string $key): void {
    $prefix = 'lifeline:';
    if ($r = _cacheRedis()) { $r->del($prefix . $key); return; }
    $file = sys_get_temp_dir() . '/ll_cache_' . md5($key);
    if (file_exists($file)) @unlink($file);
}

// Canonical list of supported blood types — single source of truth.
const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
// DONATION_COOLOFF_DAYS is defined in config.php (loaded ahead of this file) so
// both the app and the email service share one definition — see DEF-01.

function isValidBloodType(string $type): bool {
    return in_array($type, BLOOD_TYPES, true);
}

// Best-effort client IP (used for abuse rate limiting). Honors a single trusted
// proxy header when present; otherwise falls back to REMOTE_ADDR.
function clientIp(): string {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        // First hop is the original client when behind a trusted proxy.
        $first = trim(explode(',', $forwarded)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Durable, DB-backed fixed-window rate limiter (DEF-12).
 * Atomically records a hit for $key and reports whether it is within $max
 * hits per $windowSeconds. Works across requests, processes, and guests
 * (unlike the session-only login limiter) — suitable for Emergency-SOS abuse.
 *
 * @return array{allowed:bool, hits:int, remaining:int, retry_after:int}
 */
function rateLimitHit(PDO $pdo, string $key, int $max, int $windowSeconds): array {
    $key = substr($key, 0, 191);
    try {
        // One atomic upsert: reset the window if it has expired, else increment.
        $stmt = $pdo->prepare("
            INSERT INTO rate_limits (rate_key, hits, window_started_at)
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                hits = IF(window_started_at < (NOW() - INTERVAL ? SECOND), 1, hits + 1),
                window_started_at = IF(window_started_at < (NOW() - INTERVAL ? SECOND), NOW(), window_started_at)
        ");
        $stmt->execute([$key, $windowSeconds, $windowSeconds]);

        $row = $pdo->prepare("
            SELECT hits, TIMESTAMPDIFF(SECOND, window_started_at, NOW()) AS elapsed
            FROM rate_limits WHERE rate_key = ?
        ");
        $row->execute([$key]);
        $r = $row->fetch();
        $hits = (int)($r['hits'] ?? 1);
        $elapsed = (int)($r['elapsed'] ?? 0);
    } catch (Exception $e) {
        // Fail open (availability over strictness) but log — never block a real emergency on infra error.
        error_log('rateLimitHit failed: ' . $e->getMessage());
        return ['allowed' => true, 'hits' => 0, 'remaining' => $max, 'retry_after' => 0];
    }

    $allowed = $hits <= $max;
    return [
        'allowed'     => $allowed,
        'hits'        => $hits,
        'remaining'   => max(0, $max - $hits),
        'retry_after' => $allowed ? 0 : max(1, $windowSeconds - $elapsed),
    ];
}

/**
 * Enqueue an outbound notification for the worker to deliver (DEF-03).
 * Keeps email fan-out off the request thread.
 */
function enqueueNotification(PDO $pdo, string $template, string $recipient, array $payload, string $channel = 'email'): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notification_queue (channel, template, recipient, payload, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        return $stmt->execute([$channel, $template, $recipient, json_encode($payload)]);
    } catch (Exception $e) {
        error_log('enqueueNotification failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Begin a verified email change (DEF-07).
 * Validates the new address, ensures it is not already taken, stores a hashed,
 * time-limited token, and enqueues a confirmation email to the NEW address. The
 * account email is NOT changed until that link is followed (see verify_email.php).
 *
 * @return array{success:bool, error?:string}
 */
function requestEmailChange(PDO $pdo, int $userId, string $newEmail): array {
    $newEmail = sanitizeEmail($newEmail);
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please enter a valid email address.'];
    }

    // Already taken by someone else (or already this account's address)?
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$newEmail]);
    $owner = $check->fetchColumn();
    if ($owner && (int)$owner !== $userId) {
        return ['success' => false, 'error' => 'That email address is already in use by another account.'];
    }
    if ($owner && (int)$owner === $userId) {
        return ['success' => false, 'error' => 'That is already your current email address.'];
    }

    // One active pending request per user — clear any prior ones.
    $pdo->prepare("DELETE FROM email_change_requests WHERE user_id = ?")->execute([$userId]);

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        INSERT INTO email_change_requests (user_id, new_email, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $newEmail, $tokenHash, $expiresAt]);

    $link = fullBaseUrl() . '/verify_email.php?token=' . $rawToken;
    $body = "<p>We received a request to change the email address on your LifeLine account to this address.</p>"
          . "<p>To confirm the change, click the link below (valid for 1 hour):</p>"
          . "<p><a href=\"$link\">Confirm email change</a></p>"
          . "<p>If you did not request this, you can safely ignore this email — your account email will not change.</p>";

    enqueueNotification($pdo, 'email_change', $newEmail, [
        'subject' => 'Confirm your new LifeLine email address',
        'body'    => $body,
    ]);

    return ['success' => true];
}

// Password validation
function validatePassword(string $password): array {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return $errors;
}

// Input sanitization helpers
function sanitizeEmail(string $email): string {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function sanitizeString(string $text): string {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

function validatePhone(string $phone): bool {
    // Basic international phone validation
    return preg_match('/^[\+]?[\d\s\-\(\)]{8,20}$/', $phone) === 1;
}

// Pagination helper
function getPaginationParams(int $defaultPerPage = 25): array {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : $defaultPerPage;
    $offset = ($page - 1) * $perPage;
    
    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => $offset
    ];
}

function renderPagination(int $currentPage, int $totalPages, int $perPage, string $baseUrl): string {
    if ($totalPages <= 1) {
        return '';
    }
    
    // Styling lives in .pagination* component classes (style.css, Doc 08 §3).
    $html = '<div class="pagination">';

    // Previous button
    if ($currentPage > 1) {
        $prevUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . ($currentPage - 1) . '&per_page=' . $perPage;
        $html .= '<a href="' . $prevUrl . '" class="btn btn-small btn-secondary">&larr; Previous</a>';
    }

    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);

    if ($startPage > 1) {
        $html .= '<span class="pagination-ellipsis">...</span>';
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i === $currentPage) {
            $html .= '<span class="btn btn-small pagination-current">' . $i . '</span>';
        } else {
            $pageUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . $i . '&per_page=' . $perPage;
            $html .= '<a href="' . $pageUrl . '" class="btn btn-small btn-secondary">' . $i . '</a>';
        }
    }

    if ($endPage < $totalPages) {
        $html .= '<span class="pagination-ellipsis">...</span>';
    }

    // Next button
    if ($currentPage < $totalPages) {
        $nextUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . ($currentPage + 1) . '&per_page=' . $perPage;
        $html .= '<a href="' . $nextUrl . '" class="btn btn-small btn-secondary">Next &rarr;</a>';
    }

    $html .= '</div>';
    $html .= '<p class="pagination-meta">Page ' . $currentPage . ' of ' . $totalPages . '</p>';
    
    return $html;
}

// Redirect helper
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

// Auth checks
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function isDonor(): bool {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'donor';
}

function isHospital(): bool {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'hospital';
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        setFlash('Please login to continue.', 'danger');
        redirect(baseUrl() . '/login.php');
    }
    // Forced password change (Doc 07): a flagged account is confined to the
    // change-password page (and logout) until it rotates its credential.
    if (!empty($_SESSION['must_change_password'])) {
        $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($current, ['change_password.php', 'logout.php'], true)) {
            redirect(baseUrl() . '/change_password.php');
        }
    }
}

function requireAdmin(): void {
    requireAuth();
    if (!isAdmin()) {
        setFlash('Access denied.', 'danger');
        redirect(baseUrl() . '/index.php');
    }
}

function requireDonor(): void {
    requireAuth();
    if (!isDonor()) {
        setFlash('Access denied.', 'danger');
        redirect(baseUrl() . '/index.php');
    }
}

function requireHospital(): void {
    requireAuth();
    if (!isHospital()) {
        setFlash('Access denied.', 'danger');
        redirect(baseUrl() . '/index.php');
    }
}

// Flash messages
function setFlash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// CSRF protection
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('Invalid CSRF token');
    }
}

// Blood type compatibility: donor types that can donate TO the patient type
function getCompatibleDonorBloodTypes(string $patientType): array {
    $map = [
        'A+'  => ['A+', 'A-', 'O+', 'O-'],
        'A-'  => ['A-', 'O-'],
        'B+'  => ['B+', 'B-', 'O+', 'O-'],
        'B-'  => ['B-', 'O-'],
        'AB+' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'AB-' => ['A-', 'B-', 'AB-', 'O-'],
        'O+'  => ['O+', 'O-'],
        'O-'  => ['O-'],
    ];
    return $map[$patientType] ?? [];
}

// Fetch helpers
function getUserById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getDonorProfile(PDO $pdo, int $user_id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM donor_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getHospitalProfile(PDO $pdo, int $user_id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM hospital_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Base URL helper — returns root-relative path so it works through any proxy.
// e.g. "" (empty string) if at root, or "/lifeline" (no trailing slash)
function baseUrl(): string {
    static $base = null;
    if ($base !== null) return $base;
    
    $appPath = Config::get('APP_PATH', '');
    if ($appPath !== '') {
        $base = '/' . trim($appPath, '/');
        if ($base === '/') $base = '';
    } else {
        // Automatically determine base path
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $phpSelf = $_SERVER['PHP_SELF'] ?? '';
        
        // Use the one that seems most correct for the base
        $path = $scriptName ?: $phpSelf;
        
        // Find the project root relative to the script
        // If script is /donor/dashboard.php, we want the part before /donor/
        $currentDir = dirname($path);
        
        // This is tricky without knowing the project structure.
        // But since we are usually running index.php at root:
        if (strpos($path, '/donor/') !== false) {
            $base = str_replace('/donor', '', $currentDir);
        } elseif (strpos($path, '/hospital/') !== false) {
            $base = str_replace('/hospital', '', $currentDir);
        } elseif (strpos($path, '/admin/') !== false) {
            $base = str_replace('/admin', '', $currentDir);
        } else {
            $base = ($currentDir === '/' || $currentDir === '\\') ? '' : $currentDir;
        }
        
        $base = rtrim($base, '/');
    }
    return $base;
}

/**
 * Get old input value from POST data
 */
function old(string $key, $default = ''): string {
    return htmlspecialchars($_POST[$key] ?? $default);
}

// Eligibility helpers
function isDonorEligible(array $profile): array {
    $coolOffDays = DONATION_COOLOFF_DAYS;
    $lastDonation = $profile['last_donation_date'];
    
    if (!$lastDonation) {
        return ['eligible' => true, 'days_remaining' => 0, 'next_date' => null];
    }
    
    $lastDate = new DateTime($lastDonation);
    $today = new DateTime();
    $diff = $today->diff($lastDate)->days;
    
    if ($diff >= $coolOffDays) {
        return ['eligible' => true, 'days_remaining' => 0, 'next_date' => null];
    }
    
    $nextAvailable = clone $lastDate;
    $nextAvailable->modify("+$coolOffDays days");
    $daysRemaining = $coolOffDays - $diff;
    
    return [
        'eligible' => false,
        'days_remaining' => $daysRemaining,
        'next_date' => $nextAvailable->format('Y-m-d')
    ];
}

function getDonorCurrentStatus(PDO $pdo, int $userId): array {
    // 1. Check eligibility
    $profile = getDonorProfile($pdo, $userId);
    if (!$profile) return ['status' => 'unknown', 'label' => 'Unknown'];
    
    $eligibility = isDonorEligible($profile);
    if (!$eligibility['eligible']) {
        return [
            'status' => 'cool_off',
            'label' => 'In Cool-off Period',
            'available_on' => $eligibility['next_date'],
            'days_remaining' => $eligibility['days_remaining']
        ];
    }
    
    // 2. Check if manually marked as unavailable
    if (!$profile['is_available']) {
        return ['status' => 'unavailable', 'label' => 'Manually Unavailable'];
    }
    
    // 3. Check for active engagements (confirmed matches for open requests)
    $stmt = $pdo->prepare("
        SELECT br.id, hp.hospital_name 
        FROM donor_matches dm
        JOIN blood_requests br ON dm.request_id = br.id
        LEFT JOIN hospital_profiles hp ON br.hospital_id = hp.user_id
        WHERE dm.donor_id = ? AND dm.status = 'confirmed' AND br.status = 'open'
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $engagement = $stmt->fetch();
    
    if ($engagement) {
        return [
            'status' => 'busy',
            'label' => 'Busy with Request #' . $engagement['id'],
            'hospital' => $engagement['hospital_name'] ?? 'Emergency Request'
        ];
    }
    
    return ['status' => 'available', 'label' => 'Available'];
}

/**
 * Check if a given page is the current active page
 */
function isActivePage(string $pageName): bool {
    $currentScript = basename($_SERVER['PHP_SELF']);
    
    // Handle home/root
    if ($pageName === 'home' || $pageName === 'index.php') {
        return $currentScript === 'index.php';
    }
    
    // Handle folder-based scripts (donor/hospital/admin)
    $fullPath = $_SERVER['PHP_SELF'];
    if (strpos($fullPath, '/' . $pageName) !== false) {
        return true;
    }
    
    return $currentScript === $pageName;
}

/**
 * Get profile picture URL or default placeholder
 */
/**
 * Get profile picture URL or default UI avatar
 */
function getProfilePic(array $profile): string {
    $base = baseUrl();
    
    // Check if user has an uploaded pic
    if (!empty($profile['profile_pic'])) {
        $filename = $profile['profile_pic'];
        $path = __DIR__ . '/../uploads/profile_pics/' . $filename;
        if (file_exists($path)) {
            return $base . '/uploads/profile_pics/' . $filename;
        }
    }
    
    // Inline SVG letter avatar — no external network call needed
    $letter = strtoupper(mb_substr($profile['full_name'] ?? 'U', 0, 1, 'UTF-8')) ?: 'U';
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">'
         . '<circle cx="100" cy="100" r="100" fill="#b91c1c"/>'
         . '<text x="100" y="100" dy=".35em" text-anchor="middle" fill="#ffffff"'
         . ' font-family="system-ui,sans-serif" font-size="90" font-weight="700">'
         . htmlspecialchars($letter, ENT_XML1, 'UTF-8')
         . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Handle image upload
 */
function handleImageUpload(array $file, string $dir): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload failed: ';
        switch($file['error']) {
            case UPLOAD_ERR_INI_SIZE: $msg .= 'File exceeds upload_max_filesize in php.ini'; break;
            case UPLOAD_ERR_FORM_SIZE: $msg .= 'File exceeds MAX_FILE_SIZE in HTML form'; break;
            case UPLOAD_ERR_PARTIAL: $msg .= 'File only partially uploaded'; break;
            case UPLOAD_ERR_NO_FILE: $msg .= 'No file uploaded'; break;
            default: $msg .= 'Unknown error (' . $file['error'] . ')';
        }
        return ['success' => false, 'error' => $msg];
    }
    
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File is too large. Max size is 2MB.'];
    }

    // DEF-10: never trust the client-supplied extension or MIME. Detect the real
    // content type from the bytes, confirm it is an image we support, then
    // RE-ENCODE through GD so any embedded payload (PHP polyglot, EXIF script,
    // malformed-chunk exploit) is discarded — only pure pixel data survives.
    if (!function_exists('finfo_open') || !extension_loaded('gd')) {
        return ['success' => false, 'error' => 'Image processing is unavailable on the server.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Map verified MIME → [canonical extension, decode fn, encode fn].
    $supported = [
        'image/jpeg' => ['jpg',  'imagecreatefromjpeg', 'imagejpeg'],
        'image/png'  => ['png',  'imagecreatefrompng',  'imagepng'],
        'image/webp' => ['webp', 'imagecreatefromwebp', 'imagewebp'],
    ];

    if (!isset($supported[$mime])) {
        return ['success' => false, 'error' => 'Invalid image. Only JPG, PNG, and WebP are allowed.'];
    }
    [$ext, $decode, $encode] = $supported[$mime];

    if (!function_exists($decode) || !function_exists($encode)) {
        return ['success' => false, 'error' => 'This image format is not supported by the server.'];
    }

    // getimagesize is a second, independent sanity check on the dimensions.
    $dimensions = @getimagesize($file['tmp_name']);
    if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
        return ['success' => false, 'error' => 'The uploaded file is not a valid image.'];
    }

    $image = @$decode($file['tmp_name']);
    if ($image === false) {
        return ['success' => false, 'error' => 'The image could not be processed.'];
    }

    $filename = uniqid('profile_', true) . '.' . $ext;
    // Use an absolute path based on the root of the project
    $targetDir = __DIR__ . '/../' . ltrim($dir, '/');

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0775, true)) {
            imagedestroy($image);
            return ['success' => false, 'error' => 'Failed to create upload directory.'];
        }
    }

    // Defense in depth: drop a hardening file so the upload dir can never execute
    // PHP even if a payload somehow lands there (Apache). nginx is handled in Doc 09.
    $htaccess = $targetDir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "php_flag engine off\nHeader set X-Content-Type-Options nosniff\n<FilesMatch \"\\.(php|phtml|phar)$\">\n  Require all denied\n</FilesMatch>\n");
    }

    $target = $targetDir . '/' . $filename;

    // Re-encode (this is the sanitizing step). Quality 85 for JPEG/WebP.
    $ok = ($encode === 'imagejpeg')
        ? imagejpeg($image, $target, 85)
        : (($encode === 'imagewebp') ? imagewebp($image, $target, 85) : imagepng($image, $target));
    imagedestroy($image);

    if ($ok) {
        @chmod($target, 0644);
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'error' => 'Failed to save the processed image.'];
}

function getUnreadMessageCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// Full absolute URL — used for email links, redirects that need a host.
function fullBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . $host . baseUrl();
}

/**
 * Return a versioned asset URL for CDN long-cache busting (Doc 12 §3).
 *
 * Appends `?v=<8-char md5 of file mtime>` so CDN/browser caches are
 * invalidated automatically when the file changes. The CDN can be configured
 * to cache assets for 1 year (Cache-Control: max-age=31536000, immutable)
 * because the query-string change acts as a cache key break.
 *
 * In CLI (worker) context there's no HTTP request, so this falls back to
 * the plain baseUrl path.
 */
function assetUrl(string $path): string {
    $base = baseUrl();
    if (PHP_SAPI === 'cli') {
        return $base . '/' . ltrim($path, '/');
    }
    $absPath = __DIR__ . '/../' . ltrim($path, '/');
    $version = file_exists($absPath) ? substr(md5((string)filemtime($absPath)), 0, 8) : '0';
    return $base . '/' . ltrim($path, '/') . '?v=' . $version;
}


// Reverse map: which patient blood types can this donor donate to?
function getPatientBloodTypesForDonor(string $donorType): array {
    $map = [
        'A+'  => ['A+', 'AB+'],
        'A-'  => ['A+', 'A-', 'AB+', 'AB-'],
        'B+'  => ['B+', 'AB+'],
        'B-'  => ['B+', 'B-', 'AB+', 'AB-'],
        'AB+' => ['AB+'],
        'AB-' => ['AB+', 'AB-'],
        'O+'  => ['A+', 'B+', 'AB+', 'O+'],
        'O-'  => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
    ];
    return $map[$donorType] ?? [];
}

// Geolocation: Convert city/state to lat/lng using Nominatim (OpenStreetMap)
function geocodeLocation(string $city, string $state, string $country = 'Ethiopia'): ?array {
    $query = trim($city . ', ' . $state . ', ' . $country);
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $query,
        'format' => 'json',
        'limit' => 1,
        'countrycodes' => 'et'
    ]);
    
    $opts = [
        'http' => [
            'header'  => "User-Agent: LifeLineBloodNetwork/1.0\r\n",
            // Bound the blocking call so a slow/unreachable Nominatim never hangs a form POST.
            // Geocoding is best-effort here; the async worker (task 1.2) is the scale path.
            'timeout' => 5,
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
        return [
            'latitude' => (float)$data[0]['lat'],
            'longitude' => (float)$data[0]['lon']
        ];
    }

    return null;
}

/**
 * Geocode a location only when its text actually changed (DEF-09 / FR-13).
 *
 * Returns ['latitude'=>float,'longitude'=>float] to persist, or null to leave the
 * stored coords untouched. Callers fold the returned pair into their INSERT/UPDATE;
 * a null means "no change / lookup failed" and the save proceeds regardless — geocoding
 * is best-effort and must never block a profile save.
 *
 * @param array $new ['city','state','country'] from the submitted form
 * @param array $old previous profile row (may be empty on insert)
 */
function geocodeIfChanged(array $new, array $old = []): ?array {
    $city    = trim($new['city'] ?? '');
    $state   = trim($new['state'] ?? '');
    $country = trim($new['country'] ?? 'Ethiopia');

    if ($city === '' && $state === '') {
        return null; // nothing to geocode
    }

    // Skip the network call when the location text is unchanged AND we already have coords.
    $unchanged = isset($old['city'], $old['state'])
        && strcasecmp($city, (string)$old['city']) === 0
        && strcasecmp($state, (string)$old['state']) === 0
        && strcasecmp($country, (string)($old['country'] ?? 'Ethiopia')) === 0;
    $haveCoords = isset($old['latitude'], $old['longitude'])
        && $old['latitude'] !== null && $old['longitude'] !== null;
    if ($unchanged && $haveCoords) {
        return null;
    }

    return geocodeLocation($city, $state, $country);
}

// Calculate distance between two points using Haversine formula (km)
function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

/**
 * Compute a smoothed reliability score for a donor (FR-20).
 *
 * Uses Laplace smoothing so a donor with no history starts at 0.5 rather than
 * 0 or 1, and the score converges toward the true rate as history accumulates.
 *
 *   score = (donated + 1) / (donated + declined + 2)   ∈ (0, 1)
 *
 * Also returns total_donations and last_donation_date for composite ranking.
 *
 * @return array{score:float, donated:int, declined:int, total_donations:int}
 */
function getDonorReliability(PDO $pdo, int $donorId): array {
    try {
        $stmt = $pdo->prepare("
            SELECT
                IFNULL(SUM(status = 'donated'), 0)              AS donated,
                IFNULL(SUM(status IN ('declined','cancelled')), 0) AS declined
            FROM donor_matches
            WHERE donor_id = ?
        ");
        $stmt->execute([$donorId]);
        $r = $stmt->fetch();
        $donated  = (int)($r['donated']  ?? 0);
        $declined = (int)($r['declined'] ?? 0);
    } catch (Exception $e) {
        $donated = $declined = 0;
    }
    return [
        'score'   => ($donated + 1) / ($donated + $declined + 2),
        'donated' => $donated,
        'declined'=> $declined,
    ];
}

// Audit logging
function auditLog(PDO $pdo, string $action, string $entityType, ?int $entityId = null, ?array $oldValues = null, ?array $newValues = null): void {
    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $ipAddress,
            substr($userAgent, 0, 500)
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Neutralize CSV formula injection (DEF-14).
 * A cell beginning with = + - @ (or tab/CR) can be interpreted as a formula by
 * Excel/Sheets/LibreOffice, enabling data exfiltration or command execution on
 * the analyst's machine. We prefix such cells with a single quote, which forces
 * the spreadsheet to treat the value as literal text.
 */
function sanitizeCsvCell($value): string {
    $value = (string)$value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}

// CSV export helper
function exportToCsv(array $headers, array $rows, string $filename): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, array_map('sanitizeCsvCell', $headers));
    foreach ($rows as $row) {
        fputcsv($output, array_map('sanitizeCsvCell', $row));
    }

    fclose($output);
    exit;
}

/**
 * Award donation-milestone achievements (FR-41).
 *
 * Call this inside a transaction immediately after incrementing
 * donor_profiles.total_donations and updating the tier.
 *
 * The achievements table has a UNIQUE KEY on (donor_id, type), so
 * INSERT IGNORE is safe for re-runs or concurrent requests.
 */
function checkAndAwardMilestones(PDO $pdo, int $donorId, int $totalDonations): void {
    static $milestones = [
        1  => ['type' => 'first_donation',    'title' => 'First Drop',         'desc' => 'Completed your first donation!'],
        5  => ['type' => 'fifth_donation',    'title' => 'Rising Hero',         'desc' => 'Donated 5 times — you\'re a rising hero!'],
        10 => ['type' => 'tenth_donation',    'title' => 'Lifesaver',           'desc' => 'Donated 10 times — a true lifesaver!'],
        20 => ['type' => 'twentieth_donation','title' => 'Platinum Guardian',   'desc' => 'Donated 20 times — Platinum Guardian status!'],
    ];

    if (!isset($milestones[$totalDonations])) {
        return;
    }

    $m = $milestones[$totalDonations];
    $insAch = $pdo->prepare("
        INSERT IGNORE INTO achievements (donor_id, type, title, description)
        VALUES (?, ?, ?, ?)
    ");
    $insAch->execute([$donorId, $m['type'], $m['title'], $m['desc']]);

    if ($insAch->rowCount() > 0) {
        $insNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link)
            VALUES (?, 'achievement', ?, ?, '/donor/dashboard.php')
        ");
        $insNotif->execute([$donorId, 'Achievement Unlocked: ' . $m['title'], $m['desc']]);
    }
}
