<?php
declare(strict_types=1);

/**
 * Versioned CRUD API v1 front controller.
 *
 * Authentication: `Authorization: Bearer <api_key>` or `Authorization: Session <session_key>`.
 * These are bearer credentials and do NOT use CSRF. Authorization is by capability.
 * Responses use the existing shape: {ok:true,...} or {ok:false,error,message}.
 * Password hashes, token hashes, API key hashes, etc. are never exposed.
 */

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

// ---- helpers ---------------------------------------------------------------
function api_ok($data, int $status = 200): never
{
    jsonResponse(['ok' => true, 'data' => $data], $status);
}

function api_list(array $items, int $page, int $perPage, int $total): never
{
    jsonResponse([
        'ok' => true,
        'data' => $items,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / max(1, $perPage))),
        ],
    ]);
}

function api_error(int $status, string $error, string $message): never
{
    jsonResponse(['ok' => false, 'error' => $error, 'message' => $message], $status);
}

function article_out(array $a): array
{
    return [
        'id' => (int)$a['id'],
        'title' => $a['title'],
        'slug' => $a['slug'],
        'summary' => (string)($a['summary'] ?? ''),
        'status' => $a['status'],
        'body_markdown' => $a['body_markdown'] ?? '',
        'body_html' => $a['body_html'] ?? '',
        'author' => ['id' => (int)$a['author_user_id'], 'name' => $a['author_name'] ?? '', 'slug' => $a['author_slug'] ?? null],
        'category' => $a['category_name'] ?? null,
        'cover_media_id' => !empty($a['cover_media_id']) ? (int)$a['cover_media_id'] : null,
        'meta_title' => $a['meta_title'] ?? '',
        'meta_description' => $a['meta_description'] ?? '',
        'published_at' => $a['published_at'] ?? null,
        'updated_at' => $a['updated_at'] ?? null,
        'extensions' => api_extensions_out('article', $a),
    ];
}

function page_out(array $p): array
{
    return [
        'id' => (int)$p['id'],
        'title' => $p['title'],
        'slug' => $p['slug'],
        'summary' => (string)($p['summary'] ?? ''),
        'status' => $p['status'],
        'body_markdown' => $p['body_markdown'] ?? '',
        'body_html' => $p['body_html'] ?? '',
        'author' => ['id' => (int)$p['author_user_id'], 'name' => $p['author_name'] ?? ''],
        'cover_media_id' => !empty($p['cover_media_id']) ? (int)$p['cover_media_id'] : null,
        'meta_title' => $p['meta_title'] ?? '',
        'meta_description' => $p['meta_description'] ?? '',
        'published_at' => $p['published_at'] ?? null,
        'updated_at' => $p['updated_at'] ?? null,
        'extensions' => api_extensions_out('page', $p),
    ];
}

function media_out(array $m): array
{
    return [
        'id' => (int)$m['id'],
        'url' => '/uploads/' . $m['stored_name'],
        'original_name' => $m['original_name'],
        'mime_type' => $m['mime_type'],
        'width' => isset($m['width']) ? (int)$m['width'] : null,
        'height' => isset($m['height']) ? (int)$m['height'] : null,
        'alt_text' => $m['alt_text'] ?? '',
        'title' => $m['title'] ?? '',
        'created_at' => $m['created_at'] ?? null,
    ];
}

function listing_out(array $l): array
{
    return [
        'id' => (int)$l['id'],
        'title' => $l['title'],
        'slug' => $l['slug'],
        'summary' => (string)($l['summary'] ?? ''),
        'status' => $l['status'],
        'body_markdown' => $l['body_markdown'] ?? '',
        'body_html' => $l['body_html'] ?? '',
        'image_media_id' => !empty($l['image_media_id']) ? (int)$l['image_media_id'] : null,
        'owner_user_id' => !empty($l['owner_user_id']) ? (int)$l['owner_user_id'] : null,
        'owner_name' => $l['owner_name'] ?? null,
        'links' => $l['links'] ?? [],
        'tags' => $l['tags'] ?? [],
        'meta_title' => $l['meta_title'] ?? '',
        'meta_description' => $l['meta_description'] ?? '',
        'published_at' => $l['published_at'] ?? null,
        'updated_at' => $l['updated_at'] ?? null,
        'extensions' => api_extensions_out('listing', $l),
    ];
}

