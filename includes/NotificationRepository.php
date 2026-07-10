<?php
declare(strict_types=1);

final class NotificationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(?int $userId, string $eventName, string $title, string $body = '', string $url = ''): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, event_name, title, body, url)
             VALUES (:user_id, :event_name, :title, :body, :url)'
        );
        $stmt->execute([
            'user_id' => $userId, 'event_name' => mb_substr($eventName, 0, 80),
            'title' => mb_substr($title, 0, 160), 'body' => mb_substr($body, 0, 500), 'url' => mb_substr($url, 0, 500),
        ]);
    }

    public function recent(?int $userId = null, int $limit = 50): array
    {
        $sql = 'SELECT * FROM notifications';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE user_id IS NULL OR user_id = :user_id';
            $params['user_id'] = $userId;
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY created_at DESC LIMIT :limit');
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue('limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function unreadCount(?int $userId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM notifications WHERE is_read = 0';
        $params = [];
        if ($userId !== null) {
            $sql .= ' AND (user_id IS NULL OR user_id = :user_id)';
            $params['user_id'] = $userId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function markAllRead(?int $userId = null): void
    {
        $sql = 'UPDATE notifications SET is_read = 1 WHERE is_read = 0';
        $params = [];
        if ($userId !== null) {
            $sql .= ' AND (user_id IS NULL OR user_id = :user_id)';
            $params['user_id'] = $userId;
        }
        $this->pdo->prepare($sql)->execute($params);
    }
}
