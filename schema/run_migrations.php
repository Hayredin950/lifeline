#!/usr/bin/env php
<?php
/**
 * LifeLine — Forward-only migration runner.
 * Applies every schema/*.sql file that has not yet been recorded in schema_migrations.
 * Usage: php schema/run_migrations.php [--dry-run]
 *
 * Fulfills: "formal runner script still TODO" from 0.1 checklist note.
 */

$isDryRun = in_array('--dry-run', $argv ?? [], true);

require_once __DIR__ . '/../lifeline/includes/config.php';

$cfg = Config::getDatabaseConfig();
$dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "[migrate] DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// Ensure ledger exists.
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Load applied versions.
$applied = $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

// Discover migration files in order.
$files = glob(__DIR__ . '/*.sql');
sort($files);

$pending = 0;
$done    = 0;

foreach ($files as $file) {
    $basename = basename($file, '.sql');
    if (isset($applied[$basename])) {
        echo "[migrate] SKIP  $basename (already applied)\n";
        continue;
    }

    $pending++;
    if ($isDryRun) {
        echo "[migrate] WOULD APPLY  $basename\n";
        continue;
    }

    echo "[migrate] APPLY $basename ...\n";
    $sql = file_get_contents($file);

    // Execute statement by statement — PDO::exec rejects multi-statement strings.
    // Strip single-line SQL comments before splitting so comment lines that contain
    // semicolons (or happen to be the only content in a segment) are not mis-executed.
    $sqlStripped = preg_replace('/^\s*--[^\n]*/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sqlStripped))) as $stmt) {
        $stmt = trim(preg_replace('/^\s*--[^\n]*/m', '', $stmt));
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // Tolerate "object already exists" errors so re-running is safe.
            // $e->getCode() is the SQLSTATE; $e->errorInfo[1] is the MySQL native error number.
            $mysqlCode = (int)($e->errorInfo[1] ?? 0);
            $toleratedCodes = [1050, 1060, 1061, 1062, 1091]; // already exists / duplicate
            if (in_array($mysqlCode, $toleratedCodes, true)) {
                echo "[migrate] NOTICE $basename: " . $e->getMessage() . "\n";
                continue;
            }
            fwrite(STDERR, "[migrate] ERROR in $basename: " . $e->getMessage() . "\n");
            fwrite(STDERR, "Statement: " . substr($stmt, 0, 200) . "\n");
            exit(1);
        }
    }

    // Record in ledger (migration file's own INSERT IGNORE handles it too, but
    // this is a safety net for files that don't self-record).
    $pdo->prepare("INSERT IGNORE INTO schema_migrations (version) VALUES (?)")
        ->execute([$basename]);

    echo "[migrate] OK    $basename\n";
    $done++;
}

if ($isDryRun) {
    echo "[migrate] Dry run complete. $pending pending migrations found.\n";
} else {
    echo "[migrate] Done. Applied $done migration(s).\n";
}
