<?php
declare(strict_types=1);

final class AnalyticsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function recordOutboundClick(array $link): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO analytics_events (event_name, entity_type, entity_id, link_id)
             VALUES (\'outbound_click\', :entity_type, :entity_id, :link_id)'
        );
        $stmt->execute([
            'entity_type' => (string)$link['entity_type'], 'entity_id' => (int)$link['entity_id'], 'link_id' => (int)$link['id'],
        ]);
    }

    public function summary(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $stmt = $this->pdo->prepare(
            'SELECT event_name, COUNT(*) AS total FROM analytics_events
             WHERE created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL :days DAY)
             GROUP BY event_name'
        );
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
