<?php
declare(strict_types=1);

/**
 * Idempotent SQL migration runner.
 *
 * Tracks applied migrations in a `schema_migrations` table. Existing migrations
 * are written to be idempotent (`CREATE TABLE IF NOT EXISTS`,
 * `ON DUPLICATE KEY UPDATE`), so re-running them on an already-initialized
 * database is safe; the runner records each as applied so it is not run again.
 *
 * The destructive dev-only `000_reset_dev.sql` is NEVER run by this runner.
 */
final class MigrationRunner
{
    public function __construct(
        private PDO $pdo,
        private string $sqlDir
    ) {
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                filename VARCHAR(255) NOT NULL PRIMARY KEY,
                hash CHAR(64) NOT NULL DEFAULT "",
                status VARCHAR(20) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                error TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * All migration files in order, excluding the dev reset.
     *
     * @return string[] absolute paths
     */
    public function migrationFiles(): array
    {
        $files = glob($this->sqlDir . '/*.sql') ?: [];
        $files = array_filter($files, fn($f) => !str_starts_with(basename($f), '000'));
        sort($files);

        return array_values($files);
    }

    /**
     * Filenames already recorded as applied (success).
     *
     * @return string[] filenames
     */
    public function appliedFiles(): array
    {
        $this->ensureTable();
        $rows = $this->pdo->query(
            "SELECT filename FROM schema_migrations WHERE status = 'success'"
        )->fetchAll(PDO::FETCH_COLUMN);

        return $rows ?: [];
    }

    /**
     * Per-file status (filename => ['status' => ..., 'applied_at' => ...]).
     */
    public function statusMap(): array
    {
        $this->ensureTable();
        $rows = $this->pdo->query(
            'SELECT filename, status, applied_at, error FROM schema_migrations'
        )->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['filename']] = $r;
        }
        return $map;
    }

    /**
     * Apply all pending migrations in order. Returns per-file results.
     *
     * @return array<int, array{file: string, ok: bool, error?: string, applied: bool}>
     */
    public function runPending(): array
    {
        $this->ensureTable();
        $applied = $this->appliedFiles();
        $results = [];

        foreach ($this->migrationFiles() as $path) {
            $filename = basename($path);

            if (in_array($filename, $applied, true)) {
                $results[] = ['file' => $filename, 'ok' => true, 'applied' => false];
                continue;
            }

            $sql = file_get_contents($path);
            if ($sql === false) {
                $this->record($filename, '', 'failed', 'Could not read file.');
                $results[] = ['file' => $filename, 'ok' => false, 'applied' => true, 'error' => 'Could not read file.'];
                break;
            }

            try {
                $this->pdo->exec($sql);
                $this->record($filename, hash('sha256', $sql), 'success', null);
                $results[] = ['file' => $filename, 'ok' => true, 'applied' => true];
            } catch (Throwable $e) {
                $this->record($filename, hash('sha256', $sql), 'failed', $e->getMessage());
                $results[] = ['file' => $filename, 'ok' => false, 'applied' => true, 'error' => $e->getMessage()];
                break;
            }
        }

        return $results;
    }

    private function record(string $filename, string $hash, string $status, ?string $error): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO schema_migrations (filename, hash, status, error)
             VALUES (:filename, :hash, :status, :error)
             ON DUPLICATE KEY UPDATE hash = VALUES(hash), status = VALUES(status),
                 applied_at = CURRENT_TIMESTAMP, error = VALUES(error)'
        );
        $stmt->execute([
            'filename' => $filename,
            'hash' => $hash,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
