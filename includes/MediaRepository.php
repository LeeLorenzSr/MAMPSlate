<?php
declare(strict_types=1);

final class MediaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $uploaderId, string $storedName, string $originalName, string $mimeType, int $fileSize, ?int $width, ?int $height): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO media
                (uploader_user_id, stored_name, original_name, mime_type, file_size, width, height)
             VALUES (:uploader, :stored_name, :original_name, :mime_type, :file_size, :width, :height)'
        );
        $stmt->execute([
            'uploader' => $uploaderId,
            'stored_name' => $storedName,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT media.*, users.display_name AS uploader_name
             FROM media
             INNER JOIN users ON users.id = media.uploader_user_id
             WHERE media.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listAll(): array
    {
        return $this->pdo->query(
            'SELECT media.id, media.stored_name, media.original_name, media.mime_type,
                    media.file_size, media.width, media.height, media.alt_text, media.title,
                    media.created_at, users.display_name AS uploader_name
             FROM media
             INNER JOIN users ON users.id = media.uploader_user_id
             ORDER BY media.created_at DESC'
        )->fetchAll();
    }

    public function count(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM media')->fetchColumn();
    }

    public function searchAdmin(string $query): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT id, stored_name, original_name, mime_type, created_at
             FROM media
             WHERE original_name LIKE :q1 OR alt_text LIKE :q2 OR title LIKE :q3
             ORDER BY created_at DESC LIMIT 50'
        );
        $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like]);

        return $stmt->fetchAll();
    }

    public function totalStorage(): int
    {
        return (int)$this->pdo->query('SELECT COALESCE(SUM(file_size), 0) FROM media')->fetchColumn();
    }

    public function updateMeta(int $id, string $altText, string $title): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE media SET alt_text = :alt, title = :title WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'alt' => trim($altText),
            'title' => trim($title),
        ]);
    }

    /**
     * Delete a media row. Returns the stored_name so the caller can remove the file.
     */
    public function delete(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT stored_name FROM media WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $this->pdo->prepare('DELETE FROM media WHERE id = :id')->execute(['id' => $id]);

        return $row['stored_name'];
    }
}
