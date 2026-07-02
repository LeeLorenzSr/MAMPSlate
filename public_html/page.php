<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

requireFeature('pages');

$currentUser = $auth->user();

$slug = (string)($_GET['slug'] ?? '');
$page = $slug !== '' ? $pages->findBySlug($slug) : null;

if (!$page || $page['status'] !== 'published'
    || $page['published_at'] === null
    || strtotime($page['published_at']) > time()) {
    http_response_code(404);
    renderHeader('Not found', $currentUser);
    echo '<p>The page you were looking for is not available.</p>';
    renderFooter();
    exit;
}

$cover = null;
if ($page['cover_media_id']) {
    $cover = $media->findById((int)$page['cover_media_id']);
}

$seoDescription = $page['meta_description'] !== '' ? $page['meta_description'] : ($page['summary'] ?? '');
$ogImage = $cover ? (setting('app.base_url') . '/uploads/' . $cover['stored_name']) : null;
$coverSrc = $cover ? ('/uploads/' . $cover['stored_name']) : '/assets/img/default-cover.jpg';
$coverAlt = $cover ? ($cover['alt_text'] ?: $page['title']) : $page['title'];
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'headline' => $page['title'],
    'dateModified' => date('c', strtotime($page['updated_at'])),
    'description' => $seoDescription,
];
if ($ogImage) {
    $jsonLd['image'] = $ogImage;
}

renderHeader(
    $page['meta_title'] !== '' ? $page['meta_title'] : $page['title'],
    $currentUser,
    [
        'description' => $seoDescription,
        'canonical' => '/pages/' . $page['slug'],
        'og_type' => 'article',
        'og_image' => $ogImage,
        'jsonLd' => $jsonLd,
    ]
);
?>
<article class="article-view">
    <h2><?= e($page['title']) ?></h2>
    <img class="article-cover" src="<?= e($coverSrc) ?>" alt="<?= e($coverAlt) ?>">
    <div class="article-body">
        <?= $page['body_html'] ?>
    </div>
</article>
<?php renderFooter();
