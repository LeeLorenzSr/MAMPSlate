<?php
declare(strict_types=1);

/**
 * Health / status endpoint.
 *
 * Standalone: does NOT load bootstrap.php (which redirects to /setup when the
 * database is down). Instead it loads config + Database directly so it can
 * report an unhealthy database instead of redirecting.
 *
 * The response is cached to a file for 60 seconds (cache/health.json) so that
 * a flood of health-check requests cannot hammer the database. Cache hits serve
 * the stored JSON without touching the database; only one regeneration per
 * minute performs a `SELECT 1`.
 */

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/http.php';
require_once APP_ROOT . '/includes/Database.php';

$configPath = APP_ROOT . '/config/config.local.php';
if (!is_file($configPath)) {
    $configPath = APP_ROOT . '/config/config.example.php';
}
$config = require $configPath;

$ttl = 60;
$cacheFile = APP_ROOT . '/cache/health.json';

security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=' . $ttl);

// Serve a fresh cache entry without touching the database.
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
    $cached = file_get_contents($cacheFile);
    $data = json_decode($cached ?: '', true);
    http_response_code(is_array($data) && ($data['status'] ?? '') === 'ok' ? 200 : 503);
    echo $cached;
    exit;
}

// Regenerate the status (at most once per TTL window).
$dbOk = false;
try {
    $pdo = Database::connect($config['database']);
    $pdo->query('SELECT 1')->fetch();
    $dbOk = true;
} catch (Throwable $e) {
    $dbOk = false;
}

$payload = [
    'status' => $dbOk ? 'ok' : 'degraded',
    'db' => $dbOk ? 'ok' : 'error',
    'service' => $config['app']['name'] ?? 'MusicPromoV2 CMS',
    'cached_at' => date('c'),
    'ttl_seconds' => $ttl,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Atomic write so concurrent requests never see a partial file.
if (!is_dir(dirname($cacheFile))) {
    @mkdir(dirname($cacheFile), 0775, true);
}
$tmp = $cacheFile . '.tmp';
if (@file_put_contents($tmp, $json) !== false) {
    @rename($tmp, $cacheFile);
}

http_response_code($dbOk ? 200 : 503);
echo $json;
