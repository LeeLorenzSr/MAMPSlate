<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

requireFeature('contact_forms');

$currentUser = $auth->user();
$form = $contacts->findFormBySlug('contact');
$message = null;
$error = null;
$appName = setting('site.name', $config['app']['name'] ?? 'MAMPSlate CMS');

if (!$form || !(bool)$form['is_active']) {
    http_response_code(404);
    exit('Contact form unavailable.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body = trim((string)($_POST['message'] ?? ''));
    $honeypot = trim((string)($_POST['website'] ?? ''));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowed = rate_limit('contact:' . $ip, 'contact');

    if ($honeypot !== '' || !$allowed) {
        error_log('contact submission rejected: ' . hash('sha256', $ip . '|' . $email));
        $message = 'Thanks. Your message has been received.';
    } elseif ($name === '' || $email === '' || $body === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Name, valid email, and message are required.';
    } else {
        $status = ((string)setting('contact_require_moderation', '1') === '1') ? 'pending' : 'handled';
        $submissionId = $contacts->createSubmission([
            'form_id' => (int)$form['id'],
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $body,
            'status' => $status,
            'ip_hash' => hash('sha256', $ip),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
        $audit->log('contact.submitted', $currentUser ? (int)$currentUser['id'] : null, 'contact_submission', (string)$submissionId);
        $notifications->create(null, 'form.submitted', 'New contact submission', $subject !== '' ? $subject : 'Contact form', '/admin/contact-submissions');
        $webhookDispatcher->dispatch('form.submitted', ['submission_id' => $submissionId, 'form_id' => (int)$form['id'], 'status' => $status]);

        $recipient = trim((string)$form['recipient_email']);
        if ($recipient !== '' && (bool)$form['notify_on_submit']) {
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                error_log('contact notification skipped: invalid recipient on form ' . (int)$form['id']);
            } else {
                try {
                    $mailer->send(
                        $recipient,
                        'New contact submission: ' . ($subject !== '' ? $subject : $appName),
                        '<p><strong>From:</strong> ' . e($name) . ' &lt;' . e($email) . '&gt;</p>'
                        . '<p><strong>Subject:</strong> ' . e($subject) . '</p>'
                        . '<p>' . nl2br(e($body)) . '</p>'
                    );
                } catch (Throwable $e) {
                    error_log('contact notification failed for submission ' . $submissionId . ': ' . $e->getMessage());
                }
            }
        }
        $message = 'Thanks. Your message has been received.';
        $_POST = [];
    }
}

renderHeader('Contact', $currentUser, [
    'canonical' => '/contact',
    'description' => 'Get in touch with ' . $appName . '.',
]);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>

<section class="panel">
    <?php if (!empty($form['description'])): ?>
        <p><?= e($form['description']) ?></p>
    <?php endif; ?>
    <form method="post" class="grid-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label>
            Name
            <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required maxlength="120" autocomplete="name">
        </label>
        <label>
            Email
            <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required maxlength="255" autocomplete="email">
        </label>
        <label>
            Subject
            <input type="text" name="subject" value="<?= e($_POST['subject'] ?? '') ?>" maxlength="200">
        </label>
        <label class="visually-hidden">
            Website
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off">
        </label>
        <label>
            Message
            <textarea name="message" rows="8" required><?= e($_POST['message'] ?? '') ?></textarea>
        </label>
        <button type="submit">Send message</button>
    </form>
</section>
<?php renderFooter();
