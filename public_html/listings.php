<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

requireFeature('listings');

$currentUser = $auth->user();
$page = max(1, (int)($_GET['page'] ?? 1));
$tag = trim((string)($_GET['tag'] ?? ''));
$perPage = 12;
$items = $listings->listPublished($page, $perPage, $tag !== '' ? $tag : null);
$total = $listings->countPublished($tag !== '' ? $tag : null);
$totalPages = max(1, (int)ceil($total / $perPage));

$title = $tag !== '' ? 'Listings tagged ' . $tag : 'Listings';
renderHeader($title, $currentUser, [
    'canonical' => $tag !== '' ? '/listings?tag=' . urlencode($tag) : '/listings',
    'description' => 'Browse published directory listings.',
]);
?>
<section class="panel">
    <div class="article-list">
        <?php foreach ($items as $item): ?>
            <?php $cover = !empty($item['image']) ? '/uploads/' . $item['image'] : '/assets/img/default-cover.jpg'; ?>
            <article class="article-card">
                <a class="article-card-cover" href="/listings/<?= e($item['slug']) ?>">
                    <img src="<?= e($cover) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                </a>
                <div class="article-card-body">
                    <h2><a href="/listings/<?= e($item['slug']) ?>"><?= e($item['title']) ?></a></h2>
                    <?php if (!empty($item['summary'])): ?>
                        <p><?= e($item['summary']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item['tags'])): ?>
                        <p class="tag-list">
                            <?php foreach ($item['tags'] as $tagName): ?>
                                <a href="/listings?tag=<?= e(urlencode((string)$tagName)) ?>"><?= e((string)$tagName) ?></a>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (!$items): ?>
        <p class="muted">No listings have been published yet.</p>
    <?php endif; ?>
    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a class="<?= $p === $page ? 'current' : '' ?>" href="/listings?page=<?= $p ?><?= $tag !== '' ? '&tag=' . e(urlencode($tag)) : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>
<?php renderFooter();
