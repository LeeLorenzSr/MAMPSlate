<?php
declare(strict_types=1);

final class ApiAuth
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $users,
        private int $sessionLifetimeMinutes
    ) {
    }

    public function authenticateRequest(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (str_starts_with($header, 'Bearer ')) {
            return $this->authenticateApiKey(substr($header, 7));
        }

        if (str_starts_with($header, 'Session ')) {
            return $this->authenticateSessionKey(substr($header, 8));
        }

        return null;
    }

    /**
     * Revoke all active temporal session keys for a user (e.g. on password reset).
     */
    public function revokeSessionsForUser(int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE user_sessions SET revoked_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND revoked_at IS NULL'
        )->execute(['user_id' => $userId]);
    }

    public function issueSessionKey(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);
        if (!$user || !(bool)$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $sessionKey = generateCredential('sess');
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . $this->sessionLifetimeMinutes . ' minutes');

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_sessions
                (user_id, session_key_hash, source, ip_address, user_agent, expires_at)
             VALUES
                (:user_id, :session_key_hash, :source, :ip_address, :user_agent, :expires_at)'
        );
        $stmt->execute([
            'user_id' => (int)$user['id'],
            'session_key_hash' => hashCredential($sessionKey),
            'source' => 'api',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return [
            'session_key' => $sessionKey,
            'expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
        ];
    }

    private function authenticateApiKey(string $apiKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT api_keys.id AS api_key_id, api_keys.user_id
             FROM api_keys
             WHERE api_keys.key_hash = :key_hash
               AND api_keys.revoked_at IS NULL
               AND (api_keys.expires_at IS NULL OR api_keys.expires_at > CURRENT_TIMESTAMP)'
        );
        $stmt->execute(['key_hash' => hashCredential($apiKey)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $this->pdo->prepare('UPDATE api_keys SET last_used_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute(['id' => (int)$row['api_key_id']]);

        return $this->users->findById((int)$row['user_id']);
    }

    private function authenticateSessionKey(string $sessionKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_sessions.id AS session_id, user_sessions.user_id
             FROM user_sessions
             WHERE user_sessions.session_key_hash = :session_key_hash
               AND user_sessions.revoked_at IS NULL
               AND user_sessions.expires_at > CURRENT_TIMESTAMP'
        );
        $stmt->execute(['session_key_hash' => hashCredential($sessionKey)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $this->pdo->prepare('UPDATE user_sessions SET last_used_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute(['id' => (int)$row['session_id']]);

        return $this->users->findById((int)$row['user_id']);
    }
}
