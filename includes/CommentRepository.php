<?php
declare(strict_types=1);

final class CommentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $articleId, int $userId, ?int $parentId, string $body, string $status): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO comments (article_id, user_id, parent_id, body, status)
             VALUES (:article_id, :user_id, :parent_id, :body, :status)'
        );
        $stmt->execute([
            'article_id' => $articleId,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'body' => $body,
            'status' => $status,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Approved comments for an article, with author display name, oldest first.
     */
    public function listApprovedForArticle(int $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT comments.id, comments.parent_id, comments.body, comments.created_at,
                    users.id AS user_id, users.display_name AS author_name
             FROM comments
             INNER JOIN users ON users.id = comments.user_id
             WHERE comments.article_id = :article_id
               AND comments.status = :status
             ORDER BY comments.created_at ASC, comments.id ASC'
        );
        $stmt->execute(['article_id' => $articleId, 'status' => 'approved']);

        return $stmt->fetchAll();
    }

    public function countForArticle(int $articleId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM comments WHERE article_id = :id AND status = :status'
        );
        $stmt->execute(['id' => $articleId, 'status' => 'approved']);

        return (int)$stmt->fetchColumn();
    }

    public function countPending(): int
    {
        return (int)$this->pdo->query(
            "SELECT COUNT(*) FROM comments WHERE status = 'pending'"
        )->fetchColumn();
    }

    public function recent(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT comments.id, comments.body, comments.status, comments.created_at,
                    articles.title AS article_title, articles.slug AS article_slug,
                    users.display_name AS author_name
             FROM comments
             INNER JOIN articles ON articles.id = comments.article_id
             INNER JOIN users ON users.id = comments.user_id
             ORDER BY comments.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function searchAdmin(string $query): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT comments.id, comments.body, comments.status, comments.created_at,
                    articles.title AS article_title, articles.slug AS article_slug,
                    users.display_name AS author_name
             FROM comments
             INNER JOIN articles ON articles.id = comments.article_id
             INNER JOIN users ON users.id = comments.user_id
             WHERE comments.body LIKE :q
             ORDER BY comments.created_at DESC LIMIT 50'
        );
        $stmt->execute(['q' => $like]);

        return $stmt->fetchAll();
    }

    public function listForModeration(): array
    {
        return $this->pdo->query(
            "SELECT comments.id, comments.body, comments.status, comments.created_at,
                    comments.article_id, articles.title AS article_title, articles.slug AS article_slug,
                    users.display_name AS author_name
             FROM comments
             INNER JOIN articles ON articles.id = comments.article_id
             INNER JOIN users ON users.id = comments.user_id
             WHERE comments.status IN ('pending', 'approved')
             ORDER BY
                CASE comments.status WHEN 'pending' THEN 0 ELSE 1 END,
                comments.created_at DESC"
        )->fetchAll();
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE comments SET status = :status WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM comments WHERE id = :id')->execute(['id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM comments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Count comments by a user since a given timestamp (for throttling).
     */
    public function countByUserSince(int $userId, string $since): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM comments WHERE user_id = :user_id AND created_at >= :since'
        );
        $stmt->execute(['user_id' => $userId, 'since' => $since]);

        return (int)$stmt->fetchColumn();
    }
}
