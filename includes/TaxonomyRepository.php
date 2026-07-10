<?php
declare(strict_types=1);

final class TaxonomyRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT tx.*, (SELECT COUNT(*) FROM taxonomy_terms tt WHERE tt.taxonomy_id = tx.id) AS term_count
             FROM taxonomies tx ORDER BY tx.name'
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM taxonomies WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Taxonomy name is required.');
        }
        $slug = Slug::slugify((string)($data['slug'] ?? $name));
        if ($slug === '') {
            throw new InvalidArgumentException('Taxonomy slug is required.');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO taxonomies (name, slug, description, is_hierarchical)
             VALUES (:name, :slug, :description, :hierarchical)'
        );
        $stmt->execute([
            'name' => mb_substr($name, 0, 120), 'slug' => $slug,
            'description' => mb_substr(trim((string)($data['description'] ?? '')), 0, 500),
            'hierarchical' => !empty($data['is_hierarchical']) ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Taxonomy not found.');
        }
        $name = trim((string)($data['name'] ?? $existing['name']));
        $slug = Slug::slugify((string)($data['slug'] ?? $existing['slug']));
        if ($name === '' || $slug === '') {
            throw new InvalidArgumentException('Taxonomy name and slug are required.');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE taxonomies SET name = :name, slug = :slug, description = :description, is_hierarchical = :hierarchical WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id, 'name' => mb_substr($name, 0, 120), 'slug' => $slug,
            'description' => mb_substr(trim((string)($data['description'] ?? '')), 0, 500),
            'hierarchical' => !empty($data['is_hierarchical']) ? 1 : 0,
        ]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM taxonomies WHERE id = :id')->execute(['id' => $id]);
    }

    public function terms(int $taxonomyId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tt.*, parent.name AS parent_name,
                    (SELECT COUNT(*) FROM entity_terms et WHERE et.term_id = tt.id) AS use_count
             FROM taxonomy_terms tt
             LEFT JOIN taxonomy_terms parent ON parent.id = tt.parent_id
             WHERE tt.taxonomy_id = :taxonomy_id
             ORDER BY tt.name'
        );
        $stmt->execute(['taxonomy_id' => $taxonomyId]);
        return $stmt->fetchAll();
    }

    public function allTerms(): array
    {
        return $this->pdo->query(
            'SELECT tt.id, tt.taxonomy_id, tt.name, tt.slug, tt.parent_id, tx.name AS taxonomy_name, tx.slug AS taxonomy_slug
             FROM taxonomy_terms tt INNER JOIN taxonomies tx ON tx.id = tt.taxonomy_id
             ORDER BY tx.name, tt.name'
        )->fetchAll();
    }

    public function createTerm(int $taxonomyId, array $data): int
    {
        if (!$this->findById($taxonomyId)) {
            throw new InvalidArgumentException('Taxonomy not found.');
        }
        $name = trim((string)($data['name'] ?? ''));
        $slug = Slug::slugify((string)($data['slug'] ?? $name));
        if ($name === '' || $slug === '') {
            throw new InvalidArgumentException('Term name and slug are required.');
        }
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        if ($parentId !== null && !$this->termBelongsTo($parentId, $taxonomyId)) {
            throw new InvalidArgumentException('A term parent must belong to the same taxonomy.');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO taxonomy_terms (taxonomy_id, name, slug, description, parent_id)
             VALUES (:taxonomy_id, :name, :slug, :description, :parent_id)'
        );
        $stmt->execute([
            'taxonomy_id' => $taxonomyId, 'name' => mb_substr($name, 0, 120), 'slug' => $slug,
            'description' => mb_substr(trim((string)($data['description'] ?? '')), 0, 500), 'parent_id' => $parentId,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteTerm(int $id): void
    {
        $this->pdo->prepare('DELETE FROM taxonomy_terms WHERE id = :id')->execute(['id' => $id]);
    }

    private function termBelongsTo(int $termId, int $taxonomyId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM taxonomy_terms WHERE id = :id AND taxonomy_id = :taxonomy_id');
        $stmt->execute(['id' => $termId, 'taxonomy_id' => $taxonomyId]);
        return (bool)$stmt->fetchColumn();
    }
}
