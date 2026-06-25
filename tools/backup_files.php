<?php
/**
 * CLI file backup helper.
 *
 * Tars public_html/uploads + the secret config files into backups/.
 * Run from the CLI:  php tools/backup_files.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$backupsDir = $root . '/backups';
if (!is_dir($backupsDir)) {
    mkdir($backupsDir, 0775, true);
}

$stamp = date('Ymd_His');
$tarPath = $backupsDir . '/files_' . $stamp . '.tar';

$paths = [
    'public_html/uploads',
];
foreach (['config/config.local.php', 'config/sitemaster.hash'] as $secret) {
    if (is_file($root . '/' . $secret)) {
        $paths[] = $secret;
    }
}

try {
    $tar = new PharData($tarPath);
    foreach ($paths as $rel) {
        $full = $root . '/' . $rel;
        if (is_dir($full)) {
            $tar->buildFromDirectory($full, '/.+/');
        } elseif (is_file($full)) {
            $tar->addFile($full, $rel);
        }
    }
    $tar->compress(Phar::GZ); // writes files_<stamp>.tar.gz next to the .tar
    unlink($tarPath);          // remove the uncompressed .tar

    $outFile = $tarPath . '.gz';
} catch (Throwable $e) {
    @unlink($tarPath);
    fwrite(STDERR, "Backup failed: " . $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "File backup written: {$outFile} (" . number_format(filesize($outFile)) . " bytes)\n");