function api_extensions_out(string $entityType, array $entity): array
{
    $publicOnly = !array_key_exists('status', $entity) || contentIsPublic($entity);
    return $GLOBALS['contentExtensions']->extensionPayload($entityType, (int)$entity['id'], $publicOnly);
}

function api_save_extensions(string $entityType, int $entityId, array $body, int $actorId): void
{
    if (!array_key_exists('extensions', $body)) {
        return;
    }
    $extensions = $body['extensions'];
    if (!is_array($extensions)) {
        api_error(422, 'validation', 'extensions must be an object.');
    }
    try {
        $repository = $GLOBALS['contentExtensions'];
        if (array_key_exists('custom_fields', $extensions)) {
            $repository->saveFieldValues($entityType, $entityId, is_array($extensions['custom_fields']) ? $extensions['custom_fields'] : []);
        }
        if (array_key_exists('term_ids', $extensions)) {
            $repository->saveTerms($entityType, $entityId, is_array($extensions['term_ids']) ? $extensions['term_ids'] : []);
        }
        if (array_key_exists('links', $extensions)) {
            $repository->saveLinks($entityType, $entityId, is_array($extensions['links']) ? $extensions['links'] : []);
        }
        if (array_key_exists('embeds', $extensions)) {
            $repository->saveEmbeds($entityType, $entityId, is_array($extensions['embeds']) ? $extensions['embeds'] : []);
        }
        if (array_key_exists('relationships', $extensions)) {
            $repository->saveRelationships($entityType, $entityId, is_array($extensions['relationships']) ? $extensions['relationships'] : [], $actorId);
        }
    } catch (InvalidArgumentException $e) {
        api_error(422, 'validation', $e->getMessage());
    }
}

function api_publish_event(string $entityType, int $id, array $data, ?array $previous = null): void
{
    if (!contentIsPublic($data) || ($previous !== null && contentIsPublic($previous))) {
        return;
    }
    $url = contentPublicUrl($entityType, $data) ?? '';
    $GLOBALS['notifications']->create(null, 'content.published', ucfirst($entityType) . ' published: ' . $data['title'], '', $url);
    $GLOBALS['webhookDispatcher']->dispatch('content.published', ['type' => $entityType, 'id' => $id, 'title' => $data['title'], 'url' => $url]);
}

// ---- auth ------------------------------------------------------------------
$apiUser = $apiAuth->authenticateRequest();
if (!$apiUser) {
    api_error(401, 'unauthorized', 'Provide a valid API key (Bearer) or session key (Session).');
}
$apiCaps = $capabilities->capabilitiesForRole((int)$apiUser['role_id']);
$api_can = fn(string $cap): bool => in_array($cap, $apiCaps, true);

// ---- routing ---------------------------------------------------------------
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '';
$prefix = '/api/v1';
if (str_starts_with($uri, $prefix)) {
    $uri = substr($uri, strlen($prefix));
}
$uri = trim($uri, '/');
$segments = $uri === '' ? [] : explode('/', $uri);
$resource = $segments[0] ?? '';
$id = isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($resource === '') {
    api_error(404, 'not_found', 'No resource specified. See docs/api-v1.md.');
}

switch ($resource) {
    case 'articles':
        requireFeature('articles');
        route_articles($method, $id);
        break;
    case 'pages':
        requireFeature('pages');
        route_pages($method, $id);
        break;
    case 'media':
        requireFeature('media');
        route_media($method, $id);
        break;
    case 'listings':
        requireFeature('listings');
        route_listings($method, $id);
        break;
    case 'comments':
        requireFeature('comments');
        route_comments($method, $id);
        break;
    case 'taxonomies':
        requireFeature('taxonomies');
        route_taxonomies($method, $id);
        break;
    case 'collections':
        requireFeature('collections');
        route_collections($method, $id);
        break;
    default:
        api_error(404, 'not_found', "Unknown resource: {$resource}");
}

