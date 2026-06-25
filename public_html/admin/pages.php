<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('pages');

$currentUser = $auth->requireCapability('page.create');
$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['page_id'] ?? 0);
        $page = $pages->findById($id);
        if ($page) {
            $canDelete = $auth->can('page.delete.any')
                || ($auth->can('page.delete.own') && (int)$page['author_user_id'] === (int)$currentUser['id']);
            if ($canDelete) {
                $pages->deletePage($id);
                $audit->log('page.deleted', (int)$currentUser['id'], 'page', (string)$id);
                $message = 'Page deleted.';
            } else {
                $error = 'You may only delete your own pages.';
            }
        } else {
            $error = 'Page not found.';
        }
    }
}

$allPages = $pages->listForAdmin();

renderHeader('Pages', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel">
    <p><a href="/admin/page-edit">New page</a></p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allPages as $p): ?>
                    <tr>
                        <td><?= e($p['title']) ?></td>
                        <td><?= e($p['status']) ?></td>
                        <td><?= e($p['author_name']) ?></td>
                        <td><?= e($p['updated_at']) ?></td>
                        <td>
                            <a href="/admin/page-edit?id=<?= (int)$p['id'] ?>">Edit</a>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="danger" data-confirm="Delete this page?">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();
