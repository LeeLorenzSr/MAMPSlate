<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('backup.manage');
$backupsDir = APP_ROOT . '/backups';
if (!is_dir($backupsDir)) {
    @mkdir($backupsDir, 0775, true);
}

if (isset($_GET['download'])) {
    $file = basename((string)$_GET['download']);
    $path = $backupsDir . '/' . $file;
    if (!preg_match('/^(db|files)_\d{8}_\d{6}\.(sql\.gz|tar\.gz)$/', $file) || !is_file($path)) {
        http_response_code(404);
        exit('Backup not found.');
    }
    prevent_caching();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $type = (string)($_POST['type'] ?? '');
    $script = $type === 'db' ? 'backup_db.php' : ($type === 'files' ? 'backup_files.php' : '');
    if ($script === '') {
        $error = 'Unknown backup type.';
    } else {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(APP_ROOT . '/tools/' . $script) . ' 2>&1';
        exec($cmd, $output, $rc);
        $ok = $rc === 0;
        $audit->log('backup.run', (int)$currentUser['id'], 'backup', $type, ['ok' => $ok ? '1' : '0']);
        $message = $ok ? 'Backup completed.' : null;
        $error = $ok ? null : 'Backup failed. Check server tools and permissions.';
        if (!$ok) {
            error_log('backup failed: ' . implode("\n", $output));
        }
    }
}

$files = glob($backupsDir . '/*') ?: [];
rsort($files);

renderHeader('Backups', $currentUser);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<section class="panel">
    <h2>Create backup</h2>
    <form method="post" class="action-bar">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <button type="submit" name="type" value="db" data-confirm="Run a database backup now?">Database backup</button>
        <button type="submit" name="type" value="files" data-confirm="Run a file backup now?">File backup</button>
    </form>
</section>
<section class="panel">
    <h2>Available backups</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>File</th><th>Size</th><th>Modified</th><th>Download</th></tr></thead>
            <tbody>
                <?php foreach ($files as $path): ?>
                    <?php $file = basename($path); ?>
                    <?php if (!preg_match('/^(db|files)_\d{8}_\d{6}\.(sql\.gz|tar\.gz)$/', $file)) { continue; } ?>
                    <tr>
                        <td><code><?= e($file) ?></code></td>
                        <td><?= e(number_format((int)filesize($path))) ?> bytes</td>
                        <td><?= e(date('Y-m-d H:i:s', filemtime($path))) ?></td>
                        <td><a href="/admin/backups?download=<?= e(urlencode($file)) ?>">Download</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();
