<?php
declare(strict_types=1);

final class ApiKeyRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createForUser(int $userId, string $name): array
    {
        $apiKey = generateCredential('mpk');
        $stmt = $this->pdo->prepare(
            'INSERT INTO api_keys (user_id, name, key_hash, key_prefix)
             VALUES (:user_id, :name, :key_hash, :key_prefix)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'name' => trim($name),
            'key_hash' => hashCredential($apiKey),
            'key_prefix' => substr($apiKey, 0, 16),
        ]);

        return [
            'id' => (int)$this->pdo->lastInsertId(),
            'api_key' => $apiKey,
        ];
    }

    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, key_prefix, last_used_at, revoked_at, expires_at, created_at
             FROM api_keys
             WHERE user_id = :user_id
             ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT api_keys.id, api_keys.name, api_keys.key_prefix, api_keys.last_used_at,
                    api_keys.revoked_at, api_keys.expires_at, api_keys.created_at,
                    users.email, users.display_name
             FROM api_keys
             INNER JOIN users ON users.id = api_keys.user_id
             ORDER BY api_keys.created_at DESC'
        );

        return $stmt->fetchAll();
    }

    public function revokeForUser(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_keys
             SET revoked_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id AND revoked_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);
    }

    public function revoke(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_keys
             SET revoked_at = CURRENT_TIMESTAMP
             WHERE id = :id AND revoked_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }
}
