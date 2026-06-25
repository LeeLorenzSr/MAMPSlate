<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$currentUser = $auth->user();

$q = trim((string)($_GET['q'] ?? ''));
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$results = [];
$total = 0;

if ($q !== '') {
    if (feature('articles')) {
        foreach ($articles->searchPublished($q, 50) as $a) {
            $results[] = [
                'type' => 'article',
                'title' => $a['title'],
                'url' => '/articles/' . $a['slug'],
                'excerpt' => $a['summary'] ?? '',
                'date' => $a['published_at'] ?? null,
            ];
        }
    }
    if (feature('pages')) {
        foreach ($pages->searchPublished($q, 1, 50) as $p) {
            $results[] = [
                'type' => 'page',
                'title' => $p['title'],
                'url' => '/pages/' . $p['slug'],
                'excerpt' => $p['summary'] ?? '',
                'date' => $p['published_at'] ?? null,
            ];
        }
    }

    // Sort by date desc.
    usort($results, fn($x, $y) => strcmp((string)($y['date'] ?? ''), (string)($x['date'] ?? '')));
    $total = count($results);
    $offset = ($page - 1) * $perPage;
    $results = array_slice($results, $offset, $perPage);
}

$totalPages = max(1, (int)ceil($total / $perPage));

renderHeader('Search', $currentUser);
?>
<section class="panel">
    <form method="get" action="/search" class="grid-form">
        <label>
            Search
            <input type="search" name="q" value="<?= e($q) ?>" required>
        </label>
        <button type="submit">Search</button>
    </form>
</section>

<?php if ($q !== ''): ?>
<section class="panel">
    <h2>Results for “<?= e($q) ?>” (<?= (int)$total ?>)</h2>
    <?php if (!$results): ?>
        <p class="muted">No results found.</p>
    <?php else: ?>
        <div class="article-list">
            <?php foreach ($results as $r): ?>
                <article class="article-card">
                    <div class="article-card-body">
                        <p class="muted"><?= e(ucfirst($r['type'])) ?></p>
                        <h3><a href="<?= e($r['url']) ?>"><?= e($r['title']) ?></a></h3>
                        <?php if ($r['excerpt']): ?>
                            <p><?= e(mb_strimwidth($r['excerpt'], 0, 200, '…')) ?></p>
                        <?php endif; ?>
                        <?php if ($r['date']): ?>
                            <p class="muted"><?= e(date('M j, Y', strtotime($r['date']))) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a class="<?= $p === $page ? 'current' : '' ?>" href="/search?q=<?= e(urlencode($q)) ?>&page=<?= $p ?>"><?= $p ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php renderFooter();
