<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('collections');
$currentUser = $auth->requireCapability('collection.manage');
$message = null;
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $collections->create(['name' => $_POST['name'] ?? '', 'slug' => $_POST['slug'] ?? '', 'description' => $_POST['description'] ?? '', 'is_public' => isset($_POST['is_public'])]);
            $message = 'Collection created.';
        } elseif ($action === 'save_items') {
            $items = [];
            foreach (preg_split('/\r\n|\r|\n/', (string)($_POST['items'] ?? '')) ?: [] as $line) {
                [$type, $id] = array_pad(explode(':', trim($line), 2), 2, '');
                $items[] = ['entity_type' => $type, 'entity_id' => (int)$id];
            }
            $collections->replaceItems((int)($_POST['id'] ?? 0), $items);
            $message = 'Collection items saved.';
        } elseif ($action === 'delete') {
            $collections->delete((int)($_POST['id'] ?? 0));
            $message = 'Collection deleted.';
        }
        $audit->log('collection.updated', (int)$currentUser['id'], 'collection');
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$allCollections = $collections->all();
$editId = (int)($_GET['id'] ?? 0);
renderHeader('Collections', $currentUser);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?><?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<section class="panel"><h2>New collection</h2><form method="post" class="grid-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create"><label>Name<input name="name" required placeholder="Featured"></label><label>Slug<input name="slug"></label><label>Description<input name="description" maxlength="500"></label><label class="inline"><input name="is_public" type="checkbox" checked> Public</label><p><button>Create collection</button></p></form></section>
<section class="panel"><h2>Collections</h2><?php if ($allCollections === []): ?><p class="muted">No collections yet.</p><?php else: ?><table><thead><tr><th>Name</th><th>Items</th><th>Visibility</th><th></th></tr></thead><tbody><?php foreach ($allCollections as $collection): ?><tr><td><a href="/admin/collections?id=<?= (int)$collection['id'] ?>"><?= e($collection['name']) ?></a></td><td><?= (int)$collection['item_count'] ?></td><td><?= !empty($collection['is_public']) ? 'Public' : 'Private' ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$collection['id'] ?>"><button class="danger" data-confirm="Delete this collection?">Delete</button></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<?php if ($editId): $editing = array_values(array_filter($allCollections, fn($collection) => (int)$collection['id'] === $editId))[0] ?? null; if ($editing): ?>
<section class="panel"><h2>Items: <?= e($editing['name']) ?></h2><p class="muted">One per line: <code>article:12</code>, <code>page:4</code>, or <code>listing:9</code>. Order is preserved.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="save_items"><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><textarea name="items" rows="8"><?php foreach ($collections->items((int)$editing['id']) as $item) { echo e($item['entity_type'] . ':' . $item['entity_id']) . "\n"; } ?></textarea><button>Save items</button></form></section>
<?php endif; endif; ?>
<?php renderFooter();
