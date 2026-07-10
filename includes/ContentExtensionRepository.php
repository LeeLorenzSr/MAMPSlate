<?php
declare(strict_types=1);

/**
 * Stores generic extensions for core and module-provided entities.  The base
 * CMS deliberately keeps entity references polymorphic: a vertical can add an
 * entity type without coupling the core schema to its tables.
 */
final class ContentExtensionRepository
{
    public const ENTITY_TYPES = ['article', 'page', 'listing'];
    public const FIELD_TYPES = ['text', 'textarea', 'number', 'date', 'url', 'boolean', 'select'];

    public function __construct(private PDO $pdo)
    {
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::ENTITY_TYPES, true)
            || (($GLOBALS['modules'] ?? null) instanceof ModuleRegistry
                && $GLOBALS['modules']->hasEntityType($entityType));
    }

    public function definitions(string $entityType): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM content_field_definitions WHERE entity_type = :type ORDER BY sort_order, label'
        );
        $stmt->execute(['type' => $entityType]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['options'] = $this->decodeArray($row['options_json'] ?? null);
        }
        unset($row);
        return $rows;
    }

    public function defineField(array $data): int
    {
        $entityType = $this->entityType((string)($data['entity_type'] ?? ''));
        $fieldKey = $this->fieldKey((string)($data['field_key'] ?? ''));
        $label = trim((string)($data['label'] ?? ''));
        $type = (string)($data['value_type'] ?? 'text');
        if ($label === '' || !in_array($type, self::FIELD_TYPES, true)) {
            throw new InvalidArgumentException('Provide a label and supported field type.');
        }
        $options = $this->normalizeOptions($data['options'] ?? []);
        if ($type === 'select' && $options === []) {
            throw new InvalidArgumentException('Select fields require at least one option.');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO content_field_definitions
                (entity_type, field_key, label, value_type, options_json, is_required, is_public, sort_order)
             VALUES (:type, :field_key, :label, :value_type, :options, :required, :public, :sort_order)'
        );
        $stmt->execute([
            'type' => $entityType,
            'field_key' => $fieldKey,
            'label' => mb_substr($label, 0, 120),
            'value_type' => $type,
            'options' => json_encode($options, JSON_UNESCAPED_SLASHES),
            'required' => !empty($data['is_required']) ? 1 : 0,
            'public' => !empty($data['is_public']) ? 1 : 0,
            'sort_order' => (int)($data['sort_order'] ?? 0),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteDefinition(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT entity_type, field_key FROM content_field_definitions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM content_field_values WHERE entity_type = :type AND field_key = :field_key')
                ->execute(['type' => $row['entity_type'], 'field_key' => $row['field_key']]);
            $this->pdo->prepare('DELETE FROM content_field_definitions WHERE id = :id')->execute(['id' => $id]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function fieldValues(string $entityType, int $entityId, bool $publicOnly = false): array
    {
        $sql = 'SELECT d.field_key, d.label, d.value_type, d.is_public, d.sort_order, v.value_json
                FROM content_field_definitions d
                INNER JOIN content_field_values v ON v.entity_type = d.entity_type AND v.field_key = d.field_key
                WHERE d.entity_type = :type AND v.entity_id = :id';
        if ($publicOnly) {
            $sql .= ' AND d.is_public = 1';
        }
        $sql .= ' ORDER BY d.sort_order, d.label';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['type' => $this->entityType($entityType), 'id' => $entityId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'key' => $row['field_key'],
                'label' => $row['label'],
                'type' => $row['value_type'],
                'value' => $this->decodeValue($row['value_json']),
            ];
        }
        return $out;
    }

    /** @param array<string,mixed> $values */
    public function saveFieldValues(string $entityType, int $entityId, array $values): void
    {
        $entityType = $this->entityType($entityType);
        foreach ($this->definitions($entityType) as $definition) {
            $key = $definition['field_key'];
            $value = $values[$key] ?? null;
            $normalized = $this->validateFieldValue($definition, $value);
            if ($normalized === null || $normalized === '') {
                $this->pdo->prepare('DELETE FROM content_field_values WHERE entity_type = :type AND entity_id = :id AND field_key = :key')
                    ->execute(['type' => $entityType, 'id' => $entityId, 'key' => $key]);
                continue;
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO content_field_values (entity_type, entity_id, field_key, value_json)
                 VALUES (:type, :id, :key, :value)
                 ON DUPLICATE KEY UPDATE value_json = :value_update'
            );
            $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $stmt->execute(['type' => $entityType, 'id' => $entityId, 'key' => $key, 'value' => $encoded, 'value_update' => $encoded]);
        }
    }

    public function links(string $entityType, int $entityId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM external_links WHERE entity_type = :type AND entity_id = :id ORDER BY sort_order, id');
        $stmt->execute(['type' => $this->entityType($entityType), 'id' => $entityId]);
        return $stmt->fetchAll();
    }

    /** @param array<int,array<string,mixed>> $links */
    public function saveLinks(string $entityType, int $entityId, array $links): void
    {
        $entityType = $this->entityType($entityType);
        $normalized = [];
        foreach ($links as $index => $link) {
            if (!is_array($link)) {
                continue;
            }
            $label = trim((string)($link['label'] ?? ''));
            $url = trim((string)($link['url'] ?? ''));
            if ($label === '' && $url === '') {
                continue;
            }
            if ($label === '' || $url === '') {
                throw new InvalidArgumentException('Each external link needs both a label and URL.');
            }
            $normalized[] = [
                'label' => mb_substr($label, 0, 120),
                'url' => ListingLinkNormalizer::normalizeUrl($url),
                'service_type' => $this->serviceType((string)($link['service_type'] ?? 'website')),
                'rel_attributes' => $this->relAttributes((string)($link['rel_attributes'] ?? 'noopener noreferrer')),
                'sort_order' => (int)($link['sort_order'] ?? $index),
            ];
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM external_links WHERE entity_type = :type AND entity_id = :id')
                ->execute(['type' => $entityType, 'id' => $entityId]);
            $insert = $this->pdo->prepare(
                'INSERT INTO external_links (entity_type, entity_id, label, url, service_type, rel_attributes, sort_order)
                 VALUES (:type, :id, :label, :url, :service, :rel, :sort_order)'
            );
            foreach ($normalized as $link) {
                $insert->execute([
                    'type' => $entityType, 'id' => $entityId, 'label' => $link['label'], 'url' => $link['url'],
                    'service' => $link['service_type'], 'rel' => $link['rel_attributes'], 'sort_order' => $link['sort_order'],
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function linkById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM external_links WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function embeds(string $entityType, int $entityId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM content_embeds WHERE entity_type = :type AND entity_id = :id ORDER BY sort_order, id');
        $stmt->execute(['type' => $this->entityType($entityType), 'id' => $entityId]);
        return $stmt->fetchAll();
    }

    /** @param array<int,array<string,mixed>> $embeds */
    public function saveEmbeds(string $entityType, int $entityId, array $embeds): void
    {
        $entityType = $this->entityType($entityType);
        $normalized = [];
        foreach ($embeds as $index => $embed) {
            if (!is_array($embed)) {
                continue;
            }
            $url = trim((string)($embed['source_url'] ?? $embed['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $normalized[] = EmbedProvider::normalize($url, (string)($embed['provider'] ?? '')) + [
                'title' => mb_substr(trim((string)($embed['title'] ?? '')), 0, 160),
                'sort_order' => (int)($embed['sort_order'] ?? $index),
            ];
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM content_embeds WHERE entity_type = :type AND entity_id = :id')
                ->execute(['type' => $entityType, 'id' => $entityId]);
            $insert = $this->pdo->prepare(
                'INSERT INTO content_embeds (entity_type, entity_id, provider, source_url, title, sort_order)
                 VALUES (:type, :id, :provider, :source_url, :title, :sort_order)'
            );
            foreach ($normalized as $embed) {
                $insert->execute(['type' => $entityType, 'id' => $entityId] + $embed);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function relationships(string $entityType, int $entityId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM entity_relationships WHERE source_type = :type AND source_id = :id ORDER BY sort_order, id'
        );
        $stmt->execute(['type' => $this->entityType($entityType), 'id' => $entityId]);
        return $stmt->fetchAll();
    }

    /** @param array<int,array<string,mixed>> $relationships */
    public function saveRelationships(string $entityType, int $entityId, array $relationships, ?int $actorId = null): void
    {
        $entityType = $this->entityType($entityType);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM entity_relationships WHERE source_type = :type AND source_id = :id')
                ->execute(['type' => $entityType, 'id' => $entityId]);
            $insert = $this->pdo->prepare(
                'INSERT INTO entity_relationships
                 (source_type, source_id, target_type, target_id, relationship_type, label, sort_order, created_by_user_id)
                 VALUES (:source_type, :source_id, :target_type, :target_id, :relationship_type, :label, :sort_order, :actor)'
            );
            foreach ($relationships as $index => $relationship) {
                if (!is_array($relationship) || empty($relationship['target_id'])) {
                    continue;
                }
                $targetType = $this->entityType((string)($relationship['target_type'] ?? ''));
                $targetId = max(1, (int)$relationship['target_id']);
                $relationshipType = $this->relationType((string)($relationship['relationship_type'] ?? 'related'));
                if ($entityType === $targetType && $entityId === $targetId) {
                    throw new InvalidArgumentException('Content cannot be related to itself.');
                }
                $insert->execute([
                    'source_type' => $entityType, 'source_id' => $entityId, 'target_type' => $targetType,
                    'target_id' => $targetId, 'relationship_type' => $relationshipType,
                    'label' => mb_substr(trim((string)($relationship['label'] ?? '')), 0, 120),
                    'sort_order' => (int)($relationship['sort_order'] ?? $index), 'actor' => $actorId,
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function terms(string $entityType, int $entityId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.name, t.slug, t.description, tx.id AS taxonomy_id, tx.name AS taxonomy_name, tx.slug AS taxonomy_slug
             FROM entity_terms et
             INNER JOIN taxonomy_terms t ON t.id = et.term_id
             INNER JOIN taxonomies tx ON tx.id = t.taxonomy_id
             WHERE et.entity_type = :type AND et.entity_id = :id
             ORDER BY tx.name, t.name'
        );
        $stmt->execute(['type' => $this->entityType($entityType), 'id' => $entityId]);
        return $stmt->fetchAll();
    }

    /** @param int[] $termIds */
    public function saveTerms(string $entityType, int $entityId, array $termIds): void
    {
        $entityType = $this->entityType($entityType);
        $ids = array_values(array_unique(array_filter(array_map('intval', $termIds), fn($id) => $id > 0)));
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM entity_terms WHERE entity_type = :type AND entity_id = :id')
                ->execute(['type' => $entityType, 'id' => $entityId]);
            if ($ids !== []) {
                $insert = $this->pdo->prepare('INSERT IGNORE INTO entity_terms (entity_type, entity_id, term_id) VALUES (:type, :id, :term_id)');
                foreach ($ids as $termId) {
                    $insert->execute(['type' => $entityType, 'id' => $entityId, 'term_id' => $termId]);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function extensionPayload(string $entityType, int $entityId, bool $publicOnly = false): array
    {
        return [
            'custom_fields' => $this->fieldValues($entityType, $entityId, $publicOnly),
            'links' => $this->links($entityType, $entityId),
            'embeds' => $this->embeds($entityType, $entityId),
            'terms' => $this->terms($entityType, $entityId),
            'relationships' => $this->relationships($entityType, $entityId),
        ];
    }

    private function entityType(string $value): string
    {
        $value = strtolower(trim($value));
        if (!$this->supports($value)) {
            throw new InvalidArgumentException('Unsupported content type.');
        }
        return $value;
    }

    private function fieldKey(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z][a-z0-9_]{0,79}$/', $value)) {
            throw new InvalidArgumentException('Field keys use lowercase letters, numbers, and underscores.');
        }
        return $value;
    }

    private function validateFieldValue(array $definition, mixed $value): mixed
    {
        $type = $definition['value_type'];
        if ($type === 'boolean') {
            return !empty($value);
        }
        $value = is_scalar($value) ? trim((string)$value) : '';
        if ($value === '') {
            if (!empty($definition['is_required'])) {
                throw new InvalidArgumentException($definition['label'] . ' is required.');
            }
            return null;
        }
        if ($type === 'number' && !is_numeric($value)) {
            throw new InvalidArgumentException($definition['label'] . ' must be a number.');
        }
        if ($type === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException($definition['label'] . ' must be a date.');
        }
        if ($type === 'url') {
            $value = ListingLinkNormalizer::normalizeUrl($value);
        }
        if ($type === 'select' && !in_array($value, $definition['options'] ?? [], true)) {
            throw new InvalidArgumentException($definition['label'] . ' has an invalid option.');
        }
        return mb_substr($value, 0, $type === 'textarea' ? 10000 : 1000);
    }

    private function normalizeOptions(mixed $options): array
    {
        if (is_string($options)) {
            $options = preg_split('/\r\n|\r|\n/', $options) ?: [];
        }
        if (!is_array($options)) {
            return [];
        }
        $out = [];
        foreach ($options as $option) {
            $option = mb_substr(trim((string)$option), 0, 120);
            if ($option !== '') {
                $out[$option] = $option;
            }
        }
        return array_values($out);
    }

    private function decodeArray(?string $json): array
    {
        $value = json_decode((string)$json, true);
        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    private function decodeValue(string $json): mixed
    {
        $value = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $value : $json;
    }

    private function serviceType(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^[a-z0-9_-]{1,60}$/', $value) ? $value : 'website';
    }

    private function relationType(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $value)) {
            throw new InvalidArgumentException('Relationship types use lowercase letters, numbers, dashes, and underscores.');
        }
        return $value;
    }

    private function relAttributes(string $value): string
    {
        $allowed = ['noopener', 'noreferrer', 'nofollow', 'ugc', 'sponsored'];
        $parts = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        $parts = array_values(array_unique(array_filter($parts, fn($part) => in_array($part, $allowed, true))));
        return implode(' ', $parts === [] ? ['noopener', 'noreferrer'] : $parts);
    }
}
