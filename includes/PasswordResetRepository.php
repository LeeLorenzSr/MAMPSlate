<?php
declare(strict_types=1);

/**
 * Password reset token storage. Only token hashes are persisted; the plaintext
 * token is returned once at creation and embedded in the reset link.
 */
final class PasswordResetRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Create a reset token for a user. Returns the plaintext token (shown once).
     */
    public function create(int $userId, int $lifetimeMinutes): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetimeMinutes * 60);

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens
                (user_id, token_hash, expires_at, request_ip, request_user_agent)
             VALUES (:user_id, :token_hash, :expires_at, :ip, :ua)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]);

        return $token;
    }

    /**
     * Find a valid (unused, unexpired) token row by plaintext token.
     */
    public function findValid(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM password_reset_tokens
             WHERE token_hash = :hash
               AND used_at IS NULL
               AND expires_at > CURRENT_TIMESTAMP
             LIMIT 1'
        );
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markUsed(int $id): void
    {
        $this->pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = :id AND used_at IS NULL'
        )->execute(['id' => $id]);
    }

    /**
     * Invalidate all unused tokens for a user (e.g. after a successful reset).
     */
    public function invalidateForUser(int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = :id AND used_at IS NULL'
        )->execute(['id' => $userId]);
    }
}
