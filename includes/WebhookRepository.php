<?php
declare(strict_types=1);

final class WebhookRepository
{
    public const EVENTS = ['content.published', 'user.signed_up', 'comment.pending', 'form.submitted'];

    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM webhook_endpoints ORDER BY name')->fetchAll();
    }

    public function create(array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $event = (string)($data['event_name'] ?? '');
        if ($name === '' || !in_array($event, self::EVENTS, true)) {
            throw new InvalidArgumentException('Webhook name and supported event are required.');
        }
        $targetUrl = ListingLinkNormalizer::normalizeUrl((string)($data['target_url'] ?? ''));
        if (parse_url($targetUrl, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException('Webhooks require an HTTPS endpoint.');
        }
        $secret = trim((string)($data['signing_secret'] ?? ''));
        if ($secret === '') {
            $secret = bin2hex(random_bytes(24));
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO webhook_endpoints (name, event_name, target_url, signing_secret, is_active)
             VALUES (:name, :event_name, :target_url, :signing_secret, :is_active)'
        );
        $stmt->execute([
            'name' => mb_substr($name, 0, 120), 'event_name' => $event, 'target_url' => $targetUrl,
            'signing_secret' => mb_substr($secret, 0, 255), 'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM webhook_endpoints WHERE id = :id')->execute(['id' => $id]);
    }

    public function activeForEvent(string $eventName): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webhook_endpoints WHERE event_name = :event_name AND is_active = 1 ORDER BY id');
        $stmt->execute(['event_name' => $eventName]);
        return $stmt->fetchAll();
    }

    public function recordDelivery(int $endpointId, string $eventName, ?int $statusCode, string $summary): void
    {
        $this->pdo->prepare(
            'INSERT INTO webhook_deliveries (endpoint_id, event_name, response_code, response_summary)
             VALUES (:endpoint_id, :event_name, :response_code, :summary)'
        )->execute([
            'endpoint_id' => $endpointId, 'event_name' => $eventName, 'response_code' => $statusCode,
            'summary' => mb_substr($summary, 0, 500),
        ]);
        $this->pdo->prepare(
            'UPDATE webhook_endpoints
             SET last_status_code = :status_code, last_error = :last_error,
                 last_delivered_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['id' => $endpointId, 'status_code' => $statusCode, 'last_error' => mb_substr($summary, 0, 500)]);
    }
}