// ---- listings -------------------------------------------------------------
function route_listings(string $method, ?int $id): void
{
    global $listings, $apiUser, $api_can;

    if ($method === 'GET' && $id === null) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $tag = isset($_GET['tag']) ? trim((string)$_GET['tag']) : null;
        $items = $listings->listPublished($page, $perPage, $tag);
        $total = $listings->countPublished($tag);
        api_list(array_map('listing_out', $items), $page, $perPage, $total);
    }

    if ($method === 'GET' && $id !== null) {
        $item = $listings->findById($id);
        if (!$item) {
            api_error(404, 'not_found', 'Listing not found.');
        }
        if (!contentIsPublic($item) && !$api_can('listing.manage')) {
            api_error(403, 'forbidden', 'You may only view published listings.');
        }
        api_ok(listing_out($item));
    }

    if ($method === 'POST' && $id === null) {
        if (!$api_can('listing.manage')) {
            api_error(403, 'forbidden', 'Missing capability: listing.manage');
        }
        $body = readJsonBody();
        $data = build_listing_data($body, (int)$apiUser['id'], null);
        $newId = $listings->create($data);
        api_save_extensions('listing', $newId, $body, (int)$apiUser['id']);
        api_publish_event('listing', $newId, $data);
        api_ok(listing_out($listings->findById($newId)), 201);
    }

    if (($method === 'PATCH' || $method === 'PUT') && $id !== null) {
        if (!$api_can('listing.manage')) {
            api_error(403, 'forbidden', 'Missing capability: listing.manage');
        }
        $existing = $listings->findById($id);
        if (!$existing) {
            api_error(404, 'not_found', 'Listing not found.');
        }
        $body = readJsonBody();
        $data = build_listing_data($body, (int)$apiUser['id'], $existing);
        $listings->update($id, $data);
        api_save_extensions('listing', $id, $body, (int)$apiUser['id']);
        api_publish_event('listing', $id, $data, $existing);
        api_ok(listing_out($listings->findById($id)));
    }

    if ($method === 'DELETE' && $id !== null) {
        if (!$api_can('listing.manage')) {
            api_error(403, 'forbidden', 'Missing capability: listing.manage');
        }
        $listings->delete($id);
        api_ok(['deleted' => true, 'id' => $id]);
    }

    api_error(405, 'method_not_allowed', 'Method not allowed on this resource.');
}

function build_listing_data(array $body, int $actorId, ?array $existing): array
{
    $title = trim((string)($body['title'] ?? ($existing['title'] ?? '')));
    if ($title === '') {
        api_error(422, 'validation', 'Title is required.');
    }
    $bodyMarkdown = (string)($body['body_markdown'] ?? ($existing['body_markdown'] ?? ''));
    if (trim($bodyMarkdown) === '') {
        api_error(422, 'validation', 'body_markdown is required.');
    }
    $slug = trim((string)($body['slug'] ?? '')) ?: ($existing['slug'] ?? Slug::slugify($title));
    $slug = Slug::ensureUnique(fn($s, $ex) => $GLOBALS['listings']->slugExists($s, $ex), Slug::slugify($slug), $existing ? (int)$existing['id'] : null);

    $status = (string)($body['status'] ?? ($existing['status'] ?? 'draft'));
    try {
        $schedule = contentScheduleForStatus($status, $body['published_at'] ?? null, $existing['published_at'] ?? null);
    } catch (InvalidArgumentException $e) {
        api_error(422, 'validation', $e->getMessage());
    }
    $status = $schedule['status'];
    $publishedAt = $schedule['published_at'];

    return [
        'title' => $title,
        'slug' => $slug,
        'summary' => isset($body['summary']) ? (string)$body['summary'] : ($existing['summary'] ?? ''),
        'body_markdown' => $bodyMarkdown,
        'status' => $status,
        'image_media_id' => isset($body['image_media_id']) ? (int)$body['image_media_id'] : ($existing['image_media_id'] ?? null),
        'owner_user_id' => isset($body['owner_user_id']) ? (int)$body['owner_user_id'] : ($existing['owner_user_id'] ?? $actorId),
        'links' => normalize_listing_api_links(is_array($body['links'] ?? null) ? $body['links'] : ($existing['links'] ?? [])),
        'tags' => is_array($body['tags'] ?? null) ? array_map('strval', $body['tags']) : ($existing['tags'] ?? []),
        'meta_title' => isset($body['meta_title']) ? (string)$body['meta_title'] : ($existing['meta_title'] ?? ''),
        'meta_description' => isset($body['meta_description']) ? (string)$body['meta_description'] : ($existing['meta_description'] ?? ''),
        'published_at' => $publishedAt,
    ];
}

