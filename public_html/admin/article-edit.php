<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('articles');

$currentUser = $auth->user();
if (!$currentUser) {
    redirect('/');
}

$id = (int)($_GET['id'] ?? 0);
$isNew = $id === 0;
$canPublish = $auth->can('article.publish');

if ($isNew) {
    $auth->requireCapability('article.create');
    $article = [
        'id' => 0, 'title' => '', 'slug' => '', 'summary' => '', 'body_markdown' => '',
        'status' => 'draft', 'category_id' => null, 'cover_media_id' => null,
        'meta_title' => '', 'meta_description' => '', 'published_at' => null,
        'author_user_id' => (int)$currentUser['id'],
    ];
} else {
    $article = $articles->findById($id);
    if (!$article) {
        http_response_code(404);
        exit('Article not found.');
    }
    $canEdit = $auth->can('article.edit.any')
        || ($auth->can('article.edit.own') && (int)$article['author_user_id'] === (int)$currentUser['id']);
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

        $slug = trim((string)($_POST['slug'] ?? ''));
        if ($slug === '') {
            $slug = Slug::slugify($title);
        } else {
            $slug = Slug::slugify($slug);
        }
        $slug = Slug::ensureUnique(fn($s, $exclude) => $articles->slugExists($s, $exclude), $slug, $isNew ? null : $id);

        // Category: optional new category overrides the select.
        $categoryId = null;
        $newCategory = trim((string)($_POST['new_category'] ?? ''));
        $selectedCategory = (int)($_POST['category_id'] ?? 0);
        if ($newCategory !== '') {
            $catSlug = Slug::ensureUnique(fn($s) => $articles->findCategoryBySlug($s) !== null, Slug::slugify($newCategory));
            $existing = $articles->findCategoryBySlug($catSlug);
            $categoryId = $existing ? (int)$existing['id'] : $articles->createCategory($newCategory, $catSlug);
        } elseif ($selectedCategory > 0) {
            $categoryId = $selectedCategory;
        }

        $coverMediaId = (int)($_POST['cover_media_id'] ?? 0);
        $coverMediaId = $coverMediaId > 0 ? $coverMediaId : null;

        // Status: only users with article.publish may publish.
        $status = (string)($_POST['status'] ?? 'draft');
        if (!in_array($status, contentWorkflowStatuses(), true)) {
            $status = 'draft';
        }
        if (in_array($status, ['published', 'scheduled'], true) && !$canPublish) {
            $status = 'draft';
        }
        $schedule = contentScheduleForStatus($status, $_POST['published_at'] ?? null, $isNew ? null : $article['published_at']);
        $status = $schedule['status'];
        $publishedAt = $schedule['published_at'];

        $data = [
            'title' => $title,
            'slug' => $slug,
            'summary' => trim((string)($_POST['summary'] ?? '')),
            'body_markdown' => $bodyMarkdown,
            'status' => $status,
            'author_user_id' => (int)$article['author_user_id'],
            'category_id' => $categoryId,
            'cover_media_id' => $coverMediaId,
            'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
            'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
            'published_at' => $publishedAt,
        ];

        $tagInput = trim((string)($_POST['tags'] ?? ''));
        $tagNames = $tagInput !== '' ? array_map('trim', explode(',', $tagInput)) : [];
        sort($tagNames);
        $data['_tags'] = $tagNames;

        $oldTags = [];
        if (!$isNew) {
            foreach ($articles->tagsForArticle($id) as $t) {
                $oldTags[] = $t['name'];
            }
            sort($oldTags);
        }
        $oldSnapshot = $isNew ? null : article_snapshot($article, $oldTags);
        $newSnapshot = article_snapshot($data, $tagNames);

        if ($isNew) {
            $id = $articles->createArticle($data);
            $audit->log('article.created', (int)$currentUser['id'], 'article', (string)$id, ['status' => $status]);
            $revisions->createRevision('article', $id, (int)$currentUser['id'], $newSnapshot, 'Initial revision');
        } else {
            $oldStatus = $article['status'] ?? 'draft';
            $articles->updateArticle($id, $data);
            if ($oldStatus !== $status) {
                $audit->log('article.' . $status, (int)$currentUser['id'], 'article', (string)$id, ['from' => $oldStatus]);
            } else {
                $audit->log('article.updated', (int)$currentUser['id'], 'article', (string)$id);
            }
            if ($oldSnapshot === null || $oldSnapshot != $newSnapshot) {
                $revisions->createRevision('article', $id, (int)$currentUser['id'], $newSnapshot);
            }
        }

        $articles->syncTags($id, $tagNames);
        saveContentExtensions('article', $id, $_POST, (int)$currentUser['id']);
        if (contentIsPublic($data) && ($isNew || !contentIsPublic($article))) {
            $notifications->create(null, 'content.published', 'Article published: ' . $title, '', '/articles/' . $slug);
            $webhookDispatcher->dispatch('content.published', ['type' => 'article', 'id' => $id, 'title' => $title, 'url' => '/articles/' . $slug]);
        }

        redirect('/admin/articles');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        // Preserve submitted values for re-display.
        $article = array_merge($article, [
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'summary' => $_POST['summary'] ?? '',
            'body_markdown' => $_POST['body_markdown'] ?? '',
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
        ]);
    }
}

