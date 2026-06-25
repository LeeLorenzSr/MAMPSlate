<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->user();
$message = null;
$error = null;
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');

// Validate the token up front so the form is only shown for valid links.
$resetRow = $token !== '' ? $passwordResets->findValid($token) : null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!rate_limit('reset:' . $ip, 'reset')) {
        $error = 'Too many attempts. Please wait a while and try again.';
    } elseif (!$resetRow) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } else {
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');

        if (strlen($newPassword) < Auth::MIN_PASSWORD_LENGTH) {
            $error = sprintf('Password must be at least %d characters.', Auth::MIN_PASSWORD_LENGTH);
        } elseif ($newPassword !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $userId = (int)$resetRow['user_id'];
            $users->setPassword($userId, $newPassword);
            $passwordResets->markUsed((int)$resetRow['id']);
            $passwordResets->invalidateForUser($userId);
            $apiAuth->revokeSessionsForUser($userId);

            $audit->log('password.reset.completed', $userId, 'user', (string)$userId);

            // Log out any current web session for this user (if they were signed in).
            if ($currentUser && (int)$currentUser['id'] === $userId) {
                $auth->logout();
            }

            $_SESSION['reset_notice'] = 'Your password has been reset. You can now sign in.';
            redirect('/');
        }
    }
}

renderHeader('Reset password', $currentUser);
?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<?php if (!$resetRow && !$error): ?>
    <p class="notice error">This reset link is invalid or has expired. Please request a new one.</p>
<?php endif; ?>

<?php if ($resetRow): ?>
<section class="panel narrow">
    <p>Choose a new password (minimum <?= (int)Auth::MIN_PASSWORD_LENGTH ?> characters).</p>
    <form method="post" action="/auth/reset-password">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>
            New password
            <input type="password" name="new_password" required autocomplete="new-password" minlength="<?= (int)Auth::MIN_PASSWORD_LENGTH ?>">
        </label>
        <label>
            Confirm new password
            <input type="password" name="new_password_confirm" required autocomplete="new-password" minlength="<?= (int)Auth::MIN_PASSWORD_LENGTH ?>">
        </label>
        <button type="submit">Reset password</button>
    </form>
</section>
<?php endif; ?>
<p class="muted"><a href="/">Back to sign in</a></p>
<?php renderFooter();