function normalize_listing_api_links(array $links): array
{
    try {
        return ListingLinkNormalizer::fromArray($links);
    } catch (InvalidArgumentException $e) {
        api_error(422, 'validation', $e->getMessage());
    }
}

// ---- articles --------------------------------------------------------------
function route_articles(string $method, ?int $id): void
{
    global $articles, $apiUser, $api_can;

    if ($method === 'GET' && $id === null) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $items = $articles->listPublished($page, $perPage, null, null);
        $total = $articles->countPublished();
        api_list(array_map('article_out', $items), $page, $perPage, $total);
    }

    if ($method === 'GET' && $id !== null) {
        $a = $articles->findById($id);
        if (!$a) {
            api_error(404, 'not_found', 'Article not found.');
        }
        if (!contentIsPublic($a) && !$api_can('article.create')) {
            api_error(403, 'forbidden', 'You may only view published articles.');
        }
        $out = article_out($a);
        $out['tags'] = array_map(fn($t) => $t['name'], $articles->tagsForArticle($id));
        api_ok($out);
    }

    if ($method === 'POST' && $id === null) {
        if (!$api_can('article.create')) {
            api_error(403, 'forbidden', 'Missing capability: article.create');
        }
        $body = readJsonBody();
        if (in_array($body['status'] ?? '', ['published', 'scheduled'], true) && !$api_can('article.publish')) {
            api_error(403, 'forbidden', 'Missing capability: article.publish');
        }
        $data = build_article_data($body, (int)$apiUser['id'], null);
        $newId = $articles->createArticle($data);
        if (!empty($body['tags']) && is_array($body['tags'])) {
            $articles->syncTags($newId, array_map('strval', $body['tags']));
        }
        api_save_extensions('article', $newId, $body, (int)$apiUser['id']);
        api_publish_event('article', $newId, $data);
        api_ok(article_out($articles->findById($newId)), 201);
    }

    if (($method === 'PATCH' || $method === 'PUT') && $id !== null) {
        $a = $articles->findById($id);
        if (!$a) {
            api_error(404, 'not_found', 'Article not found.');
        }
        if (!$api_can('article.edit.any') && !($api_can('article.edit.own') && (int)$a['author_user_id'] === (int)$apiUser['id'])) {
            api_error(403, 'forbidden', 'Missing capability: article.edit.any or article.edit.own');
        }
        $body = readJsonBody();
        if (in_array($body['status'] ?? '', ['published', 'scheduled'], true) && !$api_can('article.publish')) {
            api_error(403, 'forbidden', 'Missing capability: article.publish');
        }
        $data = build_article_data($body, (int)$a['author_user_id'], $a);
        $articles->updateArticle($id, $data);
        if (array_key_exists('tags', $body) && is_array($body['tags'])) {
            $articles->syncTags($id, array_map('strval', $body['tags']));
        }
        api_save_extensions('article', $id, $body, (int)$apiUser['id']);
        api_publish_event('article', $id, $data, $a);
        api_ok(article_out($articles->findById($id)));
    }

    if ($method === 'DELETE' && $id !== null) {
        $a = $articles->findById($id);
        if (!$a) {
            api_error(404, 'not_found', 'Article not found.');
        }
        if (!$api_can('article.delete.any') && !($api_can('article.delete.own') && (int)$a['author_user_id'] === (int)$apiUser['id'])) {
            api_error(403, 'forbidden', 'Missing capability: article.delete.any or article.delete.own');
        }
        $articles->deleteArticle($id);
        api_ok(['deleted' => true, 'id' => $id]);
    }

    api_error(405, 'method_not_allowed', 'Method not allowed on this resource.');
}

