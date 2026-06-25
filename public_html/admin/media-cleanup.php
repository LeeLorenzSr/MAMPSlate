<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('media.upload');
$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_row') {
        $mediaId = (int)($_POST['media_id'] ?? 0);
        $item = $media->findById($mediaId);
        if (!$item) {
            $error = 'Media not found.';
        } elseif ($mediaUsage->inUse($mediaId, $item['stored_name'])) {
            $error = 'That media is now in use and cannot be removed here.';
        } else {
            $storedName = $media->delete($mediaId);
            if ($storedName !== null) {
                $fullPath = $uploadsRoot . '/' . $storedName;
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
            $audit->log('media.orphan_deleted', (int)$currentUser['id'], 'media', (string)$mediaId);
            $message = 'Orphan media removed.';
        }
    }

    if ($action === 'delete_disk') {
        $rel = (string)($_POST['path'] ?? '');
        // Validate the path stays within uploads and is a real file.
        $full = $uploadsRoot . '/' . $rel;
        $realUploads = realpath($uploadsRoot);
        $realFile = realpath($full);
        if ($realFile === false || $realUploads === false || !str_starts_with($realFile, $realUploads) || !is_file($realFile)) {
            $error = 'Invalid file path.';
        } elseif (in_array(basename($rel), ['.htaccess', '.gitkeep'], true)) {
            $error = 'Refusing to delete protected file.';
        } else {
            @unlink($realFile);
            $audit->log('media.disk_orphan_deleted', (int)$currentUser['id'], 'file', $rel);
            $message = 'Orphan file removed.';
        }
    }
}

$orphanRows = $mediaUsage->orphanRows();
$orphanFiles = $mediaUsage->orphanDiskFiles($uploadsRoot);

renderHeader('Media cleanup', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel">
    <h2>Orphaned media rows (not referenced)</h2>
    <?php if (!$orphanRows): ?>
        <p class="muted">No orphaned media rows.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>File</th><th>Created</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($orphanRows as $row): ?>
                        <tr>
                            <td><code>/uploads/<?= e($row['stored_name']) ?></code><br><?= e($row['original_name']) ?></td>
                            <td><?= e($row['created_at']) ?></td>
                            <td>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete_row">
                                    <input type="hidden" name="media_id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" class="danger" data-confirm="Delete this orphan media row and its file?">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Orphaned files on disk (no media row)</h2>
    <p class="muted">Includes uploaded files that have no database row. Avatar files (<code>profilepics/</code>) are kept unless you delete them here.</p>
    <?php if (!$orphanFiles): ?>
        <p class="muted">No orphaned files on disk.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Path</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($orphanFiles as $rel): ?>
                        <tr>
                            <td><code>/uploads/<?= e($rel) ?></code></td>
                            <td>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete_disk">
                                    <input type="hidden" name="path" value="<?= e($rel) ?>">
                                    <button type="submit" class="danger" data-confirm="Delete this orphan file from disk?">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<p><a href="/admin/media">← Back to media library</a></p>
<?php renderFooter();
