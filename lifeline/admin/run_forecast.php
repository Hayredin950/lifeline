<?php
/**
 * Admin: trigger forecast worker synchronously.
 * Invoked via POST from the forecasting dashboard.
 */
require_once '../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(baseUrl() . '/admin/forecasting.php');
}
validateCsrf();

$workerPath = realpath(__DIR__ . '/../../worker/compute_forecasts.php');
if (!$workerPath || !file_exists($workerPath)) {
    setFlash('Worker script not found.', 'danger');
    redirect(baseUrl() . '/admin/forecasting.php');
}

$php = PHP_BINARY;
$output = [];
$exit   = 0;
exec(escapeshellcmd($php) . ' ' . escapeshellarg($workerPath) . ' 2>&1', $output, $exit);

if ($exit === 0) {
    setFlash('Forecast worker completed successfully.', 'success');
} else {
    setFlash('Worker exited with code ' . (int)$exit . '. Check logs.', 'danger');
}

redirect(baseUrl() . '/admin/forecasting.php');
