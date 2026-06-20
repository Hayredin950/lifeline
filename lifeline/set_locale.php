<?php
require_once 'includes/functions.php';

$locale   = $_GET['locale']   ?? 'en';
$redirect = $_GET['redirect'] ?? '/';

setAppLocale($locale);

// Ensure redirect stays on the same origin.
$host = parse_url(baseUrl(), PHP_URL_HOST);
$parsed = parse_url($redirect);
if (!empty($parsed['host']) && $parsed['host'] !== $host) {
    $redirect = '/';
}

redirect(baseUrl() . '/' . ltrim($redirect, '/'));
