<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('media');

$currentUser = $auth->requireCapability('media.upload');
$message = null;
$error = null;

$maxBytes = (int)setting('media_max_upload_bytes', $config['app']['media_max_upload_bytes'] ?? 5242880);
$maxWidth = (int)setting('media_image_max_width', $config['app']['media_image_max_width'] ?? 1600);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'upload') {
            if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Choose an image to upload.');
            }
            if ((int)($_FILES['file']['size'] ?? 0) > $maxBytes) {
                throw new RuntimeException('The file is larger than the maximum allowed size.');
            }

            $meta = $mediaUpload->process($_FILES['file'], $maxWidth);
            $mediaId = $media->create(
                (int)$currentUser['id'],
                $meta['stored_name'],
                $meta['original_name'],
                $meta['mime_type'],
                $meta['file_size'],
                $meta['width'],
                $meta['height']
            );
            $message = 'Media uploaded.';
        }

        if ($action === 'update') {
            $media->updateMeta(
                (int)($_POST['media_id'] ?? 0),
                (string)($_POST['alt_text'] ?? ''),
                (string)($_POST['title'] ?? '')
            );
            $message = 'Media updated.';
        }

        if ($action === 'delete') {
            $mediaId = (int)($_POST['media_id'] ?? 0);
            $item = $media->findById($mediaId);
            $force = isset($_POST['force']);
            if ($item && !$force && $mediaUsage->inUse($mediaId, $item['stored_name'])) {
                $error = 'This media is in use. Confirm the "force delete" checkbox to delete it anyway.';
            } else {
                $storedName = $media->delete($mediaId);
                if ($storedName !== null) {
                    $fullPath = $uploadsRoot . '/' . $storedName;
                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }
                $audit->log('media.deleted', (int)$currentUser['id'], 'media', (string)$mediaId, ['forced' => $force ? '1' : '0']);
                $message = 'Media deleted.';
            }
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$allMedia = $media->listAll();
// Precompute usage summaries.
$usageSummary = [];
foreach ($allMedia as $item) {
    $uses = $mediaUsage->usages((int)$item['id'], $item['stored_name']);
    $usageSummary[(int)$item['id']] = $uses === [] ? 'Not in use' : implode('; ', array_map(fn($u) => $u['type'] . ' ×' . $u['count'], $uses));
}

renderHeader('Media library', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel">
    <h2>Upload media</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="upload">
        <label>
            Image, or enabled document/audio/video file (max <?= number_format($maxBytes) ?> bytes)
            <input type="file" name="file" accept="image/*,application/pdf,audio/*,video/*" required>
        </label>
        <button type="submit">Upload</button>
    </form>
</section>

<section class="panel">
    <h2>Media</h2>
    <?php if (!$allMedia): ?>
        <p class="muted">No media yet.</p>
    <?php endif; ?>
    <div class="media-grid">
        <?php foreach ($allMedia as $item): ?>
            <div class="media-card">
                <?php if (str_starts_with((string)$item['mime_type'], 'image/')): ?>
                    <a href="/uploads/<?= e($item['stored_name']) ?>" target="_blank"><img src="/uploads/<?= e($item['stored_name']) ?>" alt="<?= e($item['alt_text']) ?>" loading="lazy"></a>
                <?php elseif (str_starts_with((string)$item['mime_type'], 'audio/')): ?>
                    <audio controls src="/uploads/<?= e($item['stored_name']) ?>"></audio>
                <?php elseif (str_starts_with((string)$item['mime_type'], 'video/')): ?>
                    <video controls preload="metadata" src="/uploads/<?= e($item['stored_name']) ?>"></video>
                <?php else: ?>
                    <p><a href="/uploads/<?= e($item['stored_name']) ?>" target="_blank"><i class="bi bi-file-earmark"></i> <?= e($item['original_name']) ?></a></p>
                <?php endif; ?>
                <form method="post" class="media-meta-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="media_id" value="<?= (int)$item['id'] ?>">
                    <label>
                        Alt text
                        <input type="text" name="alt_text" value="<?= e($item['alt_text']) ?>" maxlength="255">
                    </label>
                    <label>
                        Title
                        <input type="text" name="title" value="<?= e($item['title']) ?>" maxlength="255">
                    </label>
                    <div class="media-url">
                        <code>/uploads/<?= e($item['stored_name']) ?></code>
                    </div>
                    <p class="muted media-usage"><?= e($usageSummary[(int)$item['id']] ?? '') ?></p>
                    <button type="submit">Save</button>
                </form>
                <form method="post" class="media-delete-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="media_id" value="<?= (int)$item['id'] ?>">
                    <?php if (($usageSummary[(int)$item['id']] ?? '') !== 'Not in use'): ?>
                        <label class="inline">
                            <input type="checkbox" name="force" value="1"> Force delete (in use)
                        </label>
                    <?php endif; ?>
                    <button type="submit" class="danger" data-confirm="Delete this image? This cannot be undone.">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<p><a href="/admin/media-cleanup">Orphan cleanup tool →</a></p>
<?php renderFooter();
