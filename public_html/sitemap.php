<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireFeature('seo_sitemap');

$baseUrl = rtrim($config['app']['base_url'] ?? '', '/');
$categories = feature('categories') ? $articles->allCategories() : [];
$entries = $sitemapRegistry->entries($baseUrl);

security_headers();
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= e($baseUrl ?: '/') ?></loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <?php foreach ($categories as $cat): ?>
        <?php if (empty($cat['slug'])) { continue; } ?>
        <url>
            <loc><?= e($baseUrl . '/category/' . $cat['slug']) ?></loc>
            <changefreq>daily</changefreq>
            <priority>0.6</priority>
        </url>
    <?php endforeach; ?>
    <?php foreach ($entries as $item): ?>
        <url>
            <loc><?= e($item['loc']) ?></loc>
            <?php $lastmod = $item['lastmod'] ?? null; ?>
            <?php if ($lastmod): ?>
                <lastmod><?= e(date('Y-m-d', strtotime($lastmod))) ?></lastmod>
            <?php endif; ?>
            <changefreq>weekly</changefreq>
            <priority>0.7</priority>
        </url>
    <?php endforeach; ?>
</urlset>
