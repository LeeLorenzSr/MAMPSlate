<?php
declare(strict_types=1);

final class MenuRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function allMenus(): array
    {
        return $this->pdo->query(
            'SELECT * FROM menus ORDER BY location'
        )->fetchAll();
    }

    public function menuByLocation(string $location): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM menus WHERE location = :loc LIMIT 1');
        $stmt->execute(['loc' => $location]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function itemsForMenu(int $menuId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM menu_items WHERE menu_id = :menu_id ORDER BY sort_order, id'
        );
        $stmt->execute(['menu_id' => $menuId]);

        return $stmt->fetchAll();
    }

    /**
     * Active, ordered items for a menu location (e.g. 'header', 'footer').
     */
    public function itemsForLocation(string $location): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT menu_items.*
             FROM menu_items
             INNER JOIN menus ON menus.id = menu_items.menu_id
             WHERE menus.location = :loc AND menu_items.is_active = 1
             ORDER BY menu_items.sort_order, menu_items.id'
        );
        $stmt->execute(['loc' => $location]);

        return $stmt->fetchAll();
    }

    public function createItem(int $menuId, string $label, string $url, ?string $linkedType, ?int $linkedId, ?int $parentId, int $sortOrder, bool $isActive): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO menu_items
                (menu_id, label, url, linked_type, linked_id, parent_id, sort_order, is_active)
             VALUES (:menu_id, :label, :url, :linked_type, :linked_id, :parent_id, :sort_order, :is_active)'
        );
        $stmt->execute([
            'menu_id' => $menuId,
            'label' => trim($label),
            'url' => $this->sanitizeUrl($url),
            'linked_type' => $linkedType,
            'linked_id' => $linkedId,
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'is_active' => $isActive ? 1 : 0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateItem(int $id, string $label, string $url, ?int $parentId, int $sortOrder, bool $isActive): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE menu_items
             SET label = :label, url = :url, parent_id = :parent_id, sort_order = :sort_order, is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'label' => trim($label),
            'url' => $this->sanitizeUrl($url),
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    public function deleteItem(int $id): void
    {
        $this->pdo->prepare('DELETE FROM menu_items WHERE id = :id')->execute(['id' => $id]);
    }

    public function findItem(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM menu_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Only allow http(s) and same-origin relative URLs in menu links.
     */
    public function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }
        // Anything else (javascript:, data:, etc.) is discarded.
        return '';
    }
}
