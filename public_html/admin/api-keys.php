<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('apikey.manage');
$message = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    if (($_POST['action'] ?? '') === 'revoke') {
        $keyId = (int)($_POST['api_key_id'] ?? 0);
        $apiKeys->revoke($keyId);
        $audit->log('apikey.revoked', (int)$currentUser['id'], 'api_key', (string)$keyId);
        $message = 'API key revoked.';
    }
}

$allApiKeys = $apiKeys->listAll();

renderHeader('API key management', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>

<section class="panel">
    <h2>API keys</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Name</th>
                    <th>Prefix</th>
                    <th>Status</th>
                    <th>Last used</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allApiKeys as $key): ?>
                    <tr>
                        <td><?= e($key['display_name']) ?><br><span class="muted"><?= e($key['email']) ?></span></td>
                        <td><?= e($key['name']) ?></td>
                        <td><code><?= e($key['key_prefix']) ?></code></td>
                        <td><?= $key['revoked_at'] ? 'Revoked' : 'Active' ?></td>
                        <td><?= e($key['last_used_at'] ?: 'Never') ?></td>
                        <td><?= e($key['created_at']) ?></td>
                        <td>
                            <?php if (!$key['revoked_at']): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="api_key_id" value="<?= (int)$key['id'] ?>">
                                    <button type="submit">Revoke</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter(); ?>
