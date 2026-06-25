<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('role.manage');
$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();

    try {
        $roles = $users->allRoles();
        foreach ($roles as $role) {
            $roleId = (int)$role['id'];
            $capIds = array_map('intval', $_POST['caps'][$roleId] ?? []);
            $capabilities->syncRole($roleId, $capIds);
        }
        $message = 'Role capabilities updated.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$roles = $users->allRoles();
$allCapabilities = $capabilities->allCapabilities();

// Map role_id => [capability_id => true] for checkbox state.
$grantedByRole = [];
foreach ($roles as $role) {
    $grantedByRole[(int)$role['id']] = array_flip(
        array_map('intval', $capabilities->capabilitiesForRole((int)$role['id']))
    );
}

renderHeader('Role capabilities', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel">
    <p class="muted">Check the capabilities each role should have. Changes take effect immediately for new requests.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="table-wrap">
            <table class="matrix">
                <thead>
                    <tr>
                        <th>Capability</th>
                        <?php foreach ($roles as $role): ?>
                            <th><?= e($role['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allCapabilities as $cap): ?>
                        <tr>
                            <td>
                                <strong><?= e($cap['name']) ?></strong><br>
                                <span class="muted"><?= e($cap['description']) ?></span>
                            </td>
                            <?php foreach ($roles as $role): ?>
                                <?php $roleId = (int)$role['id']; ?>
                                <td class="check">
                                    <label class="inline">
                                        <input type="checkbox"
                                               name="caps[<?= $roleId ?>][]"
                                               value="<?= (int)$cap['id'] ?>"
                                            <?= isset($grantedByRole[$roleId][(int)$cap['id']]) ? 'checked' : '' ?>>
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit">Save capabilities</button>
    </form>
</section>
<?php renderFooter();
