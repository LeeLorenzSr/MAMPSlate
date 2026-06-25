<?php
declare(strict_types=1);

final class PageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pages.*, users.display_name AS author_name
             FROM pages
             INNER JOIN users ON users.id = pages.author_user_id
             WHERE pages.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pages.*, users.display_name AS author_name
             FROM pages
             INNER JOIN users ON users.id = pages.author_user_id
             WHERE pages.slug = :slug'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM pages WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function createPage(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pages
                (title, slug, summary, body_markdown, body_html, status, author_user_id,
                 cover_media_id, meta_title, meta_description, published_at)
             VALUES
                (:title, :slug, :summary, :body_markdown, :body_html, :status, :author_user_id,
                 :cover_media_id, :meta_title, :meta_description, :published_at)'
        );
        $this->bindExecute($stmt, $data);

        return (int)$this->pdo->lastInsertId();
    }

    public function updatePage(int $id, array $data): void
    {
        $data['id'] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE pages
             SET title = :title,
                 slug = :slug,
                 summary = :summary,
                 body_markdown = :body_markdown,
                 body_html = :body_html,
                 status = :status,
                 cover_media_id = :cover_media_id,
                 meta_title = :meta_title,
                 meta_description = :meta_description,
                 published_at = :published_at
             WHERE id = :id'
        );
        $this->bindExecute($stmt, $data);
    }

    public function deletePage(int $id): void
    {
        $this->pdo->prepare('DELETE FROM pages WHERE id = :id')->execute(['id' => $id]);
    }

    public function listForAdmin(): array
    {
        return $this->pdo->query(
            'SELECT pages.id, pages.title, pages.slug, pages.status, pages.published_at, pages.updated_at,
                    users.display_name AS author_name
             FROM pages
             INNER JOIN users ON users.id = pages.author_user_id
             ORDER BY pages.updated_at DESC'
        )->fetchAll();
    }

    public function listPublished(int $page, int $perPage): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pages.id, pages.title, pages.slug, pages.summary, pages.published_at, pages.updated_at
             FROM pages
             WHERE pages.status = :status
               AND pages.published_at IS NOT NULL
               AND pages.published_at <= CURRENT_TIMESTAMP
             ORDER BY pages.published_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('status', 'published');
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countByStatus(): array
    {
        $rows = $this->pdo->query(
            'SELECT status, COUNT(*) AS n FROM pages GROUP BY status'
        )->fetchAll();
        $out = ['draft' => 0, 'published' => 0, 'archived' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['n'];
        }
        return $out;
    }

    public function countPublished(): int
    {
        return (int)$this->pdo->query(
            "SELECT COUNT(*) FROM pages
             WHERE status = 'published'
               AND published_at IS NOT NULL
               AND published_at <= CURRENT_TIMESTAMP"
        )->fetchColumn();
    }

    public function searchPublished(string $query, int $page, int $perPage): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT pages.id, pages.title, pages.slug, pages.summary, pages.published_at
             FROM pages
             WHERE pages.status = :status
               AND pages.published_at IS NOT NULL
               AND pages.published_at <= CURRENT_TIMESTAMP
               AND (pages.title LIKE :q OR pages.summary LIKE :q OR pages.body_markdown LIKE :q)
             ORDER BY pages.published_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('status', 'published');
        $stmt->bindValue('q', $like);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function searchAdmin(string $query): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT id, title, slug, status, updated_at
             FROM pages
             WHERE title LIKE :q OR slug LIKE :q OR body_markdown LIKE :q
             ORDER BY updated_at DESC LIMIT 50'
        );
        $stmt->execute(['q' => $like]);

        return $stmt->fetchAll();
    }

    private function bindExecute(PDOStatement $stmt, array $data): void
    {
        $stmt->execute([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'summary' => $data['summary'] ?? null,
            'body_markdown' => $data['body_markdown'],
            'body_html' => $data['body_html'],
            'status' => $data['status'] ?? 'draft',
            'author_user_id' => (int)$data['author_user_id'],
            'cover_media_id' => !empty($data['cover_media_id']) ? (int)$data['cover_media_id'] : null,
            'meta_title' => $data['meta_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'published_at' => $data['published_at'] ?? null,
            'id' => $data['id'] ?? null,
        ]);
    }
}