function build_article_data(array $body, int $authorId, ?array $existing): array
{
    $title = trim((string)($body['title'] ?? ($existing['title'] ?? '')));
    if ($title === '') {
        api_error(422, 'validation', 'Title is required.');
    }
    $bodyMarkdown = (string)($body['body_markdown'] ?? ($existing['body_markdown'] ?? ''));
    $slug = trim((string)($body['slug'] ?? '')) ?: Slug::slugify($title);
    $slug = Slug::ensureUnique(fn($s, $ex) => $GLOBALS['articles']->slugExists($s, $ex), $slug, $existing ? (int)$existing['id'] : null);

    $status = (string)($body['status'] ?? ($existing['status'] ?? 'draft'));
    try {
        $schedule = contentScheduleForStatus($status, $body['published_at'] ?? null, $existing['published_at'] ?? null);
    } catch (InvalidArgumentException $e) {
        api_error(422, 'validation', $e->getMessage());
    }
    $status = $schedule['status'];
    $publishedAt = $schedule['published_at'];

    return [
        'title' => $title,
        'slug' => $slug,
        'summary' => isset($body['summary']) ? (string)$body['summary'] : ($existing['summary'] ?? ''),
        'body_markdown' => $bodyMarkdown,
        'status' => $status,
        'author_user_id' => $authorId,
        'category_id' => isset($body['category_id']) ? (int)$body['category_id'] : ($existing['category_id'] ?? null),
        'cover_media_id' => isset($body['cover_media_id']) ? (int)$body['cover_media_id'] : ($existing['cover_media_id'] ?? null),
        'meta_title' => isset($body['meta_title']) ? (string)$body['meta_title'] : ($existing['meta_title'] ?? ''),
        'meta_description' => isset($body['meta_description']) ? (string)$body['meta_description'] : ($existing['meta_description'] ?? ''),
        'published_at' => $publishedAt,
    ];
}

// ---- pages -----------------------------------------------------------------
function route_pages(string $method, ?int $id): void
{
    global $pages, $apiUser, $api_can;

    if ($method === 'GET' && $id === null) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $items = $pages->listPublished($page, $perPage);
        $total = $pages->countPublished();
        api_list(array_map('page_out', $items), $page, $perPage, $total);
    }

    if ($method === 'GET' && $id !== null) {
        $p = $pages->findById($id);
        if (!$p) {
            api_error(404, 'not_found', 'Page not found.');
        }
        if (!contentIsPublic($p) && !$api_can('page.create')) {
            api_error(403, 'forbidden', 'You may only view published pages.');
        }
        api_ok(page_out($p));
    }

    if ($method === 'POST' && $id === null) {
        if (!$api_can('page.create')) {
            api_error(403, 'forbidden', 'Missing capability: page.create');
        }
        $body = readJsonBody();
        if (in_array($body['status'] ?? '', ['published', 'scheduled'], true) && !$api_can('page.publish')) {
            api_error(403, 'forbidden', 'Missing capability: page.publish');
        }
        $data = build_page_data($body, (int)$apiUser['id'], null);
        $newId = $pages->createPage($data);
        api_save_extensions('page', $newId, $body, (int)$apiUser['id']);
        api_publish_event('page', $newId, $data);
        api_ok(page_out($pages->findById($newId)), 201);
    }

    if (($method === 'PATCH' || $method === 'PUT') && $id !== null) {
        $p = $pages->findById($id);
        if (!$p) {
            api_error(404, 'not_found', 'Page not found.');
        }
        if (!$api_can('page.edit.any') && !($api_can('page.edit.own') && (int)$p['author_user_id'] === (int)$apiUser['id'])) {
            api_error(403, 'forbidden', 'Missing capability: page.edit.any or page.edit.own');
        }
        $body = readJsonBody();
        if (in_array($body['status'] ?? '', ['published', 'scheduled'], true) && !$api_can('page.publish')) {
            api_error(403, 'forbidden', 'Missing capability: page.publish');
        }
        $data = build_page_data($body, (int)$p['author_user_id'], $p);
        $pages->updatePage($id, $data);
        api_save_extensions('page', $id, $body, (int)$apiUser['id']);
        api_publish_event('page', $id, $data, $p);
        api_ok(page_out($pages->findById($id)));
    }

    if ($method === 'DELETE' && $id !== null) {
        $p = $pages->findById($id);
        if (!$p) {
            api_error(404, 'not_found', 'Page not found.');
        }
        if (!$api_can('page.delete.any') && !($api_can('page.delete.own') && (int)$p['author_user_id'] === (int)$apiUser['id'])) {
            api_error(403, 'forbidden', 'Missing capability: page.delete.any or page.delete.own');
        }
        $pages->deletePage($id);
        api_ok(['deleted' => true, 'id' => $id]);
    }

    api_error(405, 'method_not_allowed', 'Method not allowed on this resource.');
}

