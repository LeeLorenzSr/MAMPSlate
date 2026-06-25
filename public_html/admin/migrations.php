<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('settings.manage');
$message = null;
$error = null;
$runResults = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    if (($_POST['action'] ?? '') === 'run') {
        $runResults = $migrations->runPending();
        $allOk = $runResults && !in_array(false, array_map(fn($r) => $r['ok'], $runResults), true);
        $message = $allOk ? 'Pending migrations applied.' : 'Migration run stopped at the first error (see below).';
        if (!$allOk) {
            $error = 'One or more migrations failed.';
        }
        $audit->log('migrations.run', (int)$currentUser['id'], 'schema_migrations', null, ['ok' => $allOk ? '1' : '0']);
    }
}

$statusMap = $migrations->statusMap();
$files = $migrations->migrationFiles();

renderHeader('Migrations', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<?php if ($runResults !== null): ?>
<section class="panel">
    <h2>Run results</h2>
    <ul class="checklist">
        <?php foreach ($runResults as $r): ?>
            <li class="<?= $r['ok'] ? 'ok' : 'bad' ?>">
                <?= $r['ok'] ? '✓' : '✗' ?> <code><?= e($r['file']) ?></code>
                <?php if (!empty($r['applied']) && $r['ok']): ?><span class="muted">(applied)</span><?php endif; ?>
                <?php if (!$r['ok']): ?> — <?= e($r['error'] ?? '') ?><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Migration status</h2>
    <p class="muted">Migrations are idempotent and run in filename order. The dev-only <code>000_reset_dev.sql</code> is never run here.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="run">
        <button type="submit">Run pending migrations</button>
    </form>
    <div class="table-wrap space-top">
        <table>
            <thead>
                <tr><th>File</th><th>Status</th><th>Applied at</th></tr>
            </thead>
            <tbody>
                <?php foreach ($files as $path):
                    $filename = basename($path);
                    $rec = $statusMap[$filename] ?? null; ?>
                    <tr>
                        <td><code><?= e($filename) ?></code></td>
                        <td><?= e($rec['status'] ?? 'pending') ?></td>
                        <td><?= e($rec['applied_at'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();
