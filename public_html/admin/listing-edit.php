<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('listings');

$currentUser = $auth->requireCapability('listing.manage');
$id = (int)($_GET['id'] ?? 0);
$isNew = $id === 0;
$item = $isNew ? [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'summary' => '',
    'body_markdown' => '',
    'status' => 'draft',
    'image_media_id' => null,
    'owner_user_id' => null,
    'links' => [],
    'tags' => [],
    'meta_title' => '',
    'meta_description' => '',
    'published_at' => null,
] : $listings->findById($id);

if (!$item) {
    http_response_code(404);
    exit('Listing not found.');
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    try {
        $title = trim((string)($_POST['title'] ?? ''));
        $body = (string)($_POST['body_markdown'] ?? '');
        if ($title === '' || trim($body) === '') {
            throw new RuntimeException('Title and body are required.');
        }
        $slug = trim((string)($_POST['slug'] ?? '')) ?: $title;
        $slug = Slug::ensureUnique(fn($s, $exclude) => $listings->slugExists($s, $exclude), Slug::slugify($slug), $isNew ? null : $id);
        $status = (string)($_POST['status'] ?? 'draft');
        if (!in_array($status, contentWorkflowStatuses(), true)) {
            $status = 'draft';
        }
        $schedule = contentScheduleForStatus($status, $_POST['published_at'] ?? null, $isNew ? null : $item['published_at']);
        $status = $schedule['status'];
        $publishedAt = $schedule['published_at'];

        $data = [
            'title' => $title,
            'slug' => $slug,
            'summary' => trim((string)($_POST['summary'] ?? '')),
            'body_markdown' => $body,
            'status' => $status,
            'image_media_id' => (int)($_POST['image_media_id'] ?? 0) ?: null,
            'owner_user_id' => (int)($_POST['owner_user_id'] ?? 0) ?: null,
            'links' => ListingLinkNormalizer::fromText((string)($_POST['links'] ?? '')),
            'tags' => parse_tags((string)($_POST['tags'] ?? '')),
            'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
            'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
            'published_at' => $publishedAt,
        ];

        if ($isNew) {
            $id = $listings->create($data);
            $audit->log('listing.created', (int)$currentUser['id'], 'listing', (string)$id);
        } else {
            $listings->update($id, $data);
            $audit->log('listing.updated', (int)$currentUser['id'], 'listing', (string)$id);
        }
        saveContentExtensions('listing', $id, $_POST, (int)$currentUser['id']);
        if (contentIsPublic($data) && ($isNew || !contentIsPublic($item))) {
            $notifications->create(null, 'content.published', 'Listing published: ' . $title, '', '/listings/' . $slug);
            $webhookDispatcher->dispatch('content.published', ['type' => 'listing', 'id' => $id, 'title' => $title, 'url' => '/listings/' . $slug]);
        }
        redirect('/admin/listings');
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $item = array_merge($item, $_POST);
        try {
            $item['links'] = ListingLinkNormalizer::fromText((string)($_POST['links'] ?? ''));
        } catch (Throwable) {
            $item['links'] = [];
        }
        $item['tags'] = parse_tags((string)($_POST['tags'] ?? ''));
    }
}

$allMedia = feature('media') ? $media->listAll() : [];
$allUsers = $users->recent(100);
$linksText = links_to_text($item['links'] ?? []);
$tagsText = implode(', ', array_map('strval', $item['tags'] ?? []));

renderHeader($isNew ? 'New listing' : 'Edit listing', $currentUser);
?>
<?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<section class="panel">
    <form method="post" class="article-editor">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label>
            Title
            <input type="text" name="title" value="<?= e($item['title']) ?>" required maxlength="200">
        </label>
        <label>
            Slug (blank to auto-generate)
            <input type="text" name="slug" value="<?= e($item['slug']) ?>" maxlength="220">
        </label>
        <label>
            Summary
            <textarea name="summary" rows="2" maxlength="500"><?= e($item['summary']) ?></textarea>
        </label>
        <label>
            Body (Markdown)
            <textarea name="body_markdown" rows="16" required><?= e($item['body_markdown']) ?></textarea>
        </label>
        <div class="grid-form">
            <label>
                Image
                <select name="image_media_id">
                    <option value="0">None</option>
                    <?php foreach ($allMedia as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= (int)$item['image_media_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['original_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Owner
                <select name="owner_user_id">
                    <option value="0">None</option>
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= (int)$item['owner_user_id'] === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Status
                <select name="status">
                    <?php foreach (contentWorkflowStatuses() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $item['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Publish date and time
                <input type="datetime-local" name="published_at" value="<?= !empty($item['published_at']) ? e(date('Y-m-d\\TH:i', strtotime($item['published_at']))) : '' ?>">
            </label>
            <label>
                Tags
                <input type="text" name="tags" value="<?= e($tagsText) ?>" placeholder="comma, separated">
            </label>
        </div>
        <label>
            Links (one per line: Label | https://example.com)
            <textarea name="links" rows="4"><?= e($linksText) ?></textarea>
        </label>
        <fieldset>
            <legend>SEO</legend>
            <label>
                Meta title
                <input type="text" name="meta_title" value="<?= e($item['meta_title']) ?>" maxlength="200">
            </label>
            <label>
                Meta description
                <textarea name="meta_description" rows="2" maxlength="320"><?= e($item['meta_description']) ?></textarea>
            </label>
        </fieldset>
        <?php renderContentExtensionEditor('listing', (int)$id); ?>
        <button type="submit">Save listing</button>
    </form>
</section>
<?php renderFooter();

function parse_tags(string $input): array
{
    $tags = [];
    foreach (explode(',', $input) as $tag) {
        $tag = trim($tag);
        if ($tag !== '') {
            $tags[mb_strtolower($tag)] = $tag;
        }
    }
    return array_values($tags);
}

function links_to_text(array $links): string
{
    $lines = [];
    foreach ($links as $link) {
        $lines[] = trim((string)($link['label'] ?? 'Link')) . ' | ' . trim((string)($link['url'] ?? ''));
    }
    return implode("\n", $lines);
}
