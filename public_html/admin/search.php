<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireLogin();
if (!$auth->canAccessAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}

$q = trim((string)($_GET['q'] ?? ''));
$results = [];

if ($q !== '') {
    if ($auth->can('article.create') && feature('articles')) {
        foreach ($articles->searchAdmin($q) as $a) {
            $results[] = ['type' => 'Article', 'label' => $a['title'], 'url' => '/admin/article-edit?id=' . (int)$a['id'], 'meta' => $a['status']];
        }
    }
    if ($auth->can('page.create') && feature('pages')) {
        foreach ($pages->searchAdmin($q) as $p) {
            $results[] = ['type' => 'Page', 'label' => $p['title'], 'url' => '/admin/page-edit?id=' . (int)$p['id'], 'meta' => $p['status']];
        }
    }
    if ($auth->can('media.upload') && feature('media')) {
        foreach ($media->searchAdmin($q) as $m) {
            $results[] = ['type' => 'Media', 'label' => $m['original_name'], 'url' => '/uploads/' . $m['stored_name'], 'meta' => $m['mime_type']];
        }
    }
    if ($auth->can('comment.moderate') && feature('comments')) {
        foreach ($comments->searchAdmin($q) as $c) {
            $results[] = ['type' => 'Comment', 'label' => $c['author_name'] . ' on ' . $c['article_title'], 'url' => '/articles/' . $c['article_slug'] . '#comments', 'meta' => $c['status']];
        }
    }
    if ($auth->can('user.manage')) {
        foreach ($users->searchAdmin($q) as $u) {
            $results[] = ['type' => 'User', 'label' => $u['display_name'] . ' (' . $u['email'] . ')', 'url' => '/admin/users', 'meta' => $u['role_name']];
        }
    }
}

renderHeader('Admin search', $currentUser);
?>
<section class="panel">
    <form method="get" action="/admin/search" class="grid-form">
        <label>
            Search
            <input type="search" name="q" value="<?= e($q) ?>" required>
        </label>
        <button type="submit">Search</button>
    </form>
</section>

<?php if ($q !== ''): ?>
<section class="panel">
    <h2>Results (<?= (int)count($results) ?>)</h2>
    <?php if (!$results): ?>
        <p class="muted">No results, or you lack permission to view some record types.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Type</th><th>Label</th><th>Detail</th></tr></thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td><?= e($r['type']) ?></td>
                            <td><a href="<?= e($r['url']) ?>"><?= e($r['label']) ?></a></td>
                            <td><?= e($r['meta']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php renderFooter();
