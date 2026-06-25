<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->user();
$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!rate_limit('forgot:' . $ip, 'forgot') || !rate_limit('forgot:' . $email, 'forgot')) {
        $error = 'Too many requests. Please wait a while and try again.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Always show the same generic message whether or not the email exists.
        $message = 'If an account exists for that email, a password reset link has been sent.';

        $user = $users->findByEmail($email);
        if ($user && (bool)$user['is_active']) {
            $lifetime = 30; // minutes
            $token = $passwordResets->create((int)$user['id'], $lifetime);

            $baseUrl = rtrim($config['app']['base_url'] ?? '', '/');
            $link = $baseUrl . '/auth/reset-password?token=' . $token;
            $body = '<p>A password reset was requested for your ' . e($config['app']['name'] ?? 'CMS') . ' account.</p>'
                . '<p><a href="' . e($link) . '">Reset your password</a></p>'
                . '<p>This link expires in ' . $lifetime . ' minutes. If you did not request a reset, ignore this email.</p>';
            $mailer->send($user['email'], 'Password reset request', $body);

            $audit->log('password.reset.requested', (int)$user['id'], 'user', (string)$user['id']);
        }
    }
}

renderHeader('Forgot password', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel narrow">
    <p>Enter your email address and we will send a link to reset your password.</p>
    <form method="post" action="/auth/forgot-password">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label>
            Email
            <input type="email" name="email" required autocomplete="email">
        </label>
        <button type="submit">Send reset link</button>
    </form>
    <p class="muted space-top"><a href="/">Back to sign in</a></p>
</section>
<?php renderFooter();
