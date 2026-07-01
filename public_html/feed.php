<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireFeature('rss_feed');

$baseUrl = rtrim($config['app']['base_url'] ?? '', '/');
$appName = $config['app']['name'] ?? 'MAMPSlate CMS';
$items = $articles->listPublished(1, 20, null, null);

security_headers();
header('Content-Type: application/rss+xml; charset=utf-8');

$build = date('r');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title><?= e($appName) ?></title>
        <link><?= e($baseUrl ?: '/') ?></link>
        <description>Latest articles from <?= e($appName) ?></description>
        <language>en-us</language>
        <lastBuildDate><?= e($build) ?></lastBuildDate>
        <atom:link href="<?= e($baseUrl . '/feed') ?>" rel="self" type="application/rss+xml" />
        <?php foreach ($items as $item): ?>
            <item>
                <title><?= e($item['title']) ?></title>
                <link><?= e($baseUrl . '/articles/' . $item['slug']) ?></link>
                <guid isPermaLink="true"><?= e($baseUrl . '/articles/' . $item['slug']) ?></guid>
                <?php if (!empty($item['published_at'])): ?>
                    <pubDate><?= e(date('r', strtotime($item['published_at']))) ?></pubDate>
                <?php endif; ?>
                <?php if (!empty($item['summary'])): ?>
                    <description><?= e($item['summary']) ?></description>
                <?php endif; ?>
            </item>
        <?php endforeach; ?>
    </channel>
</rss>
