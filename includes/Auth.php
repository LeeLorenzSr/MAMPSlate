<?php
declare(strict_types=1);

final class Auth
{
    public const SIGNUP_ROLE = 'user';
    public const MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        private UserRepository $users,
        private CapabilityRepository $capabilities
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if (!$user || !(bool)$user['is_active']) {
            return false;
        }

        // OAuth-only accounts have no password and cannot log in this way.
        if ($user['password_hash'] === null) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        $this->loginById((int)$user['id']);

        return true;
    }

    /**
     * Regenerate the session and mark the user as authenticated.
     */
    public function loginById(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $this->users->touchLastLogin($userId);
    }

    /**
     * Create a new password account.
     *
     * @param bool $active Whether the account is immediately active (false for
     *                     restricted/signup-on-hold mode).
     * @param bool $login  Whether to authenticate the user immediately (false
     *                     when the account is inactive or pending approval).
     * @return int The new user id.
     * @throws RuntimeException When validation fails.
     */
    public function signup(string $email, string $displayName, string $password, bool $active = true, bool $login = true): int
    {
        $email = strtolower(trim($email));
        $displayName = trim($displayName);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please provide a valid email address.');
        }
        if ($displayName === '') {
            throw new RuntimeException('Please provide a display name.');
        }
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new RuntimeException(sprintf('Password must be at least %d characters.', self::MIN_PASSWORD_LENGTH));
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new RuntimeException('An account with that email already exists.');
        }

        $roleId = $this->users->findRoleIdByName(self::SIGNUP_ROLE);
        if ($roleId === null) {
            throw new RuntimeException('Default user role is not configured.');
        }

        $userId = $this->users->createUser($email, $displayName, $roleId, $password, $active);
        if ($login && $active) {
            $this->loginById($userId);
        }

        return $userId;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $user = $this->users->findById((int)$_SESSION['user_id']);
        if (!$user || !(bool)$user['is_active']) {
            $this->logout();
            return null;
        }

        // Attach the role's capability names so callers can call can().
        $user['_capabilities'] = $this->capabilities->capabilitiesForRole((int)$user['role_id']);

        return $user;
    }

    /**
     * Check the current user has a capability.
     */
    public function can(string $capability): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return in_array($capability, $user['_capabilities'] ?? [], true);
    }

    /**
     * Any capability that grants access to the admin area / dashboard.
     */
    public function canAccessAdmin(): bool
    {
        foreach (self::ADMIN_CAPABILITIES as $cap) {
            if ($this->can($cap)) {
                return true;
            }
        }
        return false;
    }

    public const ADMIN_CAPABILITIES = [
        'article.create', 'page.create', 'user.manage', 'role.manage',
        'apikey.manage', 'media.upload', 'comment.moderate', 'audit.view',
        'menu.manage', 'settings.manage',
    ];

    /**
     * Require the current user to have a capability.
     */
    public function requireCapability(string $capability): array
    {
        $user = $this->requireLogin();
        if (!$this->can($capability)) {
            http_response_code(403);
            exit('Forbidden');
        }
        prevent_caching();
        return $user;
    }

    public function requireLogin(): array
    {
        $user = $this->user();
        if (!$user) {
            redirect('/');
        }
        prevent_caching();
        return $user;
    }

    /**
     * Backward-compatible role check. New code should use requireCapability().
     */
    public function requireRole(string $role): array
    {
        $user = $this->requireLogin();
        if ($user['role_name'] !== $role) {
            http_response_code(403);
            exit('Forbidden');
        }

        return $user;
    }
}
