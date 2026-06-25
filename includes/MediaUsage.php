<?php
declare(strict_types=1);

/**
 * Tracks where a media item is referenced so it is not deleted while in use.
 *
 * Detects: article cover images, page cover images, and Markdown image
 * references (`![alt](/uploads/<stored_name>)`) in article/page bodies.
 * Profile avatars are stored outside the media table and are not tracked here.
 */
final class MediaUsage
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<int, array{type: string, count: int}>
     */
    public function usages(int $mediaId, string $storedName): array
    {
        $out = [];

        $n = $this->count('SELECT COUNT(*) FROM articles WHERE cover_media_id = :id', $mediaId);
        if ($n > 0) {
            $out[] = ['type' => 'article cover', 'count' => $n];
        }

        $n = $this->count('SELECT COUNT(*) FROM pages WHERE cover_media_id = :id', $mediaId);
        if ($n > 0) {
            $out[] = ['type' => 'page cover', 'count' => $n];
        }

        $like = '%' . $this->escapeLike($storedName) . '%';
        $n = $this->countLike('SELECT COUNT(*) FROM articles WHERE body_markdown LIKE :q', $like);
        if ($n > 0) {
            $out[] = ['type' => 'article body', 'count' => $n];
        }

        $n = $this->countLike('SELECT COUNT(*) FROM pages WHERE body_markdown LIKE :q', $like);
        if ($n > 0) {
            $out[] = ['type' => 'page body', 'count' => $n];
        }

        return $out;
    }

    public function inUse(int $mediaId, string $storedName): bool
    {
        return $this->usages($mediaId, $storedName) !== [];
    }

    /**
     * Media rows that are not referenced anywhere.
     */
    public function orphanRows(): array
    {
        $rows = $this->pdo->query('SELECT id, stored_name, original_name, mime_type, created_at FROM media ORDER BY created_at DESC')->fetchAll();
        $orphans = [];
        foreach ($rows as $row) {
            if (!$this->inUse((int)$row['id'], $row['stored_name'])) {
                $orphans[] = $row;
            }
        }
        return $orphans;
    }

    /**
     * Files on disk under the uploads root that have no media row.
     * Excludes .htaccess and .gitkeep.
     *
     * @return string[] relative paths
     */
    public function orphanDiskFiles(string $uploadsRoot): array
    {
        $known = $this->pdo->query('SELECT stored_name FROM media')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $known = array_flip($known);

        $orphans = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($uploadsRoot))), '/');
            if (in_array(basename($rel), ['.htaccess', '.gitkeep'], true)) {
                continue;
            }
            if (!isset($known[$rel])) {
                $orphans[] = $rel;
            }
        }
        sort($orphans);
        return $orphans;
    }

    private function count(string $sql, int $id): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn();
    }

    private function countLike(string $sql, string $like): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':q' => $like]);
        return (int)$stmt->fetchColumn();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