$categories = $articles->allCategories();
$allMedia = $media->listAll();
$currentTags = $articles->tagsForArticle((int)$article['id']);
$tagNames = implode(', ', array_map(fn($t) => $t['name'], $currentTags));

renderHeader($isNew ? 'New article' : 'Edit article', $currentUser);
?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<?php if (!$isNew): ?>
    <p><a href="/admin/revisions?type=article&id=<?= (int)$id ?>">View revision history →</a></p>
<?php endif; ?>

<section class="panel">
    <form method="post" class="article-editor">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <label>
            Title
            <input type="text" name="title" value="<?= e($article['title']) ?>" required maxlength="200">
        </label>

        <label>
            Slug (blank to auto-generate)
            <input type="text" name="slug" value="<?= e($article['slug']) ?>" maxlength="220">
        </label>

        <label>
            Summary
            <textarea name="summary" rows="2" maxlength="500"><?= e($article['summary']) ?></textarea>
        </label>

        <label for="body-markdown">Body (Markdown)</label>
        <div class="wysiwyg" data-preview-url="/admin/article-preview">
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
                <textarea name="body_markdown" id="body-markdown" rows="20" required placeholder="Write your article in Markdown…"><?= e($article['body_markdown']) ?></textarea>
                <div id="preview-output" class="article-body editor-preview" hidden></div>
            </div>
        </div>

        <div class="grid-form">
            <label>
                Category
                <select name="category_id">
                    <option value="0">— None —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$article['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                New category (optional)
                <input type="text" name="new_category" maxlength="120">
            </label>
            <label>
                Tags (comma-separated)
                <input type="text" name="tags" value="<?= e($tagNames) ?>">
            </label>
            <label>
                Cover image
                <select name="cover_media_id">
                    <option value="0">— None —</option>
                    <?php foreach ($allMedia as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= (int)$article['cover_media_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                            <?= e($m['original_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Status
                <select name="status">
                    <?php foreach (contentWorkflowStatuses() as $st): ?>
                        <?php if (in_array($st, ['published', 'scheduled'], true) && !$canPublish) { continue; } ?>
                        <option value="<?= e($st) ?>" <?= $article['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Publish date and time
                <input type="datetime-local" name="published_at" value="<?= !empty($article['published_at']) ? e(date('Y-m-d\\TH:i', strtotime($article['published_at']))) : '' ?>">
            </label>
        </div>

        <fieldset>
            <legend>SEO</legend>
            <label>
                Meta title
                <input type="text" name="meta_title" value="<?= e($article['meta_title']) ?>" maxlength="200">
            </label>
            <label>
                Meta description
                <textarea name="meta_description" rows="2" maxlength="320"><?= e($article['meta_description']) ?></textarea>
            </label>
        </fieldset>

        <?php renderContentExtensionEditor('article', (int)$id); ?>

        <button type="submit">Save article</button>
    </form>

    <?php if ($allMedia): ?>
        <details class="media-picker">
            <summary>Media library — click to copy Markdown</summary>
            <div class="media-grid">
                <?php foreach ($allMedia as $m): ?>
                    <button type="button" class="media-insert"
                            data-markdown="![<?= e($m['alt_text']) ?>](/uploads/<?= e($m['stored_name']) ?>)">
                        <img src="/uploads/<?= e($m['stored_name']) ?>" alt="<?= e($m['alt_text']) ?>" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>
</section>
<script src="/assets/admin-article.js?v=20260702-5" defer></script>
<?php renderFooter();

function article_snapshot(array $source, array $tags): array
{
    return [
        'title' => (string)($source['title'] ?? ''),
        'slug' => (string)($source['slug'] ?? ''),
        'summary' => (string)($source['summary'] ?? ''),
        'body_markdown' => (string)($source['body_markdown'] ?? ''),
        'status' => (string)($source['status'] ?? 'draft'),
        'category_id' => $source['category_id'] ?? null,
        'cover_media_id' => $source['cover_media_id'] ?? null,
        'meta_title' => (string)($source['meta_title'] ?? ''),
        'meta_description' => (string)($source['meta_description'] ?? ''),
        'published_at' => $source['published_at'] ?? null,
        'tags' => array_values($tags),
    ];
}
