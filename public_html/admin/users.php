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
            $email = trim($_POST['email'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $roleId = (int)($_POST['role_id'] ?? 0);
            $password = (string)($_POST['password'] ?? '');

            if ($email === '' || $displayName === '' || $roleId <= 0 || strlen($password) < Auth::MIN_PASSWORD_LENGTH) {
                throw new RuntimeException('Provide email, display name, role, and a password of at least ' . Auth::MIN_PASSWORD_LENGTH . ' characters.');
            }

            $newUserId = $users->createUser($email, $displayName, $roleId, $password);
            $audit->log('user.created', (int)$currentUser['id'], 'user', (string)$newUserId, ['email' => strtolower(trim($email))]);
            $message = 'User created.';
        }

        if ($action === 'update') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $before = $users->findById($userId);
            $newActive = isset($_POST['is_active']);
            $newRoleId = (int)($_POST['role_id'] ?? 0);

            $users->updateUser(
                $userId,
                (string)($_POST['display_name'] ?? ''),
                $newRoleId,
                $newActive
            );

            $newPassword = (string)($_POST['new_password'] ?? '');
            if ($newPassword !== '') {
                if (strlen($newPassword) < Auth::MIN_PASSWORD_LENGTH) {
                    throw new RuntimeException('New passwords must be at least ' . Auth::MIN_PASSWORD_LENGTH . ' characters.');
                }
                $users->setPassword($userId, $newPassword);
                $audit->log('user.password.reset', (int)$currentUser['id'], 'user', (string)$userId);
            }

            if ($before) {
                if ((bool)$before['is_active'] !== $newActive) {
                    $audit->log($newActive ? 'user.activated' : 'user.deactivated', (int)$currentUser['id'], 'user', (string)$userId);
                }
                if ((int)$before['role_id'] !== $newRoleId) {
                    $audit->log('user.role_changed', (int)$currentUser['id'], 'user', (string)$userId, ['from' => $before['role_name'], 'to_role_id' => $newRoleId]);
                }
            }
            $audit->log('user.updated', (int)$currentUser['id'], 'user', (string)$userId);

            $message = 'User updated.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$allUsers = $users->allUsers();
$roles = $users->allRoles();

renderHeader('User management', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel">
    <h2>Create user</h2>
    <form method="post" class="grid-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <label>
            Email
            <input type="email" name="email" required>
        </label>
        <label>
            Display name
            <input type="text" name="display_name" required>
        </label>
        <label>
            Role
            <select name="role_id" required>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int)$role['id'] ?>"><?= e($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Temporary password
            <input type="password" name="password" required minlength="<?= Auth::MIN_PASSWORD_LENGTH ?>">
        </label>
        <button type="submit">Create user</button>
    </form>
</section>

<section class="panel">
    <h2>Users</h2>
    <?php foreach ($allUsers as $managedUser): ?>
        <form id="user-form-<?= (int)$managedUser['id'] ?>" method="post"></form>
    <?php endforeach; ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Display name</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>New password</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allUsers as $managedUser): ?>
                <?php $formId = 'user-form-' . (int)$managedUser['id']; ?>
                <tr>
                    <td><?= e($managedUser['email']) ?></td>
                    <td>
                        <input form="<?= e($formId) ?>" type="text" name="display_name" value="<?= e($managedUser['display_name']) ?>" required>
                    </td>
                    <td>
                        <select form="<?= e($formId) ?>" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int)$role['id'] ?>" <?= $role['name'] === $managedUser['role_name'] ? 'selected' : '' ?>>
                                    <?= e($role['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <label class="inline">
                            <input form="<?= e($formId) ?>" type="checkbox" name="is_active" <?= (bool)$managedUser['is_active'] ? 'checked' : '' ?>>
                            Active
                        </label>
                    </td>
                    <td>
                        <input form="<?= e($formId) ?>" type="password" name="new_password" minlength="<?= Auth::MIN_PASSWORD_LENGTH ?>" autocomplete="new-password" placeholder="Leave unchanged">
                    </td>
                    <td><?= e($managedUser['created_at']) ?></td>
                    <td>
                        <input form="<?= e($formId) ?>" type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input form="<?= e($formId) ?>" type="hidden" name="action" value="update">
                        <input form="<?= e($formId) ?>" type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>">
                        <button form="<?= e($formId) ?>" type="submit">Save</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter(); ?>
