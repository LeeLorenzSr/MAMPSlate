<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireLogin();

$type = $_GET['type'] ?? '';
$contentId = (int)($_GET['id'] ?? 0);
$viewRevisionId = (int)($_GET['view'] ?? 0);
$message = null;
$error = null;

if (!in_array($type, ['article', 'page'], true)) {
    $type = '';
}

// Resolve content + edit permission.
$content = null;
$canEdit = false;
if ($type === 'article' && $contentId > 0) {
    requireFeature('articles');
    $content = $articles->findById($contentId);
} elseif ($type === 'page' && $contentId > 0) {
    requireFeature('pages');
    $content = $pages->findById($contentId);
}

if ($content) {
    $capAny = $type === 'article' ? 'article.edit.any' : 'page.edit.any';
    $capOwn = $type === 'article' ? 'article.edit.own' : 'page.edit.own';
    $canEdit = $auth->can($capAny)
        || ($auth->can($capOwn) && (int)$content['author_user_id'] === (int)$currentUser['id']);
}

// Restore.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    if (($_POST['action'] ?? '') === 'restore' && $canEdit) {
        $rev = $revisions->findById((int)($_POST['revision_id'] ?? 0));
        if (!$rev || $rev['content_type'] !== $type || (int)$rev['content_id'] !== $contentId) {
            $error = 'Revision not found.';
        } else {
            $snap = json_decode((string)$rev['snapshot'], true);
            if (is_array($snap)) {
                restore_snapshot($type, $contentId, $content, $snap);
                $revisions->createRevision($type, $contentId, (int)$currentUser['id'], $snap, 'Restored revision #' . (int)$rev['revision_number']);
                $audit->log($type . '.restored', (int)$currentUser['id'], $type, (string)$contentId, ['revision' => (int)$rev['revision_number']]);
                $message = 'Restored revision #' . (int)$rev['revision_number'] . '. A new revision was recorded.';
            } else {
                $error = 'Revision snapshot was corrupt.';
            }
        }
    }
}

$history = ($type && $contentId) ? $revisions->listRevisions($type, $contentId) : [];
$viewRevision = $viewRevisionId ? $revisions->findById($viewRevisionId) : null;

renderHeader('Revisions', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<?php if (!$content): ?>
    <p class="muted">No content selected. Use the revisions link from an article or page editor.</p>
<?php else: ?>
    <p>Revisions for <strong><?= e($content['title']) ?></strong> (<?= e($type) ?> #<?= (int)$contentId ?>).</p>

    <?php if ($viewRevision): ?>
        <?php $snap = json_decode((string)$viewRevision['snapshot'], true) ?: []; ?>
        <section class="panel">
            <h2>Revision #<?= (int)$viewRevision['revision_number'] ?>
                <span class="muted">— <?= e($viewRevision['changed_at']) ?> by <?= e($viewRevision['changed_by_name'] ?? '—') ?></span></h2>
            <dl class="details">
                <dt>Title</dt><dd><?= e((string)($snap['title'] ?? '')) ?></dd>
                <dt>Slug</dt><dd><?= e((string)($snap['slug'] ?? '')) ?></dd>
                <dt>Status</dt><dd><?= e((string)($snap['status'] ?? '')) ?></dd>
                <?php if ($type === 'article'): ?>
                    <dt>Tags</dt><dd><?= e(implode(', ', $snap['tags'] ?? [])) ?></dd>
                <?php endif; ?>
                <dt>Summary</dt><dd><?= e((string)($snap['summary'] ?? '')) ?></dd>
            </dl>
            <?php if ($canEdit): ?>
                <form method="post" class="space-top">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="revision_id" value="<?= (int)$viewRevision['id'] ?>">
                    <button type="submit" class="danger" data-confirm="Restore this revision? The current state will be saved as a new revision.">Restore this revision</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h2>History</h2>
        <?php if (!$history): ?>
            <p class="muted">No revisions recorded.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Changed by</th><th>When</th><th>Note</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?= (int)$h['revision_number'] ?></td>
                                <td><?= e($h['changed_by_name'] ?? '—') ?></td>
                                <td><?= e($h['changed_at']) ?></td>
                                <td><?= e($h['change_note'] ?? '') ?></td>
                                <td><a href="/admin/revisions?type=<?= e($type) ?>&id=<?= (int)$contentId ?>&view=<?= (int)$h['id'] ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php renderFooter();

function restore_snapshot(string $type, int $contentId, array $content, array $snap): void
{
    global $articles, $pages, $markdown;
    $bodyMarkdown = (string)($snap['body_markdown'] ?? '');
    $data = [
        'title' => (string)($snap['title'] ?? ''),
        'slug' => (string)($snap['slug'] ?? ''),
        'summary' => $snap['summary'] ?? null,
        'body_markdown' => $bodyMarkdown,
        'body_html' => $markdown->render($bodyMarkdown),
        'status' => (string)($snap['status'] ?? 'draft'),
        'author_user_id' => (int)$content['author_user_id'],
        'cover_media_id' => $snap['cover_media_id'] ?? null,
        'meta_title' => (string)($snap['meta_title'] ?? ''),
        'meta_description' => (string)($snap['meta_description'] ?? ''),
        'published_at' => $snap['published_at'] ?? null,
    ];

    if ($type === 'article') {
        $data['category_id'] = $snap['category_id'] ?? null;
        $articles->updateArticle($contentId, $data);
        $articles->syncTags($contentId, $snap['tags'] ?? []);
    } else {
        $pages->updatePage($contentId, $data);
    }
}
