<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('pages');

$currentUser = $auth->user();
if (!$currentUser) {
    redirect('/');
}

$id = (int)($_GET['id'] ?? 0);
$isNew = $id === 0;
$canPublish = $auth->can('page.publish');

if ($isNew) {
    $auth->requireCapability('page.create');
    $page = [
        'id' => 0, 'title' => '', 'slug' => '', 'summary' => '', 'body_markdown' => '',
        'status' => 'draft', 'cover_media_id' => null,
        'meta_title' => '', 'meta_description' => '', 'published_at' => null,
        'author_user_id' => (int)$currentUser['id'],
    ];
} else {
    $page = $pages->findById($id);
    if (!$page) {
        http_response_code(404);
        exit('Page not found.');
    }
    $canEdit = $auth->can('page.edit.any')
        || ($auth->can('page.edit.own') && (int)$page['author_user_id'] === (int)$currentUser['id']);
    if (!$canEdit) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();

    try {
        $title = trim((string)($_POST['title'] ?? ''));
        $bodyMarkdown = (string)($_POST['body_markdown'] ?? '');
        if ($title === '' || trim($bodyMarkdown) === '') {
            throw new RuntimeException('Title and body are required.');
        }

        $slug = Slug::slugify(trim((string)($_POST['slug'] ?? '')) ?: $title);
        $slug = Slug::ensureUnique(fn($s, $exclude) => $pages->slugExists($s, $exclude), $slug, $isNew ? null : $id);

        $coverMediaId = (int)($_POST['cover_media_id'] ?? 0);
        $coverMediaId = $coverMediaId > 0 ? $coverMediaId : null;

        $status = (string)($_POST['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            $status = 'draft';
        }
        if ($status === 'published' && !$canPublish) {
            $status = 'draft';
        }

        $publishedAt = $isNew ? null : $page['published_at'];
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $data = [
            'title' => $title,
            'slug' => $slug,
            'summary' => trim((string)($_POST['summary'] ?? '')) ?: null,
            'body_markdown' => $bodyMarkdown,
            'status' => $status,
            'author_user_id' => (int)$page['author_user_id'],
            'cover_media_id' => $coverMediaId,
            'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
            'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
            'published_at' => $publishedAt,
        ];

        $newSnapshot = page_snapshot($data);
        $oldSnapshot = $isNew ? null : page_snapshot($page);

        if ($isNew) {
            $id = $pages->createPage($data);
            $audit->log('page.created', (int)$currentUser['id'], 'page', (string)$id, ['status' => $status]);
            $revisions->createRevision('page', $id, (int)$currentUser['id'], $newSnapshot, 'Initial revision');
        } else {
            $oldStatus = $page['status'] ?? 'draft';
            $pages->updatePage($id, $data);
            if ($oldStatus !== $status) {
                $audit->log('page.' . $status, (int)$currentUser['id'], 'page', (string)$id, ['from' => $oldStatus]);
            } else {
                $audit->log('page.updated', (int)$currentUser['id'], 'page', (string)$id);
            }
            if ($oldSnapshot === null || $oldSnapshot != $newSnapshot) {
                $revisions->createRevision('page', $id, (int)$currentUser['id'], $newSnapshot);
            }
        }

        redirect('/admin/pages');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        $page = array_merge($page, [
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'summary' => $_POST['summary'] ?? '',
            'body_markdown' => $_POST['body_markdown'] ?? '',
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
        ]);
    }
}

$allMedia = $media->listAll();

renderHeader($isNew ? 'New page' : 'Edit page', $currentUser);
?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<?php if (!$isNew): ?>
    <p><a href="/admin/revisions?type=page&id=<?= (int)$id ?>">View revision history →</a></p>
<?php endif; ?>

<section class="panel">
    <form method="post" class="article-editor">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <label>
            Title
            <input type="text" name="title" value="<?= e($page['title']) ?>" required maxlength="200">
        </label>

        <label>
            Slug (blank to auto-generate)
            <input type="text" name="slug" value="<?= e($page['slug']) ?>" maxlength="220">
        </label>

        <label>
            Summary
            <textarea name="summary" rows="2" maxlength="500"><?= e((string)($page['summary'] ?? '')) ?></textarea>
        </label>

        <label for="body-markdown">Body (Markdown)</label>
        <div class="wysiwyg" data-preview-url="/admin/page-preview">
            <div class="editor-toolbar" role="toolbar" aria-label="Formatting">
                <button type="button" data-md="h1" title="Heading 1"><i class="bi bi-type-h1"></i></button>
                <button type="button" data-md="h2" title="Heading 2"><i class="bi bi-type-h2"></i></button>
                <span class="tb-sep"></span>
                <button type="button" data-md="bold" title="Bold (Ctrl+B)"><i class="bi bi-type-bold"></i></button>
                <button type="button" data-md="italic" title="Italic (Ctrl+I)"><i class="bi bi-type-italic"></i></button>
                <button type="button" data-md="code" title="Inline code"><i class="bi bi-code"></i></button>
                <span class="tb-sep"></span>
                <button type="button" data-md="quote" title="Quote"><i class="bi bi-quote"></i></button>
                <button type="button" data-md="ul" title="Bulleted list"><i class="bi bi-list-ul"></i></button>
                <button type="button" data-md="ol" title="Numbered list"><i class="bi bi-list-ol"></i></button>
                <span class="tb-sep"></span>
                <button type="button" data-md="link" title="Link (Ctrl+K)"><i class="bi bi-link"></i></button>
                <button type="button" data-md="image" title="Image"><i class="bi bi-image"></i></button>
                <button type="button" data-md="hr" title="Horizontal rule"><i class="bi bi-dash-lg"></i></button>
                <span class="editor-spacer"></span>
                <button type="button" id="preview-toggle" class="editor-toggle" title="Toggle preview"><i class="bi bi-eye"></i> Preview</button>
            </div>
            <div class="editor-panes">
                <textarea name="body_markdown" id="body-markdown" rows="20" required placeholder="Write your page in Markdown…"><?= e($page['body_markdown']) ?></textarea>
                <div id="preview-output" class="article-body editor-preview" hidden></div>
            </div>
        </div>

        <div class="grid-form">
            <label>
                Cover image
                <select name="cover_media_id">
                    <option value="0">— None —</option>
                    <?php foreach ($allMedia as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= (int)$page['cover_media_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                            <?= e($m['original_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Status
                <select name="status">
                    <?php foreach (['draft', 'published', 'archived'] as $st): ?>
                        <?php if ($st === 'published' && !$canPublish) { continue; } ?>
                        <option value="<?= e($st) ?>" <?= $page['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <fieldset>
            <legend>SEO</legend>
            <label>
                Meta title
                <input type="text" name="meta_title" value="<?= e($page['meta_title']) ?>" maxlength="200">
            </label>
            <label>
                Meta description
                <textarea name="meta_description" rows="2" maxlength="320"><?= e($page['meta_description']) ?></textarea>
            </label>
        </fieldset>

        <button type="submit">Save page</button>
    </form>
</section>
<script src="/assets/admin-article.js?v=20260702-5" defer></script>
<?php renderFooter();

function page_snapshot(array $source): array
{
    return [
        'title' => (string)($source['title'] ?? ''),
        'slug' => (string)($source['slug'] ?? ''),
        'summary' => (string)($source['summary'] ?? ''),
        'body_markdown' => (string)($source['body_markdown'] ?? ''),
        'status' => (string)($source['status'] ?? 'draft'),
        'cover_media_id' => $source['cover_media_id'] ?? null,
        'meta_title' => (string)($source['meta_title'] ?? ''),
        'meta_description' => (string)($source['meta_description'] ?? ''),
        'published_at' => $source['published_at'] ?? null,
    ];
}
