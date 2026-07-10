<?php
declare(strict_types=1);

final class CollectionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(bool $publicOnly = false): array
    {
        $sql = 'SELECT cc.*, (SELECT COUNT(*) FROM content_collection_items cci WHERE cci.collection_id = cc.id) AS item_count
                FROM content_collections cc';
        if ($publicOnly) {
            $sql .= ' WHERE cc.is_public = 1';
        }
        return $this->pdo->query($sql . ' ORDER BY cc.name')->fetchAll();
    }

    public function create(array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $slug = Slug::slugify((string)($data['slug'] ?? $name));
        if ($name === '' || $slug === '') {
            throw new InvalidArgumentException('Collection name and slug are required.');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO content_collections (name, slug, description, is_public)
             VALUES (:name, :slug, :description, :is_public)'
        );
        $stmt->execute([
            'name' => mb_substr($name, 0, 120), 'slug' => $slug,
            'description' => mb_substr(trim((string)($data['description'] ?? '')), 0, 500),
            'is_public' => !empty($data['is_public']) ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM content_collections WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Collection not found.');
        }
        $name = trim((string)($data['name'] ?? $existing['name']));
        $slug = Slug::slugify((string)($data['slug'] ?? $existing['slug']));
        if ($name === '' || $slug === '') {
            throw new InvalidArgumentException('Collection name and slug are required.');
        }
        $this->pdo->prepare(
            'UPDATE content_collections SET name = :name, slug = :slug, description = :description, is_public = :is_public WHERE id = :id'
        )->execute([
            'id' => $id, 'name' => mb_substr($name, 0, 120), 'slug' => $slug,
            'description' => mb_substr(trim((string)($data['description'] ?? $existing['description'])), 0, 500),
            'is_public' => array_key_exists('is_public', $data) ? (!empty($data['is_public']) ? 1 : 0) : (int)$existing['is_public'],
        ]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM content_collections WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<int,array{entity_type:string,entity_id:int,sort_order?:int}> $items */
    public function replaceItems(int $collectionId, array $items): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM content_collection_items WHERE collection_id = :id')->execute(['id' => $collectionId]);
            $stmt = $this->pdo->prepare(
                'INSERT INTO content_collection_items (collection_id, entity_type, entity_id, sort_order)
                 VALUES (:collection_id, :entity_type, :entity_id, :sort_order)'
            );
            foreach ($items as $index => $item) {
                $type = strtolower(trim((string)($item['entity_type'] ?? '')));
                $id = (int)($item['entity_id'] ?? 0);
                if (!in_array($type, ContentExtensionRepository::ENTITY_TYPES, true) || $id < 1) {
                    continue;
                }
                $stmt->execute([
                    'collection_id' => $collectionId, 'entity_type' => $type, 'entity_id' => $id,
                    'sort_order' => (int)($item['sort_order'] ?? $index),
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function collectionsFor(string $entityType, int $entityId, bool $publicOnly = false): array
    {
        $sql = 'SELECT cc.* FROM content_collection_items cci
                INNER JOIN content_collections cc ON cc.id = cci.collection_id
                WHERE cci.entity_type = :type AND cci.entity_id = :id';
        if ($publicOnly) {
            $sql .= ' AND cc.is_public = 1';
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY cc.name');
        $stmt->execute(['type' => $entityType, 'id' => $entityId]);
        return $stmt->fetchAll();
    }

    public function items(int $collectionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT entity_type, entity_id, sort_order FROM content_collection_items
             WHERE collection_id = :collection_id ORDER BY sort_order, entity_type, entity_id'
        );
        $stmt->execute(['collection_id' => $collectionId]);
        return $stmt->fetchAll();
    }
}
