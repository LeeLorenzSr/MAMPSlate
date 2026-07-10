<?php
declare(strict_types=1);

/** Single sitemap registration point for core content and optional modules. */
final class SitemapRegistry
{
    public function __construct(
        private ArticleRepository $articles,
        private PageRepository $pages,
        private ListingRepository $listings,
        private ModuleRegistry $modules
    ) {
    }

    public function entries(string $baseUrl): array
    {
        $entries = [];
        if (feature('articles')) {
            $entries[] = ['loc' => $baseUrl . '/articles'];
            foreach ($this->articles->listPublished(1, 10000) as $article) {
                $entries[] = ['loc' => $baseUrl . '/articles/' . rawurlencode($article['slug']), 'lastmod' => $article['updated_at']];
            }
        }
        if (feature('pages')) {
            foreach ($this->pages->listPublished(1, 10000) as $page) {
                $entries[] = ['loc' => $baseUrl . '/pages/' . rawurlencode($page['slug']), 'lastmod' => $page['updated_at']];
            }
        }
        if (feature('listings')) {
            $entries[] = ['loc' => $baseUrl . '/listings'];
            foreach ($this->listings->listPublished(1, 10000) as $listing) {
                $entries[] = ['loc' => $baseUrl . '/listings/' . rawurlencode($listing['slug']), 'lastmod' => $listing['updated_at']];
            }
        }
        return array_merge($entries, $this->modules->sitemapEntries($baseUrl));
    }
}
