<?php
declare(strict_types=1);

final class ArticleRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    // ---- Categories ----

    public function allCategories(): array
    {
        return $this->pdo->query(
            'SELECT c.*, (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id) AS article_count
             FROM categories c
             ORDER BY c.name'
        )->fetchAll();
    }

    public function findCategoryBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createCategory(string $name, string $slug): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (name, slug) VALUES (:name, :slug)'
        );
        $stmt->execute(['name' => trim($name), 'slug' => $slug]);

        return (int)$this->pdo->lastInsertId();
    }

    // ---- Tags ----

    public function allTags(): array
    {
        return $this->pdo->query(
            'SELECT t.*, (SELECT COUNT(*) FROM article_tags at WHERE at.tag_id = t.id) AS article_count
             FROM tags t
             ORDER BY t.name'
        )->fetchAll();
    }

    public function findTagBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tags WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function tagsForArticle(int $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tags.id, tags.name, tags.slug
             FROM article_tags
             INNER JOIN tags ON tags.id = article_tags.tag_id
             WHERE article_tags.article_id = :id
             ORDER BY tags.name'
        );
        $stmt->execute(['id' => $articleId]);

        return $stmt->fetchAll();
    }

    /**
     * Replace the tags on an article. Unknown tags are created.
     *
     * @param string[] $tagNames
     */
    public function syncTags(int $articleId, array $tagNames): void
    {
        $this->pdo->prepare('DELETE FROM article_tags WHERE article_id = :id')
            ->execute(['id' => $articleId]);

        $names = [];
        foreach ($tagNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $names[mb_strtolower($name)] = $name;
        }

        foreach ($names as $name) {
            $slug = Slug::slugify($name);
            $stmt = $this->pdo->prepare('SELECT id FROM tags WHERE slug = :slug');
            $stmt->execute(['slug' => $slug]);
            $tagId = $stmt->fetchColumn();
            if (!$tagId) {
                $insert = $this->pdo->prepare('INSERT INTO tags (name, slug) VALUES (:name, :slug)');
                $insert->execute(['name' => $name, 'slug' => $slug]);
                $tagId = (int)$this->pdo->lastInsertId();
            }

            $this->pdo->prepare('INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (:a, :t)')
                ->execute(['a' => $articleId, 't' => (int)$tagId]);
        }
    }

    // ---- Articles ----

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT articles.*, users.display_name AS author_name, users.slug AS author_slug,
                    categories.name AS category_name, categories.slug AS category_slug
             FROM articles
             INNER JOIN users ON users.id = articles.author_user_id
             LEFT JOIN categories ON categories.id = articles.category_id
             WHERE articles.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT articles.*, users.display_name AS author_name, users.slug AS author_slug,
                    categories.name AS category_name, categories.slug AS category_slug
             FROM articles
             INNER JOIN users ON users.id = articles.author_user_id
             LEFT JOIN categories ON categories.id = articles.category_id
             WHERE articles.slug = :slug'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM articles WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function createArticle(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO articles
                (title, slug, summary, body_markdown, body_html, status, author_user_id,
                 category_id, cover_media_id, meta_title, meta_description, published_at)
             VALUES
                (:title, :slug, :summary, :body_markdown, :body_html, :status, :author_user_id,
                 :category_id, :cover_media_id, :meta_title, :meta_description, :published_at)'
        );
        $stmt->execute([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'summary' => $data['summary'] ?? '',
            'body_markdown' => $data['body_markdown'],
            'body_html' => $data['body_html'],
            'status' => $data['status'] ?? 'draft',
            'author_user_id' => (int)$data['author_user_id'],
            'category_id' => $data['category_id'] !== null ? (int)$data['category_id'] : null,
            'cover_media_id' => $data['cover_media_id'] !== null ? (int)$data['cover_media_id'] : null,
            'meta_title' => $data['meta_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'published_at' => $data['published_at'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateArticle(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE articles
             SET title = :title,
                 slug = :slug,
                 summary = :summary,
                 body_markdown = :body_markdown,
                 body_html = :body_html,
                 status = :status,
                 category_id = :category_id,
                 cover_media_id = :cover_media_id,
                 meta_title = :meta_title,
                 meta_description = :meta_description,
                 published_at = :published_at
             WHERE id = :id'
        );
        $stmt->execute([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'summary' => $data['summary'] ?? '',
            'body_markdown' => $data['body_markdown'],
            'body_html' => $data['body_html'],
            'status' => $data['status'] ?? 'draft',
            'category_id' => $data['category_id'] !== null ? (int)$data['category_id'] : null,
            'cover_media_id' => $data['cover_media_id'] !== null ? (int)$data['cover_media_id'] : null,
            'meta_title' => $data['meta_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'published_at' => $data['published_at'] ?? null,
            'id' => $id,
        ]);
    }

    public function deleteArticle(int $id): void
    {
        $this->pdo->prepare('DELETE FROM articles WHERE id = :id')->execute(['id' => $id]);
    }

    public function listForAdmin(): array
    {
        return $this->pdo->query(
            'SELECT articles.id, articles.title, articles.slug, articles.status,
                    articles.published_at, articles.updated_at,
                    users.display_name AS author_name,
                    categories.name AS category_name
             FROM articles
             INNER JOIN users ON users.id = articles.author_user_id
             LEFT JOIN categories ON categories.id = articles.category_id
             ORDER BY articles.updated_at DESC'
        )->fetchAll();
    }

    public function countByStatus(): array
    {
        $rows = $this->pdo->query(
            'SELECT status, COUNT(*) AS n FROM articles GROUP BY status'
        )->fetchAll();
        $out = ['draft' => 0, 'published' => 0, 'archived' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['n'];
        }
        return $out;
    }

    public function listPublished(int $page, int $perPage, ?string $categorySlug = null, ?string $tagSlug = null): array
    {
        $sql = 'SELECT articles.id, articles.title, articles.slug, articles.summary,
                       articles.cover_media_id, articles.published_at, articles.updated_at,
                       users.display_name AS author_name, users.slug AS author_slug,
                       categories.name AS category_name, categories.slug AS category_slug,
                       media.stored_name AS cover
                FROM articles
                INNER JOIN users ON users.id = articles.author_user_id
                LEFT JOIN categories ON categories.id = articles.category_id
                LEFT JOIN media ON media.id = articles.cover_media_id';

        $where = ['articles.status = :status', 'articles.published_at IS NOT NULL', 'articles.published_at <= CURRENT_TIMESTAMP'];
        $params = ['status' => 'published'];

        if ($categorySlug !== null) {
            $where[] = 'categories.slug = :category_slug';
            $params['category_slug'] = $categorySlug;
        }
        if ($tagSlug !== null) {
            $sql .= ' INNER JOIN article_tags ON article_tags.article_id = articles.id
                      INNER JOIN tags ON tags.id = article_tags.tag_id';
            $where[] = 'tags.slug = :tag_slug';
            $params['tag_slug'] = $tagSlug;
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' GROUP BY articles.id ORDER BY articles.published_at DESC';
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countPublished(?string $categorySlug = null, ?string $tagSlug = null): int
    {
        $sql = 'SELECT COUNT(DISTINCT articles.id)
                FROM articles
                LEFT JOIN categories ON categories.id = articles.category_id';
        $where = ['articles.status = :status', 'articles.published_at IS NOT NULL', 'articles.published_at <= CURRENT_TIMESTAMP'];
        $params = ['status' => 'published'];

        if ($categorySlug !== null) {
            $where[] = 'categories.slug = :category_slug';
            $params['category_slug'] = $categorySlug;
        }
        if ($tagSlug !== null) {
            $sql .= ' INNER JOIN article_tags ON article_tags.article_id = articles.id
                      INNER JOIN tags ON tags.id = article_tags.tag_id';
            $where[] = 'tags.slug = :tag_slug';
            $params['tag_slug'] = $tagSlug;
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * Published articles by a single author, newest first, paginated.
     */
    public function listPublishedByAuthor(int $authorId, int $page, int $perPage): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT articles.id, articles.title, articles.slug, articles.summary,
                    articles.cover_media_id, articles.published_at,
                    categories.name AS category_name, categories.slug AS category_slug,
                    media.stored_name AS cover
             FROM articles
             LEFT JOIN categories ON categories.id = articles.category_id
             LEFT JOIN media ON media.id = articles.cover_media_id
             WHERE articles.status = :status
               AND articles.published_at IS NOT NULL
               AND articles.published_at <= CURRENT_TIMESTAMP
               AND articles.author_user_id = :author_id
             ORDER BY articles.published_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('status', 'published');
        $stmt->bindValue('author_id', $authorId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countPublishedByAuthor(int $authorId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM articles
             WHERE articles.status = :status
               AND articles.published_at IS NOT NULL
               AND articles.published_at <= CURRENT_TIMESTAMP
               AND articles.author_user_id = :author_id'
        );
        $stmt->bindValue('status', 'published');
        $stmt->bindValue('author_id', $authorId, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public function searchPublished(string $query, int $limit = 50): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT articles.id, articles.title, articles.slug, articles.summary, articles.published_at
             FROM articles
             WHERE articles.status = :status
               AND articles.published_at IS NOT NULL
               AND articles.published_at <= CURRENT_TIMESTAMP
               AND (articles.title LIKE :q OR articles.summary LIKE :q OR articles.body_markdown LIKE :q)
             ORDER BY articles.published_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('status', 'published');
        $stmt->bindValue('q', $like);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function searchAdmin(string $query): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT articles.id, articles.title, articles.slug, articles.status, articles.updated_at
             FROM articles
             WHERE articles.title LIKE :q OR articles.slug LIKE :q OR articles.body_markdown LIKE :q
             ORDER BY articles.updated_at DESC LIMIT 50'
        );
        $stmt->execute(['q' => $like]);

        return $stmt->fetchAll();
    }
}
