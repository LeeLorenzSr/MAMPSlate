<?php
declare(strict_types=1);

/**
 * Editable, non-secret site settings stored in the `settings` table.
 *
 * Values are strings. Secrets and environment-only values stay in config files.
 * `get()` falls back to config-derived defaults when a key is not present in the
 * database, so the system works before any setting is saved.
 */
final class SettingsRepository
{
    private ?array $cache = null;

    public function __construct(
        private PDO $pdo,
        private array $config
    ) {
    }

    public function get(string $key, $default = null)
    {
        $this->load();
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $fallback = $this->fallback($key);
        return $fallback !== null ? $fallback : $default;
    }

    public function all(): array
    {
        $this->load();
        return $this->cache;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = :v2'
        );
        $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
        $this->cache[$key] = $value;
    }

    public function setMany(array $keyValues): void
    {
        foreach ($keyValues as $key => $value) {
            $this->set((string)$key, (string)$value);
        }
    }

    private function load(): void
    {
        if ($this->cache !== null) {
            return;
        }
        $this->cache = [];
        $rows = $this->pdo->query('SELECT `key`, `value` FROM settings')->fetchAll();
        foreach ($rows as $row) {
            $this->cache[$row['key']] = $row['value'];
        }
    }

    private function fallback(string $key)
    {
        $app = $this->config['app'] ?? [];
        $features = $this->config['features'] ?? [];

        return match ($key) {
            'site.name' => $app['name'] ?? null,
            'site.tagline' => '',
            'default_meta_title' => '',
            'default_meta_description' => $app['default_meta_description'] ?? '',
            'signup_mode' => $app['signup_mode'] ?? 'off',
            'comments_require_approval' => !empty($app['comments_require_approval']) ? '1' : '0',
            'comments_per_minute' => (string)($app['comments_per_minute'] ?? 5),
            'media_max_upload_bytes' => (string)($app['media_max_upload_bytes'] ?? 5242880),
            'media_image_max_width' => (string)($app['media_image_max_width'] ?? 1600),
            'app.base_url' => $app['base_url'] ?? null,
            'contact_require_moderation' => '1',
            'theme.accent_color' => '#2f6fec',
            'theme.font_family' => 'montserrat',
            'theme.footer_text' => '',
            'theme.social_links' => '[]',
            default => str_starts_with($key, 'features.')
                ? (array_key_exists(substr($key, 9), $features) ? ($features[substr($key, 9)] ? '1' : '0') : '1')
                : null,
        };
    }
}
