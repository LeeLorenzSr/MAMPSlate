<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('system.view');

$checks = [];
$add = static function (string $label, bool $ok, string $detail) use (&$checks): void {
    $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
};

foreach (['pdo_mysql', 'mbstring', 'json', 'fileinfo', 'gd', 'openssl'] as $ext) {
    $add('PHP extension: ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing');
}

foreach ([
    'cache' => APP_ROOT . '/cache',
    'logs' => APP_ROOT . '/logs',
    'uploads' => APP_ROOT . '/public_html/uploads',
    'config' => APP_ROOT . '/config',
    'backups' => APP_ROOT . '/backups',
] as $label => $path) {
    $exists = is_dir($path) || @mkdir($path, 0775, true);
    $add('Writable directory: ' . $label, $exists && is_writable($path), $path);
}

$dbVersion = 'unknown';
try {
    $dbVersion = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $add('Database connection', true, $dbVersion);
} catch (Throwable $e) {
    $add('Database connection', false, 'query failed');
}

$add('Upload max filesize', true, (string)ini_get('upload_max_filesize'));
$add('Post max size', true, (string)ini_get('post_max_size'));
$add('Mail mode', in_array(($config['mail']['mode'] ?? 'log'), ['log', 'mail'], true), (string)($config['mail']['mode'] ?? 'log'));
$baseUrl = (string)setting('app.base_url', $config['app']['base_url'] ?? '');
$add('Base URL', $baseUrl !== '', $baseUrl !== '' ? $baseUrl : 'empty');
$add('HTTPS request', is_https() || ($config['app']['env'] ?? 'local') === 'local', is_https() ? 'yes' : 'no');
$add('Secure cookies', (bool)($config['security']['secure_cookies'] ?? false) || ($config['app']['env'] ?? 'local') === 'local', !empty($config['security']['secure_cookies']) ? 'enabled' : 'disabled');

$files = $migrations->migrationFiles();
$statusMap = $migrations->statusMap();
$pending = [];
$failed = [];
foreach ($files as $path) {
    $filename = basename($path);
    $status = $statusMap[$filename]['status'] ?? 'pending';
    if ($status === 'pending') {
        $pending[] = $filename;
    } elseif ($status === 'failed') {
        $failed[] = $filename;
    }
}
$add('Migration state', !$failed && !$pending, (!$failed && !$pending) ? 'up to date' : (count($pending) . ' pending, ' . count($failed) . ' failed'));

renderHeader('System Status', $currentUser);
?>
<section class="panel">
    <h2>Diagnostics</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
            <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td><?= e($check['label']) ?></td>
                        <td><?= $check['ok'] ? 'OK' : 'Needs attention' ?></td>
                        <td><code><?= e($check['detail']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($pending || $failed): ?>
<section class="panel">
    <h2>Migration detail</h2>
    <?php if ($pending): ?><p>Pending: <code><?= e(implode(', ', $pending)) ?></code></p><?php endif; ?>
    <?php if ($failed): ?><p>Failed: <code><?= e(implode(', ', $failed)) ?></code></p><?php endif; ?>
    <?php if ($auth->can('settings.manage')): ?><p><a href="/admin/migrations">Open migrations</a></p><?php endif; ?>
</section>
<?php endif; ?>
<?php renderFooter();
