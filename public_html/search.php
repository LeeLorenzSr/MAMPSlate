<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$currentUser = $auth->user();
$q = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$tag = trim((string)($_GET['tag'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$author = trim((string)($_GET['author'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$categories = feature('categories') ? $articles->allCategories() : [];
$results = [];

$matches = static function (array $result) use ($type, $tag, $category, $author, $from, $to): bool {
    if ($type !== '' && $result['type'] !== $type) { return false; }
    if ($category !== '' && ($result['category'] ?? '') !== $category) { return false; }
    if ($author !== '' && mb_stripos((string)($result['author'] ?? ''), $author) === false) { return false; }
    if ($tag !== '') {
        $tags = array_map('mb_strtolower', $result['tags'] ?? []);
        if (!in_array(mb_strtolower($tag), $tags, true)) { return false; }
    }
    $date = (string)($result['date'] ?? '');
    if ($from !== '' && ($date === '' || substr($date, 0, 10) < $from)) { return false; }
    if ($to !== '' && ($date === '' || substr($date, 0, 10) > $to)) { return false; }
    return true;
};

if ($q !== '') {
    if (feature('articles') && ($type === '' || $type === 'article')) {
        foreach ($articles->searchPublished($q, 50) as $item) {
            $full = $articles->findById((int)$item['id']);
            $tags = array_map(fn($entry) => $entry['name'], $articles->tagsForArticle((int)$item['id']));
            $terms = array_map(fn($entry) => $entry['name'], $contentExtensions->terms('article', (int)$item['id']));
            $candidate = ['type' => 'article', 'title' => $item['title'], 'url' => '/articles/' . $item['slug'], 'cover' => $item['cover'] ?? null, 'excerpt' => $item['summary'] ?? '', 'date' => $item['published_at'] ?? null, 'category' => $full['category_slug'] ?? '', 'author' => $full['author_name'] ?? '', 'tags' => array_merge($tags, $terms)];
            if ($matches($candidate)) { $results[] = $candidate; }
        }
    }
    if (feature('listings') && ($type === '' || $type === 'listing')) {
        foreach ($listings->searchPublished($q, 50) as $item) {
            $full = $listings->findById((int)$item['id']);
            $terms = array_map(fn($entry) => $entry['name'], $contentExtensions->terms('listing', (int)$item['id']));
            $candidate = ['type' => 'listing', 'title' => $item['title'], 'url' => '/listings/' . $item['slug'], 'cover' => $item['image'] ?? null, 'excerpt' => $item['summary'] ?? '', 'date' => $item['published_at'] ?? null, 'category' => '', 'author' => $full['owner_name'] ?? '', 'tags' => array_merge($item['tags'] ?? [], $terms)];
            if ($matches($candidate)) { $results[] = $candidate; }
        }
    }
    if (feature('pages') && ($type === '' || $type === 'page')) {
        foreach ($pages->searchPublished($q, 1, 50) as $item) {
            $full = $pages->findById((int)$item['id']);
            $terms = array_map(fn($entry) => $entry['name'], $contentExtensions->terms('page', (int)$item['id']));
            $candidate = ['type' => 'page', 'title' => $item['title'], 'url' => '/pages/' . $item['slug'], 'cover' => null, 'excerpt' => $item['summary'] ?? '', 'date' => $item['published_at'] ?? null, 'category' => '', 'author' => $full['author_name'] ?? '', 'tags' => $terms];
            if ($matches($candidate)) { $results[] = $candidate; }
        }
    }
}

usort($results, fn($left, $right) => strcmp((string)($right['date'] ?? ''), (string)($left['date'] ?? '')));
$total = count($results);
$results = array_slice($results, ($page - 1) * $perPage, $perPage);
$totalPages = max(1, (int)ceil($total / $perPage));
$query = ['q' => $q, 'type' => $type, 'tag' => $tag, 'category' => $category, 'author' => $author, 'from' => $from, 'to' => $to];

renderHeader('Search', $currentUser);
?>
<section class="panel">
    <form method="get" action="/search" class="grid-form">
        <label>Search<input type="search" name="q" value="<?= e($q) ?>" required></label>
        <label>Type<select name="type"><option value="">All types</option><?php foreach (['article','page','listing'] as $option): ?><option value="<?= e($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></label>
        <label>Tag or term<input name="tag" value="<?= e($tag) ?>"></label>
        <label>Category<select name="category"><option value="">All categories</option><?php foreach ($categories as $item): ?><option value="<?= e($item['slug']) ?>" <?= $category === $item['slug'] ? 'selected' : '' ?>><?= e($item['name']) ?></option><?php endforeach; ?></select></label>
        <label>Author or owner<input name="author" value="<?= e($author) ?>"></label>
        <label>From<input name="from" type="date" value="<?= e($from) ?>"></label>
        <label>To<input name="to" type="date" value="<?= e($to) ?>"></label>
        <button type="submit">Search</button>
    </form>
</section>

<?php if ($q !== ''): ?>
<section class="panel">
    <h2>Results for “<?= e($q) ?>” (<?= (int)$total ?>)</h2>
    <?php if (!$results): ?><p class="muted">No results found.</p><?php else: ?><div class="article-list">
        <?php foreach ($results as $result): ?>
            <?php $cover = !empty($result['cover']) ? '/uploads/' . $result['cover'] : '/assets/img/default-cover.jpg'; ?>
            <article class="article-card"><a class="article-card-cover" href="<?= e($result['url']) ?>"><img src="<?= e($cover) ?>" alt="<?= e($result['title']) ?>" loading="lazy"></a><div class="article-card-body"><h3><a href="<?= e($result['url']) ?>"><?= e($result['title']) ?></a></h3><p class="muted"><?= e(ucfirst($result['type'])) ?><?= $result['author'] ? ' · ' . e($result['author']) : '' ?></p><?php if ($result['excerpt']): ?><p><?= e(mb_strimwidth($result['excerpt'], 0, 200, '…')) ?></p><?php endif; ?><?php if ($result['date']): ?><p class="muted"><i class="bi bi-calendar3"></i> <?= e(date('M j, Y', strtotime($result['date']))) ?></p><?php endif; ?></div></article>
        <?php endforeach; ?>
    </div><?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Pagination"><?php for ($p = 1; $p <= $totalPages; $p++): ?><?php $query['page'] = $p; ?><a class="<?= $p === $page ? 'current' : '' ?>" href="/search?<?= e(http_build_query($query)) ?>"><?= $p ?></a><?php endfor; ?></nav><?php endif; ?><?php endif; ?>
</section>
<?php endif; ?>
<?php renderFooter();
