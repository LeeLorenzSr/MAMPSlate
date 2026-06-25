<?php
declare(strict_types=1);

/**
 * Audit logging for security-relevant events.
 *
 * Never log plaintext secrets. Metadata is sanitized to strip any key whose
 * name looks sensitive (password, token, secret, key, hash, code) as a
 * defense-in-depth measure — callers must still avoid passing secrets in.
 */
final class AuditLogger
{
    /** Metadata keys that are never persisted, even if a caller passes them. */
    private const REDACTED_KEY_FRAGMENTS = ['password', 'token', 'secret', 'api_key', 'apikey', 'hash', 'code', 'credential'];

    public function __construct(private PDO $pdo)
    {
    }

    public function log(
        string $eventType,
        ?int $actorUserId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = []
    ): void {
        $metadata = $this->sanitize($metadata);

        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_events
                (actor_user_id, event_type, entity_type, entity_id, ip_address, user_agent, metadata_json)
             VALUES (:actor, :event, :entity_type, :entity_id, :ip, :ua, :metadata)'
        );
        $stmt->execute([
            'actor' => $actorUserId,
            'event' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string)$entityId : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param array{event_type?: ?string, actor?: ?int, entity_type?: ?string, from?: ?string, to?: ?string} $filters
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $sql = 'SELECT audit_events.*, users.display_name AS actor_name
                FROM audit_events
                LEFT JOIN users ON users.id = audit_events.actor_user_id';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ' ORDER BY audit_events.created_at DESC, audit_events.id DESC';
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

    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = 'SELECT COUNT(*) FROM audit_events';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public function eventTypes(): array
    {
        return $this->pdo->query(
            'SELECT DISTINCT event_type FROM audit_events ORDER BY event_type'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['event_type'])) {
            $where[] = 'audit_events.event_type = :event_type';
            $params['event_type'] = $filters['event_type'];
        }
        if (!empty($filters['actor'])) {
            $where[] = 'audit_events.actor_user_id = :actor';
            $params['actor'] = (int)$filters['actor'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'audit_events.entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'audit_events.created_at >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'audit_events.created_at <= :to';
            $params['to'] = $filters['to'];
        }

        return [implode(' AND ', $where), $params];
    }

    private function sanitize(array $metadata): array
    {
        $clean = [];
        foreach ($metadata as $key => $value) {
            $lower = strtolower((string)$key);
            $redact = false;
            foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
                if (str_contains($lower, $fragment)) {
                    $redact = true;
                    break;
                }
            }
            $clean[$key] = $redact ? '[redacted]' : $value;
        }
        return $clean;
    }
}
