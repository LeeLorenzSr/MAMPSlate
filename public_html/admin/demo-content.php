<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('demo.manage');
$message = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $created = [];
    if (feature('listings') && !$listings->slugExists('example-directory-listing')) {
        $id = $listings->create([
            'title' => 'Example Directory Listing',
            'slug' => 'example-directory-listing',
            'summary' => 'A generic profile/listing showing owner, tags, links, and Markdown body.',
            'body_markdown' => "Use listings for profiles, products, venues, organizations, or any catalog item.\n\nCustomize fields in code when a project needs a vertical-specific shape.",
            'status' => 'published',
            'owner_user_id' => (int)$currentUser['id'],
            'tags' => ['example', 'starter'],
            'links' => [['label' => 'Project website', 'url' => 'https://example.com']],
            'published_at' => date('Y-m-d H:i:s'),
        ]);
        $created[] = 'listing #' . $id;
    }
    if (feature('pages') && !$pages->slugExists('starter-example')) {
        $id = $pages->createPage([
            'title' => 'Starter Example',
            'slug' => 'starter-example',
            'summary' => 'A demo page for new installs.',
            'body_markdown' => 'This page demonstrates the editable page subsystem.',
            'status' => 'published',
            'author_user_id' => (int)$currentUser['id'],
            'cover_media_id' => null,
            'meta_title' => '',
            'meta_description' => '',
            'published_at' => date('Y-m-d H:i:s'),
        ]);
        $created[] = 'page #' . $id;
    }
    if (feature('articles') && !$articles->slugExists('starter-walkthrough')) {
        $id = $articles->createArticle([
            'title' => 'Starter Walkthrough',
            'slug' => 'starter-walkthrough',
            'summary' => 'A demo article showing categories, tags, publishing, and search.',
            'body_markdown' => 'This article gives AI agents and new operators an immediate content example to inspect.',
            'status' => 'published',
            'author_user_id' => (int)$currentUser['id'],
            'category_id' => null,
            'cover_media_id' => null,
            'meta_title' => '',
            'meta_description' => '',
            'published_at' => date('Y-m-d H:i:s'),
        ]);
        $articles->syncTags($id, ['starter', 'demo']);
        $created[] = 'article #' . $id;
    }
    $audit->log('demo.seeded', (int)$currentUser['id'], 'demo_content', null, ['created' => implode(', ', $created)]);
    $message = $created ? ('Created: ' . implode(', ', $created)) : 'Demo content already exists.';
}

renderHeader('Demo Content', $currentUser);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?>
<section class="panel">
    <h2>Seed demo content</h2>
    <p class="muted">Adds a small published page, article, and listing if they do not already exist.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <button type="submit" data-confirm="Seed optional demo content now?">Seed demo content</button>
    </form>
</section>
<?php renderFooter();
