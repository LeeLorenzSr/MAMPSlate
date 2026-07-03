<?php
declare(strict_types=1);

final class Database
{
    public static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int)$config['port'],
            $config['name'],
            $config['charset']
        );

        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Align MySQL's session timezone with PHP's so CURRENT_TIMESTAMP and
        // date() agree. Without this, a just-published article (published_at set
        // via PHP) can read as "in the future" to a `published_at <= CURRENT_TIMESTAMP`
        // filter when the two clocks use different zones, hiding it from listings.
        try {
            $pdo->exec('SET time_zone = ' . $pdo->quote(date('P')));
        } catch (Throwable $e) {
            // Non-fatal: some hosts disallow setting session time_zone.
        }

        return $pdo;
    }

    /**
     * Whether the core schema has been installed (the `user_roles` table exists).
     */
    public static function schemaInstalled(PDO $pdo): bool
    {
        try {
            $row = $pdo->query("SHOW TABLES LIKE 'user_roles'")->fetch();
            return $row !== false;
        } catch (Throwable $e) {
            return false;
        }
    }
}
