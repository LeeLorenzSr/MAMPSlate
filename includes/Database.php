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

        return new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
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
