<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

requireFeature('listings');

$currentUser = $auth->user();
$slug = (string)($_GET['slug'] ?? '');
$item = $listings->findBySlug($slug);
if (!$item || !contentIsPublic($item)) {
    http_response_code(404);
    exit('Listing not found.');
}

renderHeader($item['meta_title'] ?: $item['title'], $currentUser, [
    'canonical' => '/listings/' . $item['slug'],
    'description' => $item['meta_description'] ?: $item['summary'],
    'og_image' => !empty($item['image']) ? '/uploads/' . $item['image'] : null,
]);
?>
<article class="panel article-body">
    <?php if (!empty($item['image'])): ?>
        <img class="article-hero" src="/uploads/<?= e($item['image']) ?>" alt="<?= e($item['title']) ?>">
    <?php endif; ?>
    <?php if (!empty($item['summary'])): ?>
        <p class="lead"><?= e($item['summary']) ?></p>
    <?php endif; ?>
    <?= $item['body_html'] ?>
    <?php if (!empty($item['links'])): ?>
        <h2>Links</h2>
        <ul>
            <?php foreach ($item['links'] as $link): ?>
                <?php
                $label = trim((string)($link['label'] ?? 'Link'));
                $url = trim((string)($link['url'] ?? ''));
                if ($url === '' || !preg_match('#^https?://#i', $url)) {
                    continue;
                }
                ?>
                <li><a href="<?= e($url) ?>" rel="noopener nofollow"><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (!empty($item['tags'])): ?>
        <p class="tag-list">
            <?php foreach ($item['tags'] as $tagName): ?>
                <a href="/listings?tag=<?= e(urlencode((string)$tagName)) ?>"><?= e((string)$tagName) ?></a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
    <?php renderPublicContentExtensions('listing', (int)$item['id']); ?>
</article>
<?php renderFooter();
