<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('user.manage');
$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $maxUses = max(1, (int)($_POST['max_uses'] ?? 1));
            $expiresAt = trim((string)($_POST['expires_at'] ?? ''));
            $expiresAt = $expiresAt === '' ? null : date('Y-m-d H:i:s', strtotime($expiresAt));
            if ($expiresAt === null && trim((string)($_POST['expires_at'] ?? '')) !== '') {
                throw new RuntimeException('Expiry date was not recognized.');
            }

            $created = $invites->create((int)$currentUser['id'], $maxUses, $expiresAt);
            $_SESSION['new_invite_code'] = $created['code'];
            redirect('/admin/invites');
        }

        if ($action === 'revoke') {
            $invites->revoke((int)($_POST['invite_id'] ?? 0));
            $message = 'Invite code revoked.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$newInviteCode = $_SESSION['new_invite_code'] ?? null;
unset($_SESSION['new_invite_code']);
$allInvites = $invites->listAll();

renderHeader('Invite codes', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<?php if ($newInviteCode): ?>
    <section class="panel">
        <h2>New invite code</h2>
        <p class="notice success">Copy this code now. It will not be shown again.</p>
        <code class="secret"><?= e($newInviteCode) ?></code>
    </section>
<?php endif; ?>

<?php if (($config['app']['signup_mode'] ?? '') === 'invite'): ?>
<section class="panel">
    <h2>Create invite code</h2>
    <form method="post" class="grid-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <label>
            Max uses
            <input type="number" name="max_uses" min="1" value="1" required>
        </label>
        <label>
            Expires at (optional)
            <input type="datetime-local" name="expires_at">
        </label>
        <button type="submit">Create code</button>
    </form>
</section>
<?php else: ?>
    <p class="muted">Invite codes are only used when <code>app.signup_mode</code> is <code>invite</code>. You can still generate codes here ahead of switching modes.</p>
<?php endif; ?>

<section class="panel">
    <h2>Invite codes</h2>
    <?php if (!$allInvites): ?>
        <p class="muted">No invite codes yet.</p>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Prefix</th>
                    <th>Uses</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Created by</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allInvites as $inv): ?>
                    <?php
                        $status = 'Active';
                        if (!empty($inv['revoked_at'])) {
                            $status = 'Revoked';
                        } elseif ((int)$inv['uses'] >= (int)$inv['max_uses']) {
                            $status = 'Exhausted';
                        } elseif (!empty($inv['expires_at']) && strtotime($inv['expires_at']) <= time()) {
                            $status = 'Expired';
                        }
                    ?>
                    <tr>
                        <td><code><?= e($inv['code_prefix']) ?>…</code></td>
                        <td><?= (int)$inv['uses'] ?> / <?= (int)$inv['max_uses'] ?></td>
                        <td><?= e($inv['expires_at'] ?: 'Never') ?></td>
                        <td><?= e($status) ?></td>
                        <td><?= e($inv['created_by_name'] ?: '—') ?></td>
                        <td><?= e($inv['created_at']) ?></td>
                        <td>
                            <?php if (empty($inv['revoked_at'])): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                                    <button type="submit" class="danger">Revoke</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();
