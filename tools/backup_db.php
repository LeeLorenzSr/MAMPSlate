<?php
/**
 * CLI database backup helper.
 *
 * Reads credentials from config/config.local.php and writes a gzipped mysqldump
 * to backups/. Not a web route — run from the CLI:  php tools/backup_db.php
 *
 * Requires the `mysqldump` binary on the PATH.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$configPath = $root . '/config/config.local.php';
if (!is_file($configPath)) {
    $configPath = $root . '/config/config.example.php';
}
$config = require $configPath;
$db = $config['database'];

$backupsDir = $root . '/backups';
if (!is_dir($backupsDir)) {
    mkdir($backupsDir, 0775, true);
}

$outFile = $backupsDir . '/db_' . date('Ymd_His') . '.sql.gz';

$escaped = static function (string $v): string {
    return escapeshellarg($v);
};

$cmd = sprintf(
    'mysqldump --host=%s --port=%d --user=%s --password=%s --single-transaction --no-tablespaces --default-character-set=%s %s 2>&1 | gzip > %s',
    $escaped($db['host']),
    (int)$db['port'],
    $escaped($db['user']),
    $escaped($db['password']),
    $escaped($db['charset'] ?? 'utf8mb4'),
    $escaped($db['name']),
    $escaped($outFile)
);

fwrite(STDOUT, "Running mysqldump -> {$outFile}\n");
exec($cmd, $output, $rc);

if ($rc !== 0 || !is_file($outFile) || filesize($outFile) === 0) {
    fwrite(STDERR, "Backup failed (exit {$rc}). Is mysqldump on the PATH?\n");
    if ($output) {
        fwrite(STDERR, implode("\n", $output) . "\n");
    }
    @unlink($outFile);
    exit(1);
}

fwrite(STDOUT, "Backup written: {$outFile} (" . number_format(filesize($outFile)) . " bytes)\n");
