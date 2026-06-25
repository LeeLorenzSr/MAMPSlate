<?php
declare(strict_types=1);

final class InviteCodeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Create an invite code. The plaintext code is returned once.
     *
     * @return array{id: int, code: string}
     */
    public function create(?int $createdBy, int $maxUses, ?string $expiresAt): array
    {
        $code = generateCredential('inv');
        $stmt = $this->pdo->prepare(
            'INSERT INTO invite_codes
                (code_hash, code_prefix, created_by_user_id, max_uses, expires_at)
             VALUES (:code_hash, :code_prefix, :created_by, :max_uses, :expires_at)'
        );
        $stmt->execute([
            'code_hash' => hashCredential($code),
            'code_prefix' => substr($code, 0, 16),
            'created_by' => $createdBy,
            'max_uses' => max(1, $maxUses),
            'expires_at' => $expiresAt,
        ]);

        return [
            'id' => (int)$this->pdo->lastInsertId(),
            'code' => $code,
        ];
    }

    public function listAll(): array
    {
        return $this->pdo->query(
            'SELECT invite_codes.id, invite_codes.code_prefix, invite_codes.max_uses,
                    invite_codes.uses, invite_codes.expires_at, invite_codes.revoked_at,
                    invite_codes.created_at, creator.display_name AS created_by_name
             FROM invite_codes
             LEFT JOIN users creator ON creator.id = invite_codes.created_by_user_id
             ORDER BY invite_codes.created_at DESC'
        )->fetchAll();
    }

    /**
     * Look up a code by its plaintext value. Returns the row or null.
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM invite_codes WHERE code_hash = :hash LIMIT 1'
        );
        $stmt->execute(['hash' => hashCredential($code)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function isValid(array $code): bool
    {
        if (!empty($code['revoked_at'])) {
            return false;
        }
        if ((int)$code['uses'] >= (int)$code['max_uses']) {
            return false;
        }
        if (!empty($code['expires_at']) && strtotime($code['expires_at']) <= time()) {
            return false;
        }

        return true;
    }

    public function consume(int $id): void
    {
        $this->pdo->prepare(
            'UPDATE invite_codes SET uses = uses + 1 WHERE id = :id'
        )->execute(['id' => $id]);
    }

    public function revoke(int $id): void
    {
        $this->pdo->prepare(
            'UPDATE invite_codes SET revoked_at = CURRENT_TIMESTAMP WHERE id = :id AND revoked_at IS NULL'
        )->execute(['id' => $id]);
    }
}
