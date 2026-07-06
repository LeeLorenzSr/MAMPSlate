<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('contact_forms');

$currentUser = $auth->requireCapability('contact.manage');
$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0 ? $contacts->findFormById($id) : null;
$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    try {
        $formId = (int)($_POST['form_id'] ?? 0) ?: null;
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Name is required.');
        }
        if ($formId !== null && !$contacts->findFormById($formId)) {
            throw new RuntimeException('Contact form not found.');
        }
        $recipientEmail = trim((string)($_POST['recipient_email'] ?? ''));
        if ($recipientEmail !== '' && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Recipient email must be a valid email address.');
        }
        $slug = Slug::slugify(trim((string)($_POST['slug'] ?? '')) ?: $name);
        $slug = Slug::ensureUnique(fn($s, $exclude) => $contacts->slugExists($s, $exclude), $slug, $formId);
        $savedId = $contacts->saveForm([
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string)($_POST['description'] ?? '')),
            'recipient_email' => $recipientEmail,
            'is_active' => isset($_POST['is_active']),
            'notify_on_submit' => isset($_POST['notify_on_submit']),
        ], $formId);
        $audit->log('contact.form.saved', (int)$currentUser['id'], 'contact_form', (string)$savedId);
        redirect('/admin/contact-forms');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$forms = $contacts->allForms();
$editing = $editing ?: ['id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'recipient_email' => '', 'is_active' => 1, 'notify_on_submit' => 1];

renderHeader('Contact forms', $currentUser);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<section class="panel">
    <h2><?= (int)$editing['id'] > 0 ? 'Edit form' : 'New form' ?></h2>
    <form method="post" class="grid-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="form_id" value="<?= (int)$editing['id'] ?>">
        <label>
            Name
            <input type="text" name="name" value="<?= e($editing['name']) ?>" required maxlength="120">
        </label>
        <label>
            Slug
            <input type="text" name="slug" value="<?= e($editing['slug']) ?>" maxlength="140">
        </label>
        <label>
            Recipient email
            <input type="email" name="recipient_email" value="<?= e($editing['recipient_email']) ?>" maxlength="255">
        </label>
        <label>
            Description
            <textarea name="description" rows="3" maxlength="500"><?= e($editing['description']) ?></textarea>
        </label>
        <label><input type="checkbox" name="is_active" value="1" <?= (bool)$editing['is_active'] ? 'checked' : '' ?>> Active</label>
        <label><input type="checkbox" name="notify_on_submit" value="1" <?= (bool)$editing['notify_on_submit'] ? 'checked' : '' ?>> Notify by email</label>
        <button type="submit">Save form</button>
    </form>
</section>
<section class="panel">
    <h2>Forms</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Slug</th><th>Submissions</th><th>Active</th></tr></thead>
            <tbody>
                <?php foreach ($forms as $form): ?>
                    <tr>
                        <td><a href="/admin/contact-forms?id=<?= (int)$form['id'] ?>"><?= e($form['name']) ?></a></td>
                        <td><code><?= e($form['slug']) ?></code></td>
                        <td><?= (int)$form['submission_count'] ?></td>
                        <td><?= (bool)$form['is_active'] ? 'Yes' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();
