<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('menu.manage');
$message = null;
$error = null;

$allMenus = $menus->allMenus();
$location = $_GET['menu'] ?? 'header';
if (!in_array($location, ['header', 'footer'], true)) {
    $location = 'header';
}
$currentMenu = $menus->menuByLocation($location);

$pageOptions = feature('pages') ? $pages->listForAdmin() : [];
$categoryOptions = $articles->allCategories();
$tagOptions = $articles->allTags();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create' && $currentMenu) {
            $label = trim((string)($_POST['label'] ?? ''));
            $type = (string)($_POST['linked_type'] ?? 'custom');
            $url = '';

            if ($label === '') {
                throw new RuntimeException('Label is required.');
            }

            switch ($type) {
                case 'page':
                    $p = $pages->findById((int)($_POST['page_id'] ?? 0));
                    if (!$p) {
                        throw new RuntimeException('Select a valid page.');
                    }
                    $url = '/pages/' . $p['slug'];
                    break;
                case 'category':
                    $cat = findInList($categoryOptions, (int)($_POST['category_id'] ?? 0));
                    if (!$cat) {
                        throw new RuntimeException('Select a valid category.');
                    }
                    $url = '/category/' . $cat['slug'];
                    break;
                case 'tag':
                    $tag = findInList($tagOptions, (int)($_POST['tag_id'] ?? 0));
                    if (!$tag) {
                        throw new RuntimeException('Select a valid tag.');
                    }
                    $url = '/tag/' . $tag['slug'];
                    break;
                default:
                    $type = 'custom';
                    $url = (string)($_POST['url'] ?? '');
                    break;
            }

            $menus->createItem(
                (int)$currentMenu['id'],
                $label,
                $url,
                $type,
                $type === 'custom' ? null : (int)($_POST[$type . '_id'] ?? 0),
                null,
                (int)($_POST['sort_order'] ?? 0),
                isset($_POST['is_active'])
            );
            $audit->log('menu.item.created', (int)$currentUser['id'], 'menu_item', null, ['menu' => $location]);
            $message = 'Menu item added.';
        }

        if ($action === 'update') {
            $menus->updateItem(
                (int)($_POST['item_id'] ?? 0),
                (string)($_POST['label'] ?? ''),
                (string)($_POST['url'] ?? ''),
                null,
                (int)($_POST['sort_order'] ?? 0),
                isset($_POST['is_active'])
            );
            $message = 'Menu item updated.';
        }

        if ($action === 'delete') {
            $menus->deleteItem((int)($_POST['item_id'] ?? 0));
            $audit->log('menu.item.deleted', (int)$currentUser['id'], 'menu_item', (string)($_POST['item_id'] ?? 0));
            $message = 'Menu item removed.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$items = $currentMenu ? $menus->itemsForMenu((int)$currentMenu['id']) : [];

renderHeader('Menus', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel">
    <h2>Menus</h2>
    <p>
        Menu:
        <a href="/admin/menus?menu=header" class="<?= $location === 'header' ? 'current' : '' ?>">Header</a> |
        <a href="/admin/menus?menu=footer" class="<?= $location === 'footer' ? 'current' : '' ?>">Footer</a>
    </p>

    <h3>Add item to <?= e($location) ?> menu</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <label>Label
            <input type="text" name="label" required maxlength="120">
        </label>
        <label>Link type
            <select name="linked_type" id="menu-link-type">
                <option value="custom">Custom URL</option>
                <?php if (feature('pages')): ?><option value="page">Page</option><?php endif; ?>
                <option value="category">Category</option>
                <option value="tag">Tag</option>
            </select>
        </label>
        <label>Custom URL
            <input type="text" name="url" placeholder="/path or https://…">
        </label>
        <?php if (feature('pages')): ?>
        <label>Page
            <select name="page_id">
                <option value="0">— Select page —</option>
                <?php foreach ($pageOptions as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= e($p['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label>Category
            <select name="category_id">
                <option value="0">— Select category —</option>
                <?php foreach ($categoryOptions as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tag
            <select name="tag_id">
                <option value="0">— Select tag —</option>
                <?php foreach ($tagOptions as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Sort order
            <input type="number" name="sort_order" value="0">
        </label>
        <label class="inline">
            <input type="checkbox" name="is_active" checked> Active
        </label>
        <button type="submit">Add item</button>
    </form>
</section>

<section class="panel">
    <h2><?= e(ucfirst($location)) ?> menu items</h2>
    <?php if (!$items): ?>
        <p class="muted">No items yet.</p>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Label</th>
                    <th>URL</th>
                    <th>Sort</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <form method="post" id="menu-item-<?= (int)$item['id'] ?>"></form>
                        <td><input form="menu-item-<?= (int)$item['id'] ?>" type="text" name="label" value="<?= e($item['label']) ?>" required></td>
                        <td><input form="menu-item-<?= (int)$item['id'] ?>" type="text" name="url" value="<?= e($item['url']) ?>"></td>
                        <td><input form="menu-item-<?= (int)$item['id'] ?>" type="number" name="sort_order" value="<?= (int)$item['sort_order'] ?>" style="width:80px"></td>
                        <td><label class="inline"><input form="menu-item-<?= (int)$item['id'] ?>" type="checkbox" name="is_active" <?= (bool)$item['is_active'] ? 'checked' : '' ?>></label></td>
                        <td>
                            <input form="menu-item-<?= (int)$item['id'] ?>" type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input form="menu-item-<?= (int)$item['id'] ?>" type="hidden" name="action" value="update">
                            <input form="menu-item-<?= (int)$item['id'] ?>" type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                            <button form="menu-item-<?= (int)$item['id'] ?>" type="submit">Save</button>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="danger" data-confirm="Remove this menu item?">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();

function findInList(array $list, int $id): ?array
{
    foreach ($list as $item) {
        if ((int)$item['id'] === $id) {
            return $item;
        }
    }
    return null;
}
