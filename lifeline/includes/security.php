<?php
/**
 * LifeLine — Security headers (P3 · OWASP A05, NFR-07)
 *
 * Called once from db.php before session_start() so every HTML page and API
 * response carries the full defensive header set. Each header is conditional
 * or environment-aware where the spec requires it.
 *
 * OWASP Top-10 surface addressed here:
 *   A03 Injection      — CSP blocks inline eval/script injection
 *   A05 Security Misc  — all defensive headers enforced
 *   A06 Outdated Comp  — X-Powered-By stripped
 *   A07 Auth failures  — HSTS forces HTTPS on prod
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function sendSecurityHeaders(): void {
    // Abort if headers already sent (shouldn't happen — but be safe).
    if (headers_sent()) {
        return;
    }

    // ── Identity leakage ────────────────────────────────────────────────────
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    // ── Referrer ────────────────────────────────────────────────────────────
    // Send origin-only on cross-origin requests; full URL within same origin.
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // ── Permissions / Feature-Policy ────────────────────────────────────────
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');

    // ── HSTS (production HTTPS only) ─────────────────────────────────────────
    // Only meaningful on HTTPS — sending on HTTP breaks things.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if ($isHttps && defined('APP_ROOT') && class_exists('Config') && Config::isProduction()) {
        // 1-year max-age; includeSubDomains; preload-ready.
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    // ── Content Security Policy ──────────────────────────────────────────────
    // Baseline CSP — covers the self-hosted jQuery + CSS stack.
    // 'unsafe-inline' is currently required for script/style because the app
    // uses inline event handlers and <style> blocks. Phase 4 work: migrate to
    // nonces (generate once per request, stamp on every <script>/<style> tag).
    //
    // Known safe external resources:
    //   • Nominatim geocoder is server-side only (PHP curl) — no client connect.
    //   • No CDN resources — all assets are self-hosted.
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "font-src 'self'",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
        "upgrade-insecure-requests",
    ]);
    header("Content-Security-Policy: {$csp}");

    // ── Cross-Origin policies ────────────────────────────────────────────────
    // Prevent Spectre-style timing attacks via cross-origin data reads.
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-site');
}
