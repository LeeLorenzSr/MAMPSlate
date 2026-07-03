<?php
declare(strict_types=1);

final class CapabilityRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function allCapabilities(): array
    {
        return $this->pdo->query(
            'SELECT id, name, description FROM capabilities ORDER BY name'
        )->fetchAll();
    }

    /**
     * @return string[] capability names granted to the role.
     */
    public function capabilitiesForRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT capabilities.name
             FROM role_capabilities
             INNER JOIN capabilities ON capabilities.id = role_capabilities.capability_id
             WHERE role_capabilities.role_id = :role_id'
        );
        $stmt->execute(['role_id' => $roleId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function roleHas(int $roleId, string $capability): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM role_capabilities
             INNER JOIN capabilities ON capabilities.id = role_capabilities.capability_id
             WHERE role_capabilities.role_id = :role_id
               AND capabilities.name = :capability
             LIMIT 1'
        );
        $stmt->execute(['role_id' => $roleId, 'capability' => $capability]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * @return int[] capability IDs granted to the role. Use this (not
     *               capabilitiesForRole, which returns names) when you need IDs
     *               for form state.
     */
    public function capabilityIdsForRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT capability_id FROM role_capabilities WHERE role_id = :role_id'
        );
        $stmt->execute(['role_id' => $roleId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Replace the set of capabilities granted to a role.
     *
     * @param int[] $capabilityIds
     */
    public function syncRole(int $roleId, array $capabilityIds): void
    {
        $this->pdo->prepare('DELETE FROM role_capabilities WHERE role_id = :role_id')
            ->execute(['role_id' => $roleId]);

        $capabilityIds = array_filter(array_map('intval', $capabilityIds));
        if ($capabilityIds === []) {
            return;
        }

        $placeholders = [];
        $params = [];
        foreach ($capabilityIds as $capId) {
            $placeholders[] = '(?, ?)';
            $params[] = $roleId;
            $params[] = $capId;
        }

        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO role_capabilities (role_id, capability_id) VALUES ' . implode(', ', $placeholders)
        );
        $stmt->execute($params);
    }
}
