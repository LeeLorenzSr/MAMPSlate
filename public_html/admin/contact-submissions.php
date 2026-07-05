<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('contact_forms');

$currentUser = $auth->requireCapability('contact.manage');
$message = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $id = (int)($_POST['submission_id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'pending');
    $contacts->setSubmissionStatus($id, $status);
    $audit->log('contact.status', (int)$currentUser['id'], 'contact_submission', (string)$id, ['status' => $status]);
    $message = 'Submission updated.';
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$submissions = $contacts->submissions($statusFilter !== '' ? $statusFilter : null);

renderHeader('Contact submissions', $currentUser);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?>
<section class="panel">
    <p><a href="/admin/contact-forms">Manage forms</a></p>
    <form method="get" class="grid-form">
        <label>
            Status
            <select name="status">
                <option value="">All</option>
                <?php foreach (['pending', 'handled', 'spam', 'archived'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Filter</button>
    </form>
    <div class="table-wrap space-top">
        <table>
            <thead><tr><th>When</th><th>Form</th><th>From</th><th>Subject</th><th>Message</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($submissions as $s): ?>
                    <tr>
                        <td><?= e($s['created_at']) ?></td>
                        <td><?= e($s['form_name']) ?></td>
                        <td><?= e($s['name']) ?><br><span class="muted"><?= e($s['email']) ?></span></td>
                        <td><?= e($s['subject']) ?></td>
                        <td><?= e(mb_strimwidth($s['message'], 0, 220, '...')) ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
                                <select name="status">
                                    <?php foreach (['pending', 'handled', 'spam', 'archived'] as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $s['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();
