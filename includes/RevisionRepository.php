<?php
declare(strict_types=1);

/**
 * Content revision history for articles and pages.
 *
 * A revision is a JSON snapshot of the editable fields at save time. Restoring
 * a revision writes its snapshot back to the content and creates a new revision
 * (so the restore itself is auditable and reversible).
 */
final class RevisionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function nextRevisionNumber(string $contentType, int $contentId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(revision_number), 0) FROM content_revisions
             WHERE content_type = :type AND content_id = :id'
        );
        $stmt->execute(['type' => $contentType, 'id' => $contentId]);

        return (int)$stmt->fetchColumn() + 1;
    }

    public function createRevision(string $contentType, int $contentId, ?int $changedBy, array $snapshot, ?string $changeNote = null): int
    {
        $number = $this->nextRevisionNumber($contentType, $contentId);

        $stmt = $this->pdo->prepare(
            'INSERT INTO content_revisions
                (content_type, content_id, revision_number, changed_by_user_id, snapshot, change_note)
             VALUES (:type, :id, :number, :changed_by, :snapshot, :note)'
        );
        $stmt->execute([
            'type' => $contentType,
            'id' => $contentId,
            'number' => $number,
            'changed_by' => $changedBy,
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'note' => $changeNote !== null && $changeNote !== '' ? substr(trim($changeNote), 0, 255) : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function listRevisions(string $contentType, int $contentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT content_revisions.*, users.display_name AS changed_by_name
             FROM content_revisions
             LEFT JOIN users ON users.id = content_revisions.changed_by_user_id
             WHERE content_revisions.content_type = :type AND content_revisions.content_id = :id
             ORDER BY content_revisions.revision_number DESC'
        );
        $stmt->execute(['type' => $contentType, 'id' => $contentId]);

        return $stmt->fetchAll();
    }

    public function findById(int $revisionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT content_revisions.*, users.display_name AS changed_by_name
             FROM content_revisions
             LEFT JOIN users ON users.id = content_revisions.changed_by_user_id
             WHERE content_revisions.id = :id'
        );
        $stmt->execute(['id' => $revisionId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
