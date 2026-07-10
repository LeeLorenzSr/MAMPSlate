<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('taxonomies');
$currentUser = $auth->requireCapability('taxonomy.manage');
$message = null;
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_taxonomy') {
            $taxonomies->create(['name' => $_POST['name'] ?? '', 'slug' => $_POST['slug'] ?? '', 'description' => $_POST['description'] ?? '', 'is_hierarchical' => isset($_POST['is_hierarchical'])]);
            $message = 'Taxonomy created.';
        } elseif ($action === 'create_term') {
            $taxonomies->createTerm((int)($_POST['taxonomy_id'] ?? 0), ['name' => $_POST['name'] ?? '', 'slug' => $_POST['slug'] ?? '', 'description' => $_POST['description'] ?? '', 'parent_id' => $_POST['parent_id'] ?? null]);
            $message = 'Term created.';
        } elseif ($action === 'delete_taxonomy') {
            $taxonomies->delete((int)($_POST['id'] ?? 0));
            $message = 'Taxonomy deleted.';
        } elseif ($action === 'delete_term') {
            $taxonomies->deleteTerm((int)($_POST['id'] ?? 0));
            $message = 'Term deleted.';
        }
        $audit->log('taxonomy.updated', (int)$currentUser['id'], 'taxonomy');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$allTaxonomies = $taxonomies->all();
renderHeader('Taxonomies', $currentUser);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?><?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<section class="panel"><h2>New taxonomy</h2><form method="post" class="grid-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create_taxonomy"><label>Name<input name="name" required></label><label>Slug<input name="slug" placeholder="auto from name"></label><label>Description<input name="description" maxlength="500"></label><label class="inline"><input type="checkbox" name="is_hierarchical" checked> Allow nested terms</label><p><button>Create taxonomy</button></p></form></section>
<?php foreach ($allTaxonomies as $taxonomy): ?>
<section class="panel"><h2><?= e($taxonomy['name']) ?></h2><p class="muted"><?= e($taxonomy['description']) ?> · <?= (int)$taxonomy['term_count'] ?> terms</p>
    <form method="post" class="grid-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create_term"><input type="hidden" name="taxonomy_id" value="<?= (int)$taxonomy['id'] ?>"><label>Term name<input name="name" required></label><label>Slug<input name="slug"></label><label>Description<input name="description" maxlength="500"></label><label>Parent<select name="parent_id"><option value="">None</option><?php foreach ($taxonomies->terms((int)$taxonomy['id']) as $term): ?><option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option><?php endforeach; ?></select></label><p><button>Add term</button></p></form>
    <?php $terms = $taxonomies->terms((int)$taxonomy['id']); if ($terms !== []): ?><table><thead><tr><th>Term</th><th>Parent</th><th>Use</th><th></th></tr></thead><tbody><?php foreach ($terms as $term): ?><tr><td><?= e($term['name']) ?></td><td><?= e($term['parent_name'] ?? '') ?></td><td><?= (int)$term['use_count'] ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_term"><input type="hidden" name="id" value="<?= (int)$term['id'] ?>"><button class="danger" data-confirm="Delete this term?">Delete</button></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_taxonomy"><input type="hidden" name="id" value="<?= (int)$taxonomy['id'] ?>"><button class="danger" data-confirm="Delete this taxonomy and all of its terms?">Delete taxonomy</button></form>
</section>
<?php endforeach; ?>
<?php renderFooter();
