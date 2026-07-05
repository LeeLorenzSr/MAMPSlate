<?php
declare(strict_types=1);

final class ContactRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function allForms(): array
    {
        return $this->pdo->query(
            'SELECT contact_forms.*,
                    (SELECT COUNT(*) FROM contact_submissions s WHERE s.form_id = contact_forms.id) AS submission_count
             FROM contact_forms
             ORDER BY contact_forms.name'
        )->fetchAll();
    }

    public function findFormBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contact_forms WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findFormById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contact_forms WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function saveForm(array $data, ?int $id = null): int
    {
        $params = [
            'name' => trim((string)$data['name']),
            'slug' => (string)$data['slug'],
            'description' => trim((string)($data['description'] ?? '')),
            'recipient_email' => trim((string)($data['recipient_email'] ?? '')),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'notify_on_submit' => !empty($data['notify_on_submit']) ? 1 : 0,
        ];

        if ($id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO contact_forms (name, slug, description, recipient_email, is_active, notify_on_submit)
                 VALUES (:name, :slug, :description, :recipient_email, :is_active, :notify_on_submit)'
            );
            $stmt->execute($params);
            return (int)$this->pdo->lastInsertId();
        }

        $params['id'] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE contact_forms
             SET name = :name,
                 slug = :slug,
                 description = :description,
                 recipient_email = :recipient_email,
                 is_active = :is_active,
                 notify_on_submit = :notify_on_submit
             WHERE id = :id'
        );
        $stmt->execute($params);
        return $id;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM contact_forms WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function createSubmission(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO contact_submissions
                (form_id, name, email, subject, message, status, ip_hash, user_agent)
             VALUES
                (:form_id, :name, :email, :subject, :message, :status, :ip_hash, :user_agent)'
        );
        $stmt->execute([
            'form_id' => (int)$data['form_id'],
            'name' => trim((string)($data['name'] ?? '')),
            'email' => trim((string)($data['email'] ?? '')),
            'subject' => trim((string)($data['subject'] ?? '')),
            'message' => trim((string)$data['message']),
            'status' => in_array(($data['status'] ?? 'pending'), ['pending', 'handled', 'spam', 'archived'], true) ? $data['status'] : 'pending',
            'ip_hash' => $data['ip_hash'] ?? null,
            'user_agent' => isset($data['user_agent']) ? mb_substr((string)$data['user_agent'], 0, 255) : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function submissions(?string $status = null, int $limit = 200): array
    {
        $sql = 'SELECT contact_submissions.*, contact_forms.name AS form_name, contact_forms.slug AS form_slug
                FROM contact_submissions
                INNER JOIN contact_forms ON contact_forms.id = contact_submissions.form_id';
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE contact_submissions.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY contact_submissions.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findSubmission(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT contact_submissions.*, contact_forms.name AS form_name
             FROM contact_submissions
             INNER JOIN contact_forms ON contact_forms.id = contact_submissions.form_id
             WHERE contact_submissions.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function setSubmissionStatus(int $id, string $status): void
    {
        if (!in_array($status, ['pending', 'handled', 'spam', 'archived'], true)) {
            $status = 'pending';
        }
        $stmt = $this->pdo->prepare('UPDATE contact_submissions SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function countPending(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM contact_submissions WHERE status = 'pending'")->fetchColumn();
    }
}
