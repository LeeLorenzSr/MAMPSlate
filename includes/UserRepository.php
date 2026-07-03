<?php
declare(strict_types=1);

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.*, user_roles.name AS role_name
             FROM users
             INNER JOIN user_roles ON user_roles.id = users.role_id
             WHERE users.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.*, user_roles.name AS role_name
             FROM users
             INNER JOIN user_roles ON user_roles.id = users.role_id
             WHERE users.slug = :slug'
        );
        $stmt->execute(['slug' => $slug]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.*, user_roles.name AS role_name
             FROM users
             INNER JOIN user_roles ON user_roles.id = users.role_id
             WHERE users.email = :email'
        );
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function allUsers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT users.id, users.email, users.display_name, users.avatar, users.is_active,
                    users.last_login_at, users.created_at, user_roles.name AS role_name
             FROM users
             INNER JOIN user_roles ON user_roles.id = users.role_id
             ORDER BY users.created_at DESC'
        );

        return $stmt->fetchAll();
    }

    public function allRoles(): array
    {
        return $this->pdo->query('SELECT id, name FROM user_roles ORDER BY name')->fetchAll();
    }

    public function recent(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.id, users.email, users.display_name, users.is_active,
                    users.created_at, user_roles.name AS role_name
             FROM users
             INNER JOIN user_roles ON user_roles.id = users.role_id
             ORDER BY users.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function searchAdmin(string $query): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT users.id, users.email, users.display_name, users.is_active,
                    users.created_at, user_roles.name AS role_name
             FROM users
             INNER JOIN user_roles ON user_roles.id = users.role_id
             WHERE users.email LIKE :q1 OR users.display_name LIKE :q2
             ORDER BY users.created_at DESC LIMIT 50'
        );
        $stmt->execute(['q1' => $like, 'q2' => $like]);

        return $stmt->fetchAll();
    }

    public function findRoleIdByName(string $name): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM user_roles WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();

        return $row ? (int)$row['id'] : null;
    }

    public function createUser(string $email, string $displayName, int $roleId, string $password, bool $active = true): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, display_name, slug, role_id, password_hash, is_active)
             VALUES (:email, :display_name, :slug, :role_id, :password_hash, :is_active)'
        );
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'display_name' => trim($displayName),
            'slug' => $this->generateUniqueSlug($displayName),
            'role_id' => $roleId,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => $active ? 1 : 0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateUser(int $id, string $displayName, int $roleId, bool $isActive): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET display_name = :display_name, role_id = :role_id, is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'display_name' => trim($displayName),
            'role_id' => $roleId,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    public function setPassword(int $id, string $password): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET password_hash = :password_hash
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public function setAvatar(int $id, ?string $avatar): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET avatar = :avatar WHERE id = :id');
        $stmt->execute(['id' => $id, 'avatar' => $avatar]);
    }

    public function setCoverPhoto(int $id, ?string $coverPhoto): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET cover_photo = :cover_photo WHERE id = :id');
        $stmt->execute(['id' => $id, 'cover_photo' => $coverPhoto]);
    }

    /**
     * Whether a slug is already taken by another user.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude';
            $params['exclude'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Derive a unique slug from a display name.
     */
    public function generateUniqueSlug(string $displayName): string
    {
        return Slug::ensureUnique(
            fn(string $candidate): bool => $this->slugExists($candidate),
            Slug::slugify($displayName)
        );
    }

    /**
     * Ensure a user has a slug, generating one from their display name if not.
     * Returns the slug. Idempotent.
     */
    public function ensureSlug(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT slug, display_name FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if ($row['slug'] !== null && $row['slug'] !== '') {
            return $row['slug'];
        }

        $slug = $this->generateUniqueSlug($row['display_name']);
        $upd = $this->pdo->prepare('UPDATE users SET slug = :slug WHERE id = :id AND (slug IS NULL OR slug = "")');
        $upd->execute(['slug' => $slug, 'id' => $id]);

        return $slug;
    }

    /**
     * Backfill slugs for every user missing one, deriving each from the
     * display name. Used for the one-time migration of pre-existing accounts.
     * Returns null when the slug column is not installed yet (migration 017
     * pending) so the caller knows to retry; returns the count backfilled
     * otherwise (0 when there was nothing to do). Safe to re-run: only rows
     * with a NULL/empty slug are touched, and each derived slug is made unique
     * against the current table state.
     */
    public function backfillSlugs(): ?int
    {
        $hasColumn = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'users'
               AND column_name = 'slug'"
        )->fetchColumn();
        if ($hasColumn === 0) {
            return null;
        }

        $rows = $this->pdo->query(
            "SELECT id, display_name FROM users WHERE slug IS NULL OR slug = ''"
        )->fetchAll();

        $upd = $this->pdo->prepare(
            'UPDATE users SET slug = :slug WHERE id = :id AND (slug IS NULL OR slug = "")'
        );

        $count = 0;
        foreach ($rows as $row) {
            $slug = $this->generateUniqueSlug($row['display_name']);
            $upd->execute(['slug' => $slug, 'id' => (int)$row['id']]);
            $count++;
        }

        return $count;
    }

    /**
     * Update self-service profile fields: bio, slug, social links, and the
     * email-privacy toggle.
     */
    public function updateProfile(
        int $id,
        string $slug,
        string $bio,
        string $socialGithub,
        string $socialLinkedin,
        string $socialWebsite,
        bool $hideEmail
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET slug = :slug,
                 bio = :bio,
                 social_github = :social_github,
                 social_linkedin = :social_linkedin,
                 social_website = :social_website,
                 hide_email = :hide_email
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'slug' => $slug,
            'bio' => $bio,
            'social_github' => $socialGithub,
            'social_linkedin' => $socialLinkedin,
            'social_website' => $socialWebsite,
            'hide_email' => $hideEmail ? 1 : 0,
        ]);
    }

    public function touchLastLogin(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Create a user with no password (OAuth-only account).
     */
    public function createOAuthUser(string $email, string $displayName, int $roleId): int
    {
        $displayName = trim($displayName) !== '' ? trim($displayName) : $email;
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, display_name, slug, role_id, password_hash, is_active)
             VALUES (:email, :display_name, :slug, :role_id, NULL, 1)'
        );
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'display_name' => $displayName,
            'slug' => $this->generateUniqueSlug($displayName),
            'role_id' => $roleId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Find a user by a linked OAuth identity.
     */
    public function findOAuthIdentity(string $provider, string $providerUserId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.*, user_roles.name AS role_name
             FROM user_oauth_identities
             INNER JOIN users ON users.id = user_oauth_identities.user_id
             INNER JOIN user_roles ON user_roles.id = users.role_id
             WHERE user_oauth_identities.provider = :provider
               AND user_oauth_identities.provider_user_id = :provider_user_id'
        );
        $stmt->execute([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function linkOAuthIdentity(int $userId, string $provider, string $providerUserId, string $email, string $displayName): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO user_oauth_identities
                (user_id, provider, provider_user_id, email, display_name)
             VALUES (:user_id, :provider, :provider_user_id, :email, :display_name)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => strtolower(trim($email)),
            'display_name' => trim($displayName),
        ]);
    }
}