function build_page_data(array $body, int $authorId, ?array $existing): array
{
    $title = trim((string)($body['title'] ?? ($existing['title'] ?? '')));
    if ($title === '') {
        api_error(422, 'validation', 'Title is required.');
    }
    $bodyMarkdown = (string)($body['body_markdown'] ?? ($existing['body_markdown'] ?? ''));
    $slug = trim((string)($body['slug'] ?? '')) ?: Slug::slugify($title);
    $slug = Slug::ensureUnique(fn($s, $ex) => $GLOBALS['pages']->slugExists($s, $ex), $slug, $existing ? (int)$existing['id'] : null);

    $status = (string)($body['status'] ?? ($existing['status'] ?? 'draft'));
    try {
        $schedule = contentScheduleForStatus($status, $body['published_at'] ?? null, $existing['published_at'] ?? null);
    } catch (InvalidArgumentException $e) {
        api_error(422, 'validation', $e->getMessage());
    }
    $status = $schedule['status'];
    $publishedAt = $schedule['published_at'];

    return [
        'title' => $title,
        'slug' => $slug,
        'summary' => isset($body['summary']) ? (string)$body['summary'] : ($existing['summary'] ?? null),
        'body_markdown' => $bodyMarkdown,
        'status' => $status,
        'author_user_id' => $authorId,
        'cover_media_id' => isset($body['cover_media_id']) ? (int)$body['cover_media_id'] : ($existing['cover_media_id'] ?? null),
        'meta_title' => isset($body['meta_title']) ? (string)$body['meta_title'] : ($existing['meta_title'] ?? ''),
        'meta_description' => isset($body['meta_description']) ? (string)$body['meta_description'] : ($existing['meta_description'] ?? ''),
        'published_at' => $publishedAt,
    ];
}

// ---- media -----------------------------------------------------------------
function route_media(string $method, ?int $id): void
{
    global $media, $mediaUpload, $apiUser, $api_can, $config;

    if (!$api_can('media.upload')) {
        api_error(403, 'forbidden', 'Missing capability: media.upload');
    }

    if ($method === 'GET' && $id === null) {
        $all = $media->listAll();
        api_ok(array_map('media_out', $all));
    }

    if ($method === 'GET' && $id !== null) {
        $m = $media->findById($id);
        if (!$m) {
            api_error(404, 'not_found', 'Media not found.');
        }
        api_ok(media_out($m));
    }

    if ($method === 'POST' && $id === null) {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            api_error(422, 'validation', 'Provide a file upload named "file".');
        }
        $maxBytes = (int)setting('media_max_upload_bytes', $config['app']['media_max_upload_bytes'] ?? 5242880);
        if ((int)($_FILES['file']['size'] ?? 0) > $maxBytes) {
            api_error(422, 'validation', 'File exceeds the maximum allowed size.');
        }
        try {
            $meta = $mediaUpload->process($_FILES['file'], (int)setting('media_image_max_width', $config['app']['media_image_max_width'] ?? 1600));
            $mediaId = $media->create((int)$apiUser['id'], $meta['stored_name'], $meta['original_name'], $meta['mime_type'], $meta['file_size'], $meta['width'], $meta['height']);
            api_ok(media_out($media->findById($mediaId)), 201);
        } catch (Throwable $e) {
            api_error(422, 'upload_failed', $e->getMessage());
        }
    }

    if ($method === 'DELETE' && $id !== null) {
        $storedName = $media->delete($id);
        if ($storedName === null) {
            api_error(404, 'not_found', 'Media not found.');
        }
        $fullPath = $GLOBALS['uploadsRoot'] . '/' . $storedName;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
        api_ok(['deleted' => true, 'id' => $id]);
    }

    api_error(405, 'method_not_allowed', 'Method not allowed on this resource.');
}

