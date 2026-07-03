<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('articles');

$currentUser = $auth->requireCapability('article.create');
$canPublish = $auth->can('article.publish');
$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['article_id'] ?? 0);
        $article = $articles->findById($id);
        if ($article) {
            $canDelete = $auth->can('article.delete.any')
                || ($auth->can('article.delete.own') && (int)$article['author_user_id'] === (int)$currentUser['id']);
            if ($canDelete) {
                $articles->deleteArticle($id);
                $audit->log('article.deleted', (int)$currentUser['id'], 'article', (string)$id);
                $message = 'Article deleted.';
            } else {
                $error = 'You may only delete your own articles.';
            }
        } else {
            $error = 'Article not found.';
        }
    }

    if ($action === 'publish') {
        $id = (int)($_POST['article_id'] ?? 0);
        $article = $articles->findById($id);
        if (!$article) {
            $error = 'Article not found.';
        } elseif (!$canPublish) {
            $error = 'You may not publish articles.';
        } elseif ($article['status'] !== 'published') {
            $articles->publish($id);
            $audit->log('article.published', (int)$currentUser['id'], 'article', (string)$id, ['from' => $article['status']]);
            $message = 'Article published.';
        } else {
            $message = 'Article was already published.';
        }
    }
}

$allArticles = $articles->listForAdmin();

renderHeader('Articles', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel">
    <p><a href="/admin/article-edit">New article</a></p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allArticles as $a): ?>
                    <?php
                    $canDeleteRow = $auth->can('article.delete.any')
                        || ($auth->can('article.delete.own') && (int)$a['author_user_id'] === (int)$currentUser['id']);
                    $titleUrl = $a['status'] === 'published'
                        ? '/articles/' . $a['slug']
                        : '/admin/article-edit?id=' . (int)$a['id'];
                    ?>
                    <tr>
                        <td><a href="<?= e($titleUrl) ?>"><?= e($a['title']) ?></a></td>
                        <td><?= e($a['status']) ?></td>
                        <td><?= e($a['author_name']) ?></td>
                        <td><?= e($a['category_name'] ?: '—') ?></td>
                        <td><?= e($a['updated_at']) ?></td>
                        <td>
                            <div class="action-bar">
                                <a class="icon-action" href="/admin/article-edit?id=<?= (int)$a['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php if ($a['status'] !== 'published' && $canPublish): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="publish">
                                        <input type="hidden" name="article_id" value="<?= (int)$a['id'] ?>">
                                        <button type="submit" class="icon-action" title="Publish" data-confirm="Publish this article?"><i class="bi bi-send"></i></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canDeleteRow): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="article_id" value="<?= (int)$a['id'] ?>">
                                        <button type="submit" class="icon-action danger" title="Delete" data-confirm="Delete this article?"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();
