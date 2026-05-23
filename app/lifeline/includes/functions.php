<?php
require_once __DIR__ . '/db.php';

// Rate limiting for login attempts
function isRateLimited(string $identifier): bool {
    $maxAttempts = Config::getInt('MAX_LOGIN_ATTEMPTS', 5);
    $lockoutMinutes = Config::getInt('LOGIN_LOCKOUT_MINUTES', 15);
    
    $key = 'login_attempts_' . md5($identifier);
    $lockoutKey = 'login_lockout_' . md5($identifier);
    
    // Check if currently locked out
    if (isset($_SESSION[$lockoutKey]) && $_SESSION[$lockoutKey] > time()) {
        return true;
    }
    
    // Clear expired lockout
    if (isset($_SESSION[$lockoutKey]) && $_SESSION[$lockoutKey] <= time()) {
        unset($_SESSION[$lockoutKey]);
        unset($_SESSION[$key]);
    }
    
    return false;
}

function recordLoginAttempt(string $identifier): void {
    $maxAttempts = Config::getInt('MAX_LOGIN_ATTEMPTS', 5);
    $lockoutMinutes = Config::getInt('LOGIN_LOCKOUT_MINUTES', 15);
    
    $key = 'login_attempts_' . md5($identifier);
    $lockoutKey = 'login_lockout_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $_SESSION[$key]['count']++;
    $_SESSION[$key]['last_attempt'] = time();
    
    // Lock out after max attempts
    if ($_SESSION[$key]['count'] >= $maxAttempts) {
        $_SESSION[$lockoutKey] = time() + ($lockoutMinutes * 60);
        error_log("Rate limit triggered for: " . $identifier);
    }
}

function clearLoginAttempts(string $identifier): void {
    $key = 'login_attempts_' . md5($identifier);
    $lockoutKey = 'login_lockout_' . md5($identifier);
    unset($_SESSION[$key]);
    unset($_SESSION[$lockoutKey]);
}

function getRateLimitRemaining(string $identifier): array {
    $maxAttempts = Config::getInt('MAX_LOGIN_ATTEMPTS', 5);
    $lockoutMinutes = Config::getInt('LOGIN_LOCKOUT_MINUTES', 15);
    
    $key = 'login_attempts_' . md5($identifier);
    $lockoutKey = 'login_lockout_' . md5($identifier);
    
    if (isset($_SESSION[$lockoutKey]) && $_SESSION[$lockoutKey] > time()) {
        $remaining = $_SESSION[$lockoutKey] - time();
        return [
            'locked' => true,
            'minutes_remaining' => ceil($remaining / 60),
            'attempts_remaining' => 0
        ];
    }
    
    $attempts = $_SESSION[$key]['count'] ?? 0;
    return [
        'locked' => false,
        'minutes_remaining' => 0,
        'attempts_remaining' => max(0, $maxAttempts - $attempts)
    ];
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
    
    $html = '<div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px; flex-wrap: wrap;">';
    
    // Previous button
    if ($currentPage > 1) {
        $prevUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . ($currentPage - 1) . '&per_page=' . $perPage;
        $html .= '<a href="' . $prevUrl . '" class="btn btn-small btn-secondary">&larr; Previous</a>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<span style="padding: 6px 12px;">...</span>';
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i === $currentPage) {
            $html .= '<span class="btn btn-small" style="background: #b91c1c; cursor: default;">' . $i . '</span>';
        } else {
            $pageUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . $i . '&per_page=' . $perPage;
            $html .= '<a href="' . $pageUrl . '" class="btn btn-small btn-secondary">' . $i . '</a>';
        }
    }
    
    if ($endPage < $totalPages) {
        $html .= '<span style="padding: 6px 12px;">...</span>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $nextUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . ($currentPage + 1) . '&per_page=' . $perPage;
        $html .= '<a href="' . $nextUrl . '" class="btn btn-small btn-secondary">Next &rarr;</a>';
    }
    
    $html .= '</div>';
    $html .= '<p style="text-align: center; color: #6b7280; font-size: 0.9rem; margin-top: 10px;">Page ' . $currentPage . ' of ' . $totalPages . '</p>';
    
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
    $coolOffDays = 90;
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
    
    // Default to a simple initial-based avatar (no photos of people)
    $name = urlencode($profile['full_name'] ?? 'User');
    $bg = 'b91c1c'; // Crimson
    $color = 'ffffff';
    
    return "https://ui-avatars.com/api/?name=$name&background=$bg&color=$color&size=200&bold=true";
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
    
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and WebP are allowed.'];
    }
    
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File is too large. Max size is 2MB.'];
    }
    
    $filename = uniqid('profile_', true) . '.' . $ext;
    // Use an absolute path based on the root of the project
    $targetDir = __DIR__ . '/../' . ltrim($dir, '/');
    
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory.'];
        }
    }
    
    $target = $targetDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Failed to save file. Check directory permissions at: ' . $targetDir];
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
function geocodeLocation(string $city, string $state, string $country = 'India'): ?array {
    $query = trim($city . ', ' . $state . ', ' . $country);
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $query,
        'format' => 'json',
        'limit' => 1,
        'countrycodes' => 'in'
    ]);
    
    $opts = [
        'http' => [
            'header' => "User-Agent: LifeLineBloodNetwork/1.0\r\n"
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

// CSV export helper
function exportToCsv(array $headers, array $rows, string $filename): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