// ---- taxonomies ------------------------------------------------------------
function route_taxonomies(string $method, ?int $id): void
{
    global $taxonomies, $api_can;
    if ($method === 'GET' && $id === null) {
        $out = [];
        foreach ($taxonomies->all() as $taxonomy) {
            $taxonomy['terms'] = $taxonomies->terms((int)$taxonomy['id']);
            $out[] = $taxonomy;
        }
        api_ok($out);
    }
    if ($method === 'POST' && $id === null) {
        if (!$api_can('taxonomy.manage')) { api_error(403, 'forbidden', 'Missing capability: taxonomy.manage'); }
        $body = readJsonBody();
        try {
            $newId = $taxonomies->create($body);
            foreach (is_array($body['terms'] ?? null) ? $body['terms'] : [] as $term) {
                if (is_array($term)) { $taxonomies->createTerm($newId, $term); }
            }
            api_ok(['id' => $newId], 201);
        } catch (InvalidArgumentException $e) { api_error(422, 'validation', $e->getMessage()); }
    }
    if (($method === 'PATCH' || $method === 'PUT') && $id !== null) {
        if (!$api_can('taxonomy.manage')) { api_error(403, 'forbidden', 'Missing capability: taxonomy.manage'); }
        try { $taxonomies->update($id, readJsonBody()); api_ok(['id' => $id]); } catch (InvalidArgumentException $e) { api_error(422, 'validation', $e->getMessage()); }
    }
    if ($method === 'DELETE' && $id !== null) {
        if (!$api_can('taxonomy.manage')) { api_error(403, 'forbidden', 'Missing capability: taxonomy.manage'); }
        $taxonomies->delete($id); api_ok(['deleted' => true, 'id' => $id]);
    }
    api_error(405, 'method_not_allowed', 'Method not allowed on this resource.');
}

// ---- collections -----------------------------------------------------------
function route_collections(string $method, ?int $id): void
{
    global $collections, $api_can;
    if ($method === 'GET' && $id === null) {
        $out = [];
        foreach ($collections->all(!$api_can('collection.manage')) as $collection) {
            $collection['items'] = $collections->items((int)$collection['id']);
            $out[] = $collection;
        }
        api_ok($out);
    }
    if ($method === 'GET' && $id !== null) {
        $collection = $collections->findById($id);
        if (!$collection || (empty($collection['is_public']) && !$api_can('collection.manage'))) { api_error(404, 'not_found', 'Collection not found.'); }
        $collection['items'] = $collections->items($id);
        api_ok($collection);
    }
    if ($method === 'POST' && $id === null) {
        if (!$api_can('collection.manage')) { api_error(403, 'forbidden', 'Missing capability: collection.manage'); }
        $body = readJsonBody();
        try {
            $newId = $collections->create($body);
            if (isset($body['items']) && is_array($body['items'])) { $collections->replaceItems($newId, $body['items']); }
            api_ok(['id' => $newId], 201);
        } catch (InvalidArgumentException $e) { api_error(422, 'validation', $e->getMessage()); }
    }
    if (($method === 'PATCH' || $method === 'PUT') && $id !== null) {
        if (!$api_can('collection.manage')) { api_error(403, 'forbidden', 'Missing capability: collection.manage'); }
        $body = readJsonBody();
        try {
            $collections->update($id, $body);
            if (isset($body['items']) && is_array($body['items'])) { $collections->replaceItems($id, $body['items']); }
            api_ok(['id' => $id]);
        } catch (InvalidArgumentException $e) { api_error(422, 'validation', $e->getMessage()); }
    }
    if ($method === 'DELETE' && $id !== null) {
        if (!$api_can('collection.manage')) { api_error(403, 'forbidden', 'Missing capability: collection.manage'); }
        $collections->delete($id); api_ok(['deleted' => true, 'id' => $id]);
    }
    api_error(405, 'method_not_allowed', 'Method not allowed on this resource.');
}

// ---- comments --------------------------------------------------------------
function route_comments(string $method, ?int $id): void
{
    global $comments, $apiUser, $api_can;

    if (!$api_can('comment.moderate')) {
        api_error(403, 'forbidden', 'Missing capability: comment.moderate');
    }

    if ($method === 'GET' && $id === null) {
        $status = $_GET['status'] ?? null;
        $list = $comments->listForModeration();
        if ($status) {
            $list = array_values(array_filter($list, fn($c) => $c['status'] === $status));
        }
        api_ok($list);
    }

    if ($method === 'PATCH' && $id !== null) {
        $body = readJsonBody();
        $newStatus = (string)($body['status'] ?? '');
        if (!in_array($newStatus, ['approved', 'pending', 'rejected', 'spam'], true)) {
            api_error(422, 'validation', 'status must be one of approved, pending, rejected, spam.');
        }
        $comments->setStatus($id, $newStatus);
        api_ok(['id' => $id, 'status' => $newStatus]);
    }

    api_error(405, 'method_not_allowed', 'Method not allowed on this resource.');
}
