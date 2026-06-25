<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$currentUser = $auth->requireLogin();
$message = null;
$error = null;

$avatarSize = (int)($config['app']['avatar_size'] ?? 256);
$avatarMaxBytes = (int)($config['app']['avatar_max_upload_bytes'] ?? 3145728);
$coverMaxWidth = (int)($config['app']['cover_max_width'] ?? 1600);
$coverMaxBytes = (int)($config['app']['cover_max_upload_bytes'] ?? $avatarMaxBytes);
$uploadsRoot = $GLOBALS['uploadsRoot'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'upload_avatar') {
            if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Choose an image to upload.');
            }
            if ((int)($_FILES['avatar']['size'] ?? 0) > $avatarMaxBytes) {
                throw new RuntimeException('The image is larger than the maximum allowed size.');
            }

            $meta = $imageProcessor->processAvatarUpload($_FILES['avatar'], $avatarSize);

            // Remove the previous avatar file if any.
            if (!empty($currentUser['avatar'])) {
                $oldPath = $uploadsRoot . '/' . $currentUser['avatar'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $users->setAvatar((int)$currentUser['id'], $meta['stored_name']);
            $message = 'Profile picture updated.';
        }

        if ($action === 'remove_avatar') {
            if (!empty($currentUser['avatar'])) {
                $oldPath = $uploadsRoot . '/' . $currentUser['avatar'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
                $users->setAvatar((int)$currentUser['id'], null);
                $message = 'Profile picture removed.';
            }
        }

        if ($action === 'upload_cover') {
            if (empty($_FILES['cover']) || $_FILES['cover']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Choose an image to upload.');
            }
            if ((int)($_FILES['cover']['size'] ?? 0) > $coverMaxBytes) {
                throw new RuntimeException('The image is larger than the maximum allowed size.');
            }

            $meta = $imageProcessor->processCoverUpload($_FILES['cover'], $coverMaxWidth);

            if (!empty($currentUser['cover_photo'])) {
                $oldPath = $uploadsRoot . '/' . $currentUser['cover_photo'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $users->setCoverPhoto((int)$currentUser['id'], $meta['stored_name']);
            $audit->log('profile.cover_updated', (int)$currentUser['id'], 'user', (string)$currentUser['id'], ['action' => 'upload']);
            $message = 'Cover photo updated.';
        }

        if ($action === 'remove_cover') {
            if (!empty($currentUser['cover_photo'])) {
                $oldPath = $uploadsRoot . '/' . $currentUser['cover_photo'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
                $users->setCoverPhoto((int)$currentUser['id'], null);
                $audit->log('profile.cover_updated', (int)$currentUser['id'], 'user', (string)$currentUser['id'], ['action' => 'remove']);
                $message = 'Cover photo removed.';
            }
        }

        if ($action === 'update_profile') {
            $bio = trim((string)($_POST['bio'] ?? ''));
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            $socialGithub = trim((string)($_POST['social_github'] ?? ''));
            $socialLinkedin = trim((string)($_POST['social_linkedin'] ?? ''));
            $socialWebsite = trim((string)($_POST['social_website'] ?? ''));
            $hideEmail = isset($_POST['hide_email']);

            if (mb_strlen($bio) > 250) {
                throw new RuntimeException('Short bio must be 250 characters or fewer.');
            }
            if ($slug === '' || strlen($slug) < 3 || strlen($slug) > 120
                || !preg_match('/^[a-z0-9-]+$/', $slug)) {
                throw new RuntimeException('Profile URL handle must be 3-120 characters: lowercase letters, numbers, and dashes only.');
            }
            if ($slug !== ($currentUser['slug'] ?? null) && $users->slugExists($slug, (int)$currentUser['id'])) {
                throw new RuntimeException('That profile URL handle is already taken.');
            }
            foreach (['GitHub' => $socialGithub, 'LinkedIn' => $socialLinkedin, 'Website' => $socialWebsite] as $label => $url) {
                if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL)
                    || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true))) {
                    throw new RuntimeException($label . ' link must be a valid http(s) URL.');
                }
            }

            $users->updateProfile(
                (int)$currentUser['id'],
                $slug,
                $bio,
                $socialGithub,
                $socialLinkedin,
                $socialWebsite,
                $hideEmail
            );

            // Audit: record which fields changed, without persisting the user's
            // bio content or link URLs. The slug is the public identifier and is
            // kept so the actor's handle is traceable.
            $changed = [];
            if ($bio !== (string)($currentUser['bio'] ?? '')) {
                $changed[] = 'bio';
            }
            if ($slug !== (string)($currentUser['slug'] ?? '')) {
                $changed[] = 'slug';
            }
            if ($socialGithub !== (string)($currentUser['social_github'] ?? '')) {
                $changed[] = 'social_github';
            }
            if ($socialLinkedin !== (string)($currentUser['social_linkedin'] ?? '')) {
                $changed[] = 'social_linkedin';
            }
            if ($socialWebsite !== (string)($currentUser['social_website'] ?? '')) {
                $changed[] = 'social_website';
            }
            if ($hideEmail !== (bool)($currentUser['hide_email'] ?? false)) {
                $changed[] = 'hide_email';
            }
            $audit->log('profile.updated', (int)$currentUser['id'], 'user', (string)$currentUser['id'], [
                'fields' => $changed,
                'slug' => $slug,
            ]);

            $message = 'Profile details saved.';
        }

        if ($action === 'change_password') {
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['new_password_confirm'] ?? '');

            // OAuth-only accounts (no password) may set one without proving the current.
            if ($currentUser['password_hash'] !== null) {
                $current = (string)($_POST['current_password'] ?? '');
                if (!password_verify($current, $currentUser['password_hash'])) {
                    throw new RuntimeException('Current password is incorrect.');
                }
            }

            if (strlen($newPassword) < Auth::MIN_PASSWORD_LENGTH) {
                throw new RuntimeException(sprintf('Password must be at least %d characters.', Auth::MIN_PASSWORD_LENGTH));
            }
            if ($newPassword !== $confirm) {
                throw new RuntimeException('New passwords do not match.');
            }

            $users->setPassword((int)$currentUser['id'], $newPassword);
            $message = 'Password changed.';
        }

        if ($action === 'create_api_key' && $auth->can('apikey.own')) {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                throw new RuntimeException('Provide an API key name.');
            }

            $created = $apiKeys->createForUser((int)$currentUser['id'], $name);
            $audit->log('apikey.created', (int)$currentUser['id'], 'api_key', (string)$created['id'], ['name' => $name]);
            $_SESSION['new_api_key'] = $created['api_key'];
            redirect('/profile');
        }

        if ($action === 'revoke_api_key' && $auth->can('apikey.own')) {
            $keyId = (int)($_POST['api_key_id'] ?? 0);
            $apiKeys->revokeForUser($keyId, (int)$currentUser['id']);
            $audit->log('apikey.revoked', (int)$currentUser['id'], 'api_key', (string)$keyId);
            $message = 'API key revoked.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

// Refresh the user so avatar/password changes are reflected immediately.
$currentUser = $auth->user();

// Guarantee the user has a public slug so /user/{slug} always resolves.
$users->ensureSlug((int)$currentUser['id']);
$currentUser = $auth->user();

$newApiKey = $_SESSION['new_api_key'] ?? null;
unset($_SESSION['new_api_key']);
$userApiKeys = $apiKeys->listForUser((int)$currentUser['id']);
$canManageApiKeys = $auth->can('apikey.own');
$hasPassword = $currentUser['password_hash'] !== null;

renderHeader('Profile', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>
<?php if ($newApiKey): ?>
    <section class="panel">
        <h2>New API key</h2>
        <p class="notice success">Copy this key now. It will not be shown again.</p>
        <code class="secret"><?= e($newApiKey) ?></code>
    </section>
<?php endif; ?>

<section class="panel profile-head">
    <div class="profile-avatar-large"><?php renderAvatar($currentUser, 96) ?></div>
    <dl class="details">
        <dt>Email</dt>
        <dd><?= e($currentUser['email']) ?></dd>
        <dt>Display name</dt>
        <dd><?= e($currentUser['display_name']) ?></dd>
        <dt>Role</dt>
        <dd><?= e($currentUser['role_name']) ?></dd>
        <dt>Last login</dt>
        <dd><?= e($currentUser['last_login_at'] ?: 'Not recorded') ?></dd>
    </dl>
</section>

<section class="panel">
    <h2>Profile details</h2>
    <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="update_profile">
        <label>
            Short bio
            <textarea name="bio" rows="3" maxlength="250" placeholder="A brief, work-focused introduction."><?= e($currentUser['bio'] ?? '') ?></textarea>
            <small class="muted">Up to 250 characters.</small>
        </label>
        <label>
            Profile URL handle
            <input type="text" name="slug" value="<?= e($currentUser['slug'] ?? '') ?>" required
                   minlength="3" maxlength="120" pattern="[a-z0-9-]+"
                   autocomplete="off">
            <small class="muted">Your public profile is at <code>/user/<?= e($currentUser['slug'] ?? 'your-handle') ?></code>. Lowercase letters, numbers, and dashes only.</small>
        </label>
        <label>
            GitHub
            <input type="url" name="social_github" value="<?= e($currentUser['social_github'] ?? '') ?>" placeholder="https://github.com/you">
        </label>
        <label>
            LinkedIn
            <input type="url" name="social_linkedin" value="<?= e($currentUser['social_linkedin'] ?? '') ?>" placeholder="https://www.linkedin.com/in/you">
        </label>
        <label>
            Website
            <input type="url" name="social_website" value="<?= e($currentUser['social_website'] ?? '') ?>" placeholder="https://yoursite.com">
        </label>
        <label class="checkbox">
            <input type="checkbox" name="hide_email" value="1" <?= (bool)($currentUser['hide_email'] ?? false) ? 'checked' : '' ?>>
            Hide email address from public profile
        </label>
        <button type="submit">Save profile details</button>
    </form>
</section>

<section class="panel">
    <h2>Cover photo</h2>
    <form method="post" enctype="multipart/form-data" class="grid-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="upload_cover">
        <label>
            Image (JPEG, PNG, GIF, WebP; max <?= number_format($coverMaxBytes) ?> bytes)
            <input type="file" name="cover" accept="image/*" required>
        </label>
        <button type="submit">Upload</button>
    </form>
    <?php if (!empty($currentUser['cover_photo'])): ?>
        <form method="post" class="space-top">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="remove_cover">
            <button type="submit" class="danger" data-confirm="Remove your cover photo?">Remove cover</button>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Profile picture</h2>
    <form method="post" enctype="multipart/form-data" class="grid-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="upload_avatar">
        <label>
            Image (JPEG, PNG, GIF, WebP; max <?= number_format($avatarMaxBytes) ?> bytes)
            <input type="file" name="avatar" accept="image/*" required>
        </label>
        <button type="submit">Upload</button>
    </form>
    <?php if (!empty($currentUser['avatar'])): ?>
        <form method="post" class="space-top">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="remove_avatar">
            <button type="submit" class="danger" data-confirm="Remove your profile picture?">Remove picture</button>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Change password</h2>
    <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="change_password">
        <?php if ($hasPassword): ?>
            <label>
                Current password
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
        <?php else: ?>
            <p class="muted">Your account has no password yet. Set one below to enable email/password login.</p>
        <?php endif; ?>
        <label>
            New password
            <input type="password" name="new_password" required autocomplete="new-password" minlength="10">
        </label>
        <label>
            Confirm new password
            <input type="password" name="new_password_confirm" required autocomplete="new-password" minlength="10">
        </label>
        <button type="submit">Change password</button>
    </form>
</section>

<?php if ($canManageApiKeys): ?>
<section class="panel">
    <h2>API keys</h2>
    <form method="post" class="grid-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="create_api_key">
        <label>
            Name
            <input type="text" name="name" required>
        </label>
        <button type="submit">Create API key</button>
    </form>
    <div class="table-wrap space-top">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Prefix</th>
                    <th>Status</th>
                    <th>Last used</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userApiKeys as $key): ?>
                    <tr>
                        <td><?= e($key['name']) ?></td>
                        <td><code><?= e($key['key_prefix']) ?></code></td>
                        <td><?= $key['revoked_at'] ? 'Revoked' : 'Active' ?></td>
                        <td><?= e($key['last_used_at'] ?: 'Never') ?></td>
                        <td><?= e($key['created_at']) ?></td>
                        <td>
                            <?php if (!$key['revoked_at']): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="revoke_api_key">
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
<?php endif; ?>
<?php renderFooter();
