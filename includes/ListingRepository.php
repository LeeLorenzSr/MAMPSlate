<?php
declare(strict_types=1);

final class ListingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT listings.*, users.display_name AS owner_name, media.stored_name AS image
             FROM listings
             LEFT JOIN users ON users.id = listings.owner_user_id
             LEFT JOIN media ON media.id = listings.image_media_id
             WHERE listings.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->decodeRow($row) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT listings.*, users.display_name AS owner_name, media.stored_name AS image
             FROM listings
             LEFT JOIN users ON users.id = listings.owner_user_id
             LEFT JOIN media ON media.id = listings.image_media_id
             WHERE listings.slug = :slug'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ? $this->decodeRow($row) : null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM listings WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO listings
                (title, slug, summary, body_markdown, body_html, image_media_id,
                 links_json, tags_json, owner_user_id, status, meta_title,
                 meta_description, published_at)
             VALUES
                (:title, :slug, :summary, :body_markdown, :body_html, :image_media_id,
                 :links_json, :tags_json, :owner_user_id, :status, :meta_title,
                 :meta_description, :published_at)'
        );
        $this->executeWrite($stmt, $data);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE listings
             SET title = :title,
                 slug = :slug,
                 summary = :summary,
                 body_markdown = :body_markdown,
                 body_html = :body_html,
                 image_media_id = :image_media_id,
                 links_json = :links_json,
                 tags_json = :tags_json,
                 owner_user_id = :owner_user_id,
                 status = :status,
                 meta_title = :meta_title,
                 meta_description = :meta_description,
                 published_at = :published_at
             WHERE id = :id'
        );
        $this->executeWrite($stmt, $data);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM listings WHERE id = :id')->execute(['id' => $id]);
    }

    public function publish(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE listings
             SET status = 'published',
                 published_at = COALESCE(published_at, CURRENT_TIMESTAMP)
             WHERE id = :id"
        )->execute(['id' => $id]);
    }

    public function listForAdmin(): array
    {
        $rows = $this->pdo->query(
            'SELECT listings.id, listings.title, listings.slug, listings.status,
                    listings.published_at, listings.updated_at, users.display_name AS owner_name
             FROM listings
             LEFT JOIN users ON users.id = listings.owner_user_id
             ORDER BY listings.updated_at DESC'
        )->fetchAll();

        return array_map([$this, 'decodeRow'], $rows);
    }

    public function listPublished(int $page, int $perPage, ?string $tag = null): array
    {
        $where = [
            'listings.status = :status',
            'listings.published_at IS NOT NULL',
            'listings.published_at <= CURRENT_TIMESTAMP',
        ];
        $params = ['status' => 'published'];
        if ($tag !== null && $tag !== '') {
            $where[] = 'listings.tags_json LIKE :tag';
            $params['tag'] = '%"' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $tag) . '"%';
        }

        $stmt = $this->pdo->prepare(
            'SELECT listings.id, listings.title, listings.slug, listings.summary,
                    listings.tags_json, listings.published_at, listings.updated_at,
                    media.stored_name AS image
             FROM listings
             LEFT JOIN media ON media.id = listings.image_media_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY listings.published_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return array_map([$this, 'decodeRow'], $stmt->fetchAll());
    }

    public function countPublished(?string $tag = null): int
    {
        $where = [
            'status = :status',
            'published_at IS NOT NULL',
            'published_at <= CURRENT_TIMESTAMP',
        ];
        $params = ['status' => 'published'];
        if ($tag !== null && $tag !== '') {
            $where[] = 'tags_json LIKE :tag';
            $params['tag'] = '%"' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $tag) . '"%';
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM listings WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function countByStatus(): array
    {
        $rows = $this->pdo->query('SELECT status, COUNT(*) AS n FROM listings GROUP BY status')->fetchAll();
        $out = ['draft' => 0, 'published' => 0, 'archived' => 0];
        foreach ($rows as $row) {
            $out[$row['status']] = (int)$row['n'];
        }
        return $out;
    }

    public function searchPublished(string $query, int $limit = 50): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT listings.id, listings.title, listings.slug, listings.summary,
                    listings.tags_json, listings.published_at, media.stored_name AS image
             FROM listings
             LEFT JOIN media ON media.id = listings.image_media_id
             WHERE listings.status = :status
               AND listings.published_at IS NOT NULL
               AND listings.published_at <= CURRENT_TIMESTAMP
               AND (listings.title LIKE :q1 OR listings.summary LIKE :q2 OR listings.body_markdown LIKE :q3 OR listings.tags_json LIKE :q4)
             ORDER BY listings.published_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('status', 'published');
        $stmt->bindValue('q1', $like);
        $stmt->bindValue('q2', $like);
        $stmt->bindValue('q3', $like);
        $stmt->bindValue('q4', $like);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'decodeRow'], $stmt->fetchAll());
    }

    public function searchAdmin(string $query): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT id, title, slug, status, updated_at
             FROM listings
             WHERE title LIKE :q1 OR slug LIKE :q2 OR body_markdown LIKE :q3 OR tags_json LIKE :q4
             ORDER BY updated_at DESC LIMIT 50'
        );
        $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]);

        return array_map([$this, 'decodeRow'], $stmt->fetchAll());
    }

    private function executeWrite(PDOStatement $stmt, array $data): void
    {
        $params = [
            'title' => trim((string)$data['title']),
            'slug' => (string)$data['slug'],
            'summary' => trim((string)($data['summary'] ?? '')),
            'body_markdown' => (string)$data['body_markdown'],
            'body_html' => (new MarkdownRenderer())->render((string)$data['body_markdown']),
            'image_media_id' => !empty($data['image_media_id']) ? (int)$data['image_media_id'] : null,
            'links_json' => json_encode($this->normalizeLinks($data['links'] ?? []), JSON_UNESCAPED_SLASHES) ?: '[]',
            'tags_json' => json_encode($this->normalizeTags($data['tags'] ?? []), JSON_UNESCAPED_SLASHES) ?: '[]',
            'owner_user_id' => !empty($data['owner_user_id']) ? (int)$data['owner_user_id'] : null,
            'status' => in_array(($data['status'] ?? 'draft'), ['draft', 'published', 'archived'], true) ? ($data['status'] ?? 'draft') : 'draft',
            'meta_title' => trim((string)($data['meta_title'] ?? '')),
            'meta_description' => trim((string)($data['meta_description'] ?? '')),
            'published_at' => $data['published_at'] ?? null,
        ];
        if (array_key_exists('id', $data)) {
            $params['id'] = (int)$data['id'];
        }
        $stmt->execute($params);
    }

    private function decodeRow(array $row): array
    {
        $row['links'] = $this->decodeJsonList($row['links_json'] ?? null);
        $row['tags'] = $this->decodeJsonList($row['tags_json'] ?? null);
        return $row;
    }

    private function decodeJsonList(?string $json): array
    {
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeLinks(array $links): array
    {
        $out = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $label = trim((string)($link['label'] ?? 'Link'));
            $url = trim((string)($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $out[] = ['label' => $label !== '' ? $label : 'Link', 'url' => $url];
        }
        return $out;
    }

    private function normalizeTags(array $tags): array
    {
        $out = [];
        foreach ($tags as $tag) {
            $tag = trim((string)$tag);
            if ($tag !== '') {
                $out[mb_strtolower($tag)] = $tag;
            }
        }
        return array_values($out);
    }
}
