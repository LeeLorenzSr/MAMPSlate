<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('custom_fields');
$currentUser = $auth->requireCapability('content.model.manage');
$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    try {
        if (($_POST['action'] ?? '') === 'create') {
            $contentExtensions->defineField([
                'entity_type' => $_POST['entity_type'] ?? '', 'field_key' => $_POST['field_key'] ?? '',
                'label' => $_POST['label'] ?? '', 'value_type' => $_POST['value_type'] ?? 'text',
                'options' => $_POST['options'] ?? '', 'is_required' => isset($_POST['is_required']),
                'is_public' => isset($_POST['is_public']), 'sort_order' => $_POST['sort_order'] ?? 0,
            ]);
            $message = 'Custom field created.';
        }
        if (($_POST['action'] ?? '') === 'delete') {
            $contentExtensions->deleteDefinition((int)($_POST['id'] ?? 0));
            $message = 'Custom field deleted.';
        }
        $audit->log('content_model.updated', (int)$currentUser['id'], 'content_model');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

renderHeader('Content model', $currentUser);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<section class="panel">
    <h2>Define a custom field</h2>
    <p class="muted">Fields appear in the matching content editor and are validated before save. They are public by default; disable that for internal workflow values.</p>
    <form method="post" class="grid-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create">
        <label>Content type<select name="entity_type"><?php foreach (ContentExtensionRepository::ENTITY_TYPES as $type): ?><option value="<?= e($type) ?>"><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select></label>
        <label>Field key<input name="field_key" pattern="[a-z][a-z0-9_]{0,79}" required placeholder="release_date"></label>
        <label>Label<input name="label" maxlength="120" required placeholder="Release date"></label>
        <label>Type<select name="value_type"><?php foreach (ContentExtensionRepository::FIELD_TYPES as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></label>
        <label>Options (one per line; select only)<textarea name="options" rows="3"></textarea></label>
        <label>Sort order<input name="sort_order" type="number" value="0"></label>
        <label class="inline"><input name="is_required" type="checkbox"> Required</label>
        <label class="inline"><input name="is_public" type="checkbox" checked> Public</label>
        <p><button type="submit">Create field</button></p>
    </form>
</section>
<?php foreach (ContentExtensionRepository::ENTITY_TYPES as $type): ?>
<section class="panel">
    <h2><?= e(ucfirst($type)) ?> fields</h2>
    <?php $definitions = $contentExtensions->definitions($type); ?>
    <?php if ($definitions === []): ?><p class="muted">No fields defined.</p><?php else: ?><table><thead><tr><th>Key</th><th>Label</th><th>Type</th><th>Visibility</th><th></th></tr></thead><tbody>
    <?php foreach ($definitions as $definition): ?><tr><td><code><?= e($definition['field_key']) ?></code></td><td><?= e($definition['label']) ?></td><td><?= e($definition['value_type']) ?></td><td><?= !empty($definition['is_public']) ? 'Public' : 'Internal' ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$definition['id'] ?>"><button class="danger" data-confirm="Delete this field and all of its values?">Delete</button></form></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
</section>
<?php endforeach; ?>
<?php renderFooter();
