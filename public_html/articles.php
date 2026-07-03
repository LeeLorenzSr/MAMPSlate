<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

requireFeature('articles');

$currentUser = $auth->user();

$perPage = max(1, (int)($config['app']['articles_per_page'] ?? 10));
$page = max(1, (int)($_GET['page'] ?? 1));

$categorySlug = isset($_GET['category']) ? (string)$_GET['category'] : null;
$tagSlug = isset($_GET['tag']) ? (string)$_GET['tag'] : null;

$category = $categorySlug ? $articles->findCategoryBySlug($categorySlug) : null;
$tag = $tagSlug ? $articles->findTagBySlug($tagSlug) : null;

$total = $articles->countPublished($categorySlug, $tagSlug);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$items = $articles->listPublished($page, $perPage, $categorySlug, $tagSlug);

$title = 'Articles';
if ($category) {
    $title = 'Category: ' . $category['name'];
} elseif ($tag) {
    $title = 'Tag: ' . $tag['name'];
}

renderHeader($title, $currentUser);
?>
<section class="panel">
    <div class="list-head">
        <h2><i class="bi bi-collection"></i> <?= e($title) ?></h2>
        <?php if ($currentUser && $auth->can('article.create')): ?>
            <a class="btn-primary" href="/admin/article-edit"><i class="bi bi-plus-lg"></i> New article</a>
        <?php endif; ?>
    </div>
    <?php if (!$items): ?>
        <p class="empty-state"><i class="bi bi-inbox"></i> No articles published yet.</p>
    <?php else: ?>
        <div class="article-list">
            <?php foreach ($items as $item): ?>
                <article class="article-card">
                    <?php $cardCover = !empty($item['cover']) ? '/uploads/' . $item['cover'] : '/assets/img/default-cover.jpg'; ?>
                    <a class="article-card-cover" href="/articles/<?= e($item['slug']) ?>">
                        <img src="<?= e($cardCover) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                    </a>
                    <div class="article-card-body">
                        <h3><a href="/articles/<?= e($item['slug']) ?>"><?= e($item['title']) ?></a></h3>
                        <?php if ($item['category_name']): ?>
                            <p class="muted"><a href="/category/<?= e($item['category_slug']) ?>"><i class="bi bi-tag"></i> <?= e($item['category_name']) ?></a></p>
                        <?php endif; ?>
                        <?php if ($item['summary']): ?>
                            <p><?= e($item['summary']) ?></p>
                        <?php endif; ?>
                        <p class="muted article-card-meta">
                            <span><i class="bi bi-person"></i> <?= e($item['author_name']) ?></span>
                            <?php if ($item['published_at']): ?>
                                <span><i class="bi bi-calendar3"></i> <?= e(date('M j, Y', strtotime($item['published_at']))) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php $query = http_build_query(array_filter(['page' => $p, 'category' => $categorySlug, 'tag' => $tagSlug])); ?>
                    <a class="<?= $p === $page ? 'current' : '' ?>" href="/articles?<?= e($query) ?>"><?= $p ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php renderFooter();
