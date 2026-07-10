<?php
declare(strict_types=1);

/** Lightweight, deterministic content checks that complement manual review. */
final class AccessibilityChecker
{
    public function __construct(
        private ArticleRepository $articles,
        private PageRepository $pages,
        private ListingRepository $listings,
        private MediaRepository $media,
        private ContentExtensionRepository $extensions
    ) {
    }

    public function run(): array
    {
        $issues = [];
        foreach ([
            'article' => [$this->articles, '/admin/article-edit?id='],
            'page' => [$this->pages, '/admin/page-edit?id='],
            'listing' => [$this->listings, '/admin/listing-edit?id='],
        ] as $type => [$repository, $urlPrefix]) {
            foreach ($repository->listForAdmin() as $summary) {
                $entity = $repository->findById((int)$summary['id']);
                if (!$entity) {
                    continue;
                }
                $issues = array_merge($issues, $this->checkContent($type, $entity, $urlPrefix . (int)$entity['id']));
            }
        }
        foreach ($this->media->listAll() as $item) {
            if (str_starts_with((string)$item['mime_type'], 'image/') && trim((string)$item['alt_text']) === '') {
                $issues[] = $this->issue('media', (int)$item['id'], (string)$item['original_name'], 'warning', 'Image has no alt text.', '/admin/media');
            }
        }
        return $issues;
    }

    private function checkContent(string $type, array $entity, string $url): array
    {
        $issues = [];
        $id = (int)$entity['id'];
        $title = (string)$entity['title'];
        if (trim($title) === '') {
            $issues[] = $this->issue($type, $id, $title, 'error', 'Content has no title.', $url);
        }
        $lastHeading = 0;
        foreach (preg_split('/\r\n|\r|\n/', (string)$entity['body_markdown']) ?: [] as $line) {
            if (preg_match('/^(#{1,6})\s+/', $line, $match)) {
                $level = strlen($match[1]);
                if ($lastHeading !== 0 && $level > $lastHeading + 1) {
                    $issues[] = $this->issue($type, $id, $title, 'warning', 'Heading levels skip from H' . $lastHeading . ' to H' . $level . '.', $url);
                    break;
                }
                $lastHeading = $level;
            }
        }
        if (preg_match_all('/!\[\s*\]\([^)]*\)/', (string)$entity['body_markdown']) > 0) {
            $issues[] = $this->issue($type, $id, $title, 'warning', 'Markdown contains image syntax with empty alt text.', $url);
        }
        foreach ($this->extensions->links($type, $id) as $link) {
            if (trim((string)$link['label']) === '') {
                $issues[] = $this->issue($type, $id, $title, 'warning', 'External link has no descriptive label.', $url);
            }
        }
        return $issues;
    }

    private function issue(string $type, int $id, string $title, string $severity, string $message, string $url): array
    {
        return compact('type', 'id', 'title', 'severity', 'message', 'url');
    }
}
