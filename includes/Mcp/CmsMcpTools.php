<?php
declare(strict_types=1);

/**
 * CMS MCP tool definitions and handlers.
 *
 * Each handler validates input with an allowlist, honors dry-run, performs the
 * action through existing repositories, audit-logs mutations (source="mcp"),
 * and returns only safe fields. No secrets, hashes, or raw HTML are accepted or
 * returned. Article/page bodies are Markdown only, rendered through the existing
 * MarkdownRenderer.
 */
final class CmsMcpTools
{
    public static function register(McpToolRegistry $r): void
    {
        $obj = ['type' => 'object'];

        // ---- Read-only ----------------------------------------------------
        $r->add(new McpTool('cms.health', 'CMS health and status overview.', $obj, [], null, null, false, self::fn('health')));
        $r->add(new McpTool('cms.me', 'The authenticated user and their capabilities.', $obj, [], null, null, false, self::fn('me')));

        $r->add(new McpTool('cms.list_articles', 'List articles (all if you can edit, else published).', self::schema([
            'page' => ['type' => 'integer'], 'per_page' => ['type' => 'integer'], 'status' => ['type' => 'string'],
        ]), ['article.create'], 'articles', null, false, self::fn('listArticles')));
        $r->add(new McpTool('cms.get_article', 'Get one article by id or slug.', self::schema([
            'id' => ['type' => 'integer'], 'slug' => ['type' => 'string'],
        ], []), ['article.create'], 'articles', null, false, self::fn('getArticle')));

        $r->add(new McpTool('cms.list_pages', 'List pages.', self::schema([
            'page' => ['type' => 'integer'], 'per_page' => ['type' => 'integer'],
        ]), ['page.create'], 'pages', null, false, self::fn('listPages')));
        $r->add(new McpTool('cms.get_page', 'Get one page by id or slug.', self::schema([
            'id' => ['type' => 'integer'], 'slug' => ['type' => 'string'],
        ], []), ['page.create'], 'pages', null, false, self::fn('getPage')));

        $r->add(new McpTool('cms.list_media', 'List media items.', self::schema([
            'page' => ['type' => 'integer'], 'per_page' => ['type' => 'integer'],
        ]), ['media.upload'], 'media', null, false, self::fn('listMedia')));

        $r->add(new McpTool('cms.list_comments', 'List comments for moderation.', self::schema([
            'status' => ['type' => 'string'],
        ]), ['comment.moderate'], 'comments', null, false, self::fn('listComments')));

        $r->add(new McpTool('cms.list_menus', 'List menus and their items.', $obj, ['menu.manage'], null, null, false, self::fn('listMenus')));

        $r->add(new McpTool('cms.get_settings_public_or_nonsecret', 'Non-secret site settings and feature toggles.', $obj, ['settings.manage'], null, null, false, self::fn('getSettings')));

        // ---- Article mutation --------------------------------------------
        $r->add(new McpTool('cms.create_article', 'Create a draft or archived article (Markdown body).', self::schema([
            'title' => ['type' => 'string'], 'body_markdown' => ['type' => 'string'], 'slug' => ['type' => 'string'],
            'summary' => ['type' => 'string'], 'category_id' => ['type' => 'integer'], 'cover_media_id' => ['type' => 'integer'],
            'meta_title' => ['type' => 'string'], 'meta_description' => ['type' => 'string'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']], 'status' => ['type' => 'string', 'enum' => ['draft']],
        ], ['title', 'body_markdown']), ['article.create'], 'articles', null, true, self::fn('createArticle')));

        $r->add(new McpTool('cms.update_article', 'Update an article (Markdown body; no author_id or body_html).', self::schema([
            'id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'body_markdown' => ['type' => 'string'],
            'slug' => ['type' => 'string'], 'summary' => ['type' => 'string'], 'category_id' => ['type' => 'integer'],
            'cover_media_id' => ['type' => 'integer'], 'meta_title' => ['type' => 'string'], 'meta_description' => ['type' => 'string'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']], 'status' => ['type' => 'string', 'enum' => ['draft']],
        ], ['id']), ['article.edit.any', 'article.edit.own'], 'articles', null, true, self::fn('updateArticle')));

        $r->add(new McpTool('cms.publish_article', 'Publish an article.', self::schema(['id' => ['type' => 'integer']], ['id']),
            ['article.publish'], 'articles', 'allow_publish', true, self::fn('publishArticle')));

        $r->add(new McpTool('cms.archive_article', 'Archive an article.', self::schema(['id' => ['type' => 'integer']], ['id']),
            ['article.edit.any', 'article.edit.own'], 'articles', 'allow_delete', true, self::fn('archiveArticle')));

        // ---- Page mutation -----------------------------------------------
        $r->add(new McpTool('cms.create_page', 'Create a draft or archived page (Markdown body).', self::schema([
            'title' => ['type' => 'string'], 'body_markdown' => ['type' => 'string'], 'slug' => ['type' => 'string'],
            'summary' => ['type' => 'string'], 'cover_media_id' => ['type' => 'integer'],
            'meta_title' => ['type' => 'string'], 'meta_description' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['draft']],
        ], ['title', 'body_markdown']), ['page.create'], 'pages', null, true, self::fn('createPage')));

        $r->add(new McpTool('cms.update_page', 'Update a page (Markdown body; no author_id or body_html).', self::schema([
            'id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'body_markdown' => ['type' => 'string'],
            'slug' => ['type' => 'string'], 'summary' => ['type' => 'string'], 'cover_media_id' => ['type' => 'integer'],
            'meta_title' => ['type' => 'string'], 'meta_description' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['draft']],
        ], ['id']), ['page.edit.any', 'page.edit.own'], 'pages', null, true, self::fn('updatePage')));

        $r->add(new McpTool('cms.publish_page', 'Publish a page.', self::schema(['id' => ['type' => 'integer']], ['id']),
            ['page.publish'], 'pages', 'allow_publish', true, self::fn('publishPage')));

        $r->add(new McpTool('cms.archive_page', 'Archive a page.', self::schema(['id' => ['type' => 'integer']], ['id']),
            ['page.edit.any', 'page.edit.own'], 'pages', 'allow_delete', true, self::fn('archivePage')));

        // ---- Media --------------------------------------------------------
        $r->add(new McpTool('cms.upload_media', 'Upload an image (base64 data).', self::schema([
            'filename' => ['type' => 'string'], 'mime_type' => ['type' => 'string'], 'data' => ['type' => 'string'],
        ], ['filename', 'mime_type', 'data']), ['media.upload'], 'media', null, true, self::fn('uploadMedia')));

        $r->add(new McpTool('cms.update_media_metadata', 'Update media alt text / title.', self::schema([
            'id' => ['type' => 'integer'], 'alt_text' => ['type' => 'string'], 'title' => ['type' => 'string'],
        ], ['id']), ['media.upload'], 'media', null, true, self::fn('updateMedia')));

        // ---- Comments -----------------------------------------------------
        $r->add(new McpTool('cms.moderate_comment', 'Set a comment status.', self::schema([
            'id' => ['type' => 'integer'], 'status' => ['type' => 'string', 'enum' => ['approved', 'pending', 'rejected', 'spam']],
        ], ['id', 'status']), ['comment.moderate'], 'comments', null, true, self::fn('moderateComment')));

        // ---- Menus --------------------------------------------------------
        $r->add(new McpTool('cms.update_menu', 'Update a menu item (label, url, sort_order, is_active).', self::schema([
            'item_id' => ['type' => 'integer'], 'label' => ['type' => 'string'], 'url' => ['type' => 'string'],
            'sort_order' => ['type' => 'integer'], 'is_active' => ['type' => 'boolean'],
        ], ['item_id']), ['menu.manage'], null, null, true, self::fn('updateMenu')));
    }

    private static function fn(string $method): \Closure
    {
        return fn(array $args, array $ctx): array => self::{$method}($args, $ctx);
    }

    private static function schema(array $props = [], array $required = []): array
    {
        return ['type' => 'object', 'properties' => $props, 'additionalProperties' => false, 'required' => $required];
    }

    private static function pick(array $args, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $args)) {
                $out[$k] = $args[$k];
            }
        }
        return $out;
    }

    private static function maxBodyChars(array $ctx): int
    {
        return (int)($ctx['mcp']['max_body_chars'] ?? 50000);
    }

    private static function perPage(array $ctx, int $requested): int
    {
        $max = (int)($ctx['mcp']['max_per_page'] ?? 50);
        return min(max(1, $requested), max(1, $max));
    }

    private static function audit(string $event, array $ctx, ?int $actorId, string $entityType, ?string $entityId, array $meta = []): void
    {
        $meta['source'] = 'mcp';
        $GLOBALS['audit']?->log($event, $actorId, $entityType, $entityId, $meta);
    }

    // ---- serializers ------------------------------------------------------
    private static function articleOut(array $a): array
    {
        return [
            'id' => (int)$a['id'], 'title' => $a['title'], 'slug' => $a['slug'],
            'summary' => (string)($a['summary'] ?? ''), 'status' => $a['status'],
            'body_markdown' => $a['body_markdown'] ?? '', 'body_html' => $a['body_html'] ?? '',
            'author' => ['id' => (int)$a['author_user_id'], 'name' => $a['author_name'] ?? ''],
            'category' => $a['category_name'] ?? null,
            'cover_media_id' => !empty($a['cover_media_id']) ? (int)$a['cover_media_id'] : null,
            'meta_title' => $a['meta_title'] ?? '', 'meta_description' => $a['meta_description'] ?? '',
            'published_at' => $a['published_at'] ?? null, 'updated_at' => $a['updated_at'] ?? null,
        ];
    }

    private static function pageOut(array $p): array
    {
        return [
            'id' => (int)$p['id'], 'title' => $p['title'], 'slug' => $p['slug'],
            'summary' => (string)($p['summary'] ?? ''), 'status' => $p['status'],
            'body_markdown' => $p['body_markdown'] ?? '', 'body_html' => $p['body_html'] ?? '',
            'author' => ['id' => (int)$p['author_user_id'], 'name' => $p['author_name'] ?? ''],
            'cover_media_id' => !empty($p['cover_media_id']) ? (int)$p['cover_media_id'] : null,
            'meta_title' => $p['meta_title'] ?? '', 'meta_description' => $p['meta_description'] ?? '',
            'published_at' => $p['published_at'] ?? null, 'updated_at' => $p['updated_at'] ?? null,
        ];
    }

    private static function mediaOut(array $m): array
    {
        return [
            'id' => (int)$m['id'], 'url' => '/uploads/' . $m['stored_name'],
            'original_name' => $m['original_name'], 'mime_type' => $m['mime_type'],
            'width' => isset($m['width']) ? (int)$m['width'] : null,
            'height' => isset($m['height']) ? (int)$m['height'] : null,
            'alt_text' => $m['alt_text'] ?? '', 'title' => $m['title'] ?? '',
            'created_at' => $m['created_at'] ?? null,
        ];
    }

    // ---- handlers ---------------------------------------------------------
    public static function health(array $a, array $ctx): array
    {
        $dbOk = false;
        try {
            $GLOBALS['pdo']->query('SELECT 1')->fetch();
            $dbOk = true;
        } catch (Throwable $e) {
        }
        return [
            'service' => setting('site.name', 'MAMPSlate CMS'),
            'db' => $dbOk ? 'ok' : 'error',
            'mcp' => ['enabled' => true, 'dry_run' => $ctx['dryRun']],
            'features' => [
                'articles' => feature('articles'), 'pages' => feature('pages'),
                'media' => feature('media'), 'comments' => feature('comments'),
                'listings' => feature('listings'), 'contact_forms' => feature('contact_forms'),
            ],
        ];
    }

    public static function me(array $a, array $ctx): array
    {
        return [
            'id' => (int)$ctx['user']['id'],
            'email' => $ctx['user']['email'],
            'display_name' => $ctx['user']['display_name'],
            'role' => $ctx['user']['role_name'],
            'capabilities' => $ctx['caps'],
        ];
    }

    public static function listArticles(array $a, array $ctx): array
    {
        $articles = $GLOBALS['articles'];
        $page = max(1, (int)($a['page'] ?? 1));
        $perPage = self::perPage($ctx, (int)($a['per_page'] ?? 20));

        if (($a['status'] ?? null) === 'published') {
            $items = $articles->listPublished($page, $perPage);
            $total = $articles->countPublished();
        } else {
            $rows = $articles->listForAdmin();
            $total = count($rows);
            $items = array_slice($rows, ($page - 1) * $perPage, $perPage);
        }
        return ['data' => array_map(self::fn('articleOut'), $items), 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total]];
    }

    public static function getArticle(array $a, array $ctx): array
    {
        $articles = $GLOBALS['articles'];
        $article = null;
        if (!empty($a['id'])) {
            $article = $articles->findById((int)$a['id']);
        } elseif (!empty($a['slug'])) {
            $article = $articles->findBySlug((string)$a['slug']);
        }
        if (!$article) {
            throw new McpException('Article not found.');
        }
        $out = self::articleOut($article);
        $out['tags'] = array_map(fn($t) => $t['name'], $articles->tagsForArticle((int)$article['id']));
        return $out;
    }

    public static function listPages(array $a, array $ctx): array
    {
        $pages = $GLOBALS['pages'];
        $page = max(1, (int)($a['page'] ?? 1));
        $perPage = self::perPage($ctx, (int)($a['per_page'] ?? 20));
        $rows = $pages->listForAdmin();
        $total = count($rows);
        $items = array_slice($rows, ($page - 1) * $perPage, $perPage);
        return ['data' => array_map(self::fn('pageOut'), $items), 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total]];
    }

    public static function getPage(array $a, array $ctx): array
    {
        $pages = $GLOBALS['pages'];
        $page = null;
        if (!empty($a['id'])) {
            $page = $pages->findById((int)$a['id']);
        } elseif (!empty($a['slug'])) {
            $page = $pages->findBySlug((string)$a['slug']);
        }
        if (!$page) {
            throw new McpException('Page not found.');
        }
        return self::pageOut($page);
    }

    public static function listMedia(array $a, array $ctx): array
    {
        $rows = $GLOBALS['media']->listAll();
        return ['data' => array_map(self::fn('mediaOut'), $rows)];
    }

    public static function listComments(array $a, array $ctx): array
    {
        $rows = $GLOBALS['comments']->listForModeration();
        if (!empty($a['status'])) {
            $rows = array_values(array_filter($rows, fn($c) => $c['status'] === $a['status']));
        }
        return ['data' => $rows];
    }

    public static function listMenus(array $a, array $ctx): array
    {
        $menus = $GLOBALS['menus'];
        $out = [];
        foreach ($menus->allMenus() as $m) {
            $m['items'] = $menus->itemsForMenu((int)$m['id']);
            $out[] = $m;
        }
        return ['data' => $out];
    }

    public static function getSettings(array $a, array $ctx): array
    {
        // Non-secret settings only. Never config.local.php / secrets.
        return [
            'site.name' => setting('site.name'),
            'site.tagline' => setting('site.tagline'),
            'default_meta_title' => setting('default_meta_title'),
            'default_meta_description' => setting('default_meta_description'),
            'signup_mode' => setting('signup_mode'),
            'comments_require_approval' => (string)setting('comments_require_approval') === '1',
            'comments_per_minute' => (int)setting('comments_per_minute'),
            'features' => [
                'articles' => feature('articles'), 'pages' => feature('pages'),
                'media' => feature('media'), 'comments' => feature('comments'),
                'categories' => feature('categories'), 'tags' => feature('tags'),
                'listings' => feature('listings'), 'contact_forms' => feature('contact_forms'),
                'seo_sitemap' => feature('seo_sitemap'), 'rss_feed' => feature('rss_feed'),
            ],
        ];
    }

    // ---- article mutation -------------------------------------------------
    public static function createArticle(array $a, array $ctx): array
    {
        $articles = $GLOBALS['articles'];
        $a = self::pick($a, ['title', 'body_markdown', 'slug', 'summary', 'category_id', 'cover_media_id', 'meta_title', 'meta_description', 'tags', 'status']);
        $title = trim((string)($a['title'] ?? ''));
        $body = (string)($a['body_markdown'] ?? '');
        if ($title === '') {
            throw new McpException('title is required.');
        }
        if ($body === '') {
            throw new McpException('body_markdown is required.');
        }
        if (strlen($body) > self::maxBodyChars($ctx)) {
            throw new McpException('body_markdown exceeds max_body_chars.');
        }
        $status = (string)($a['status'] ?? 'draft');
        if (!in_array($status, ['draft'], true)) {
            throw new McpException('status must be draft (use cms.publish_article or cms.archive_article to change status).');
        }
        $slug = Slug::slugify((string)($a['slug'] ?? '')) ?: Slug::slugify($title);
        $slug = Slug::ensureUnique(fn($s, $ex) => $articles->slugExists($s, $ex), $slug, null);
        $tags = is_array($a['tags'] ?? null) ? array_map('strval', $a['tags']) : [];

        $data = self::articleData($a, $body, $status, (int)$ctx['user']['id'], null);

        if ($ctx['dryRun']) {
            return ['dry_run' => true, 'planned' => 'create article', 'title' => $title, 'slug' => $slug, 'status' => $status, 'tags' => $tags];
        }

        $id = $articles->createArticle($data);
        if ($tags) {
            $articles->syncTags($id, $tags);
        }
        self::audit('article.created', $ctx, (int)$ctx['user']['id'], 'article', (string)$id, ['tool' => 'cms.create_article', 'slug' => $slug]);
        return ['id' => $id, 'slug' => $slug, 'status' => $status];
    }

    public static function updateArticle(array $a, array $ctx): array
    {
        $articles = $GLOBALS['articles'];
        $a = self::pick($a, ['id', 'title', 'body_markdown', 'slug', 'summary', 'category_id', 'cover_media_id', 'meta_title', 'meta_description', 'tags', 'status']);
        $id = (int)($a['id'] ?? 0);
        $existing = $articles->findById($id);
        if (!$existing) {
            throw new McpException('Article not found.');
        }
        self::requireEdit($ctx, (int)$existing['author_user_id'], 'article');

        if (isset($a['body_markdown']) && strlen((string)$a['body_markdown']) > self::maxBodyChars($ctx)) {
            throw new McpException('body_markdown exceeds max_body_chars.');
        }
        if (isset($a['status']) && !in_array($a['status'], ['draft'], true)) {
            throw new McpException('status must be draft (use cms.publish_article or cms.archive_article to change status).');
        }
        $slug = isset($a['slug']) ? Slug::ensureUnique(fn($s, $ex) => $articles->slugExists($s, $ex), Slug::slugify((string)$a['slug']), $id) : $existing['slug'];

        $data = self::articleData($a, (string)($a['body_markdown'] ?? $existing['body_markdown']), (string)($a['status'] ?? $existing['status']), (int)$existing['author_user_id'], $existing, $slug);

        if ($ctx['dryRun']) {
            return ['dry_run' => true, 'planned' => 'update article', 'id' => $id, 'changed_keys' => array_keys($a)];
        }
        $articles->updateArticle($id, $data);
        if (array_key_exists('tags', $a)) {
            $articles->syncTags($id, array_map('strval', (array)$a['tags']));
        }
        self::audit('article.updated', $ctx, (int)$ctx['user']['id'], 'article', (string)$id, ['tool' => 'cms.update_article']);
        return ['id' => $id, 'updated' => true];
    }

    public static function publishArticle(array $a, array $ctx): array
    {
        return self::setArticleStatus($a, $ctx, 'published', 'cms.publish_article');
    }

    public static function archiveArticle(array $a, array $ctx): array
    {
        return self::setArticleStatus($a, $ctx, 'archived', 'cms.archive_article');
    }

    private static function setArticleStatus(array $a, array $ctx, string $status, string $tool): array
    {
        $articles = $GLOBALS['articles'];
        $id = (int)($a['id'] ?? 0);
        $existing = $articles->findById($id);
        if (!$existing) {
            throw new McpException('Article not found.');
        }
        self::requireEdit($ctx, (int)$existing['author_user_id'], 'article');

        $publishedAt = $existing['published_at'];
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        $data = self::articleData([], (string)$existing['body_markdown'], $status, (int)$existing['author_user_id'], $existing, $existing['slug'], $publishedAt);

        if ($ctx['dryRun']) {
            return ['dry_run' => true, 'planned' => 'set article status', 'id' => $id, 'status' => $status];
        }
        $articles->updateArticle($id, $data);
        self::audit('article.' . $status, $ctx, (int)$ctx['user']['id'], 'article', (string)$id, ['tool' => $tool]);
        return ['id' => $id, 'status' => $status];
    }

    private static function articleData(array $a, string $body, string $status, int $authorId, ?array $existing, ?string $slugOverride = null, ?string $publishedAtOverride = null): array
    {
        return [
            'title' => isset($a['title']) ? trim((string)$a['title']) : ($existing['title'] ?? ''),
            'slug' => $slugOverride ?? ($existing['slug'] ?? ''),
            'summary' => array_key_exists('summary', $a) ? (string)$a['summary'] : ($existing['summary'] ?? ''),
            'body_markdown' => $body,
            'status' => $status,
            'author_user_id' => $authorId,
            'category_id' => array_key_exists('category_id', $a) ? (int)$a['category_id'] : ($existing['category_id'] ?? null),
            'cover_media_id' => array_key_exists('cover_media_id', $a) ? (int)$a['cover_media_id'] : ($existing['cover_media_id'] ?? null),
            'meta_title' => array_key_exists('meta_title', $a) ? (string)$a['meta_title'] : ($existing['meta_title'] ?? ''),
            'meta_description' => array_key_exists('meta_description', $a) ? (string)$a['meta_description'] : ($existing['meta_description'] ?? ''),
            'published_at' => $publishedAtOverride ?? ($existing['published_at'] ?? null),
        ];
    }

    // ---- page mutation ----------------------------------------------------
    public static function createPage(array $a, array $ctx): array
    {
        $pages = $GLOBALS['pages'];
        $a = self::pick($a, ['title', 'body_markdown', 'slug', 'summary', 'cover_media_id', 'meta_title', 'meta_description', 'status']);
        $title = trim((string)($a['title'] ?? ''));
        $body = (string)($a['body_markdown'] ?? '');
        if ($title === '' || $body === '') {
            throw new McpException('title and body_markdown are required.');
        }
        if (strlen($body) > self::maxBodyChars($ctx)) {
            throw new McpException('body_markdown exceeds max_body_chars.');
        }
        $status = (string)($a['status'] ?? 'draft');
        if (!in_array($status, ['draft'], true)) {
            throw new McpException('status must be draft (use cms.publish_page or cms.archive_page to change status).');
        }
        $slug = Slug::slugify((string)($a['slug'] ?? '')) ?: Slug::slugify($title);
        $slug = Slug::ensureUnique(fn($s, $ex) => $pages->slugExists($s, $ex), $slug, null);
        $data = self::pageData($a, $body, $status, (int)$ctx['user']['id'], null);

        if ($ctx['dryRun']) {
            return ['dry_run' => true, 'planned' => 'create page', 'title' => $title, 'slug' => $slug, 'status' => $status];
        }
        $id = $pages->createPage($data);
        self::audit('page.created', $ctx, (int)$ctx['user']['id'], 'page', (string)$id, ['tool' => 'cms.create_page', 'slug' => $slug]);
        return ['id' => $id, 'slug' => $slug, 'status' => $status];
    }

    public static function updatePage(array $a, array $ctx): array
    {
        $pages = $GLOBALS['pages'];
        $a = self::pick($a, ['id', 'title', 'body_markdown', 'slug', 'summary', 'cover_media_id', 'meta_title', 'meta_description', 'status']);
        $id = (int)($a['id'] ?? 0);
        $existing = $pages->findById($id);
        if (!$existing) {
            throw new McpException('Page not found.');
        }
        self::requireEdit($ctx, (int)$existing['author_user_id'], 'page');
        if (isset($a['body_markdown']) && strlen((string)$a['body_markdown']) > self::maxBodyChars($ctx)) {
            throw new McpException('body_markdown exceeds max_body_chars.');
        }
        $slug = isset($a['slug']) ? Slug::ensureUnique(fn($s, $ex) => $pages->slugExists($s, $ex), Slug::slugify((string)$a['slug']), $id) : $existing['slug'];
        $data = self::pageData($a, (string)($a['body_markdown'] ?? $existing['body_markdown']), (string)($a['status'] ?? $existing['status']), (int)$existing['author_user_id'], $existing, $slug);

        if ($ctx['dryRun']) {
            return ['dry_run' => true, 'planned' => 'update page', 'id' => $id, 'changed_keys' => array_keys($a)];
        }
        $pages->updatePage($id, $data);
        self::audit('page.updated', $ctx, (int)$ctx['user']['id'], 'page', (string)$id, ['tool' => 'cms.update_page']);
        return ['id' => $id, 'updated' => true];
    }

    public static function publishPage(array $a, array $ctx): array
    {
        return self::setPageStatus($a, $ctx, 'published', 'cms.publish_page');
    }

    public static function archivePage(array $a, array $ctx): array
    {
        return self::setPageStatus($a, $ctx, 'archived', 'cms.archive_page');
    }

    private static function setPageStatus(array $a, array $ctx, string $status, string $tool): array
    {
        $pages = $GLOBALS['pages'];
        $id = (int)($a['id'] ?? 0);
        $existing = $pages->findById($id);
        if (!$existing) {
            throw new McpException('Page not found.');
        }
        self::requireEdit($ctx, (int)$existing['author_user_id'], 'page');
        $publishedAt = $existing['published_at'];
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        $data = self::pageData([], (string)$existing['body_markdown'], $status, (int)$existing['author_user_id'], $existing, $existing['slug'], $publishedAt);
        if ($ctx['dryRun']) {
            return ['dry_run' => true, 'planned' => 'set page status', 'id' => $id, 'status' => $status];
        }
        $pages->updatePage($id, $data);
        self::audit('page.' . $status, $ctx, (int)$ctx['user']['id'], 'page', (string)$id, ['tool' => $tool]);
        return ['id' => $id, 'status' => $status];
    }

    private static function pageData(array $a, string $body, string $status, int $authorId, ?array $existing, ?string $slugOverride = null, ?string $publishedAtOverride = null): array
    {
        return [
            'title' => isset($a['title']) ? trim((string)$a['title']) : ($existing['title'] ?? ''),
            'slug' => $slugOverride ?? ($existing['slug'] ?? ''),
            'summary' => array_key_exists('summary', $a) ? (string)$a['summary'] : ($existing['summary'] ?? null),
            'body_markdown' => $body,
            'status' => $status,
            'author_user_id' => $authorId,
            'cover_media_id' => array_key_exists('cover_media_id', $a) ? (int)$a['cover_media_id'] : ($existing['cover_media_id'] ?? null),
            'meta_title' => array_key_exists('meta_title', $a) ? (string)$a['meta_title'] : ($existing['meta_title'] ?? ''),
            'meta_description' => array_key_exists('meta_description', $a) ? (string)$a['meta_description'] : ($existing['meta_description'] ?? ''),
            'published_at' => $publishedAtOverride ?? ($existing['published_at'] ?? null),
        ];
    }

    private static function requireEdit(array $ctx, int $authorId, string $type): void
    {
        $caps = $ctx['caps'];
        $any = $type . '.edit.any';
        $own = $type . '.edit.own';
        if (in_array($any, $caps, true)) {
            return;
        }
        if (in_array($own, $caps, true) && $authorId === (int)$ctx['user']['id']) {
            return;
        }
        throw new McpException('Forbidden: you can only edit your own ' . $type . 's.');
    }

    // ---- media ------------------------------------------------------------
    public static function uploadMedia(array $a, array $ctx): array
    {
        $a = self::pick($a, ['filename', 'mime_type', 'data']);
        $filename = trim((string)($a['filename'] ?? ''));
        $mime = strtolower(trim((string)($a['mime_type'] ?? '')));
        $data = (string)($a['data'] ?? '');
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if ($filename === '' || $mime === '' || $data === '') {
            throw new McpException('filename, mime_type, and data are required.');
        }
        if (!in_array($mime, $allowed, true)) {
            throw new McpException('mime_type must be one of: ' . implode(', ', $allowed));
        }
        $binary = base64_decode($data, true);
        if ($binary === false || $binary === '') {
            throw new McpException('data must be valid base64.');
        }
        $maxBytes = (int)setting('media_max_upload_bytes', $GLOBALS['config']['app']['media_max_upload_bytes'] ?? 5242880);
        if (strlen($binary) > $maxBytes) {
            throw new McpException('Decoded file exceeds media_max_upload_bytes.');
        }

        if ($ctx['dryRun']) {
            return ['dry_run' => true, 'planned' => 'upload media', 'filename' => $filename, 'mime_type' => $mime, 'bytes' => strlen($binary)];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mcp_');
        file_put_contents($tmp, $binary);
        try {
            $maxWidth = (int)setting('media_image_max_width', $GLOBALS['config']['app']['media_image_max_width'] ?? 1600);
            $meta = $GLOBALS['imageProcessor']->processLocalFile($tmp, $filename, $maxWidth);
        } finally {
            @unlink($tmp);
        }
        $id = $GLOBALS['media']->create((int)$ctx['user']['id'], $meta['stored_name'], $meta['original_name'], $meta['mime_type'], $meta['file_size'], $meta['width'], $meta['height']);
        self::audit('media.created', $ctx, (int)$ctx['user']['id'], 'media', (string)$id, ['tool' => 'cms.upload_media']);
        return ['id' => $id, 'url' => '/uploads/' . $meta['stored_name']];
    }

    public static function updateMedia(array $a, array $ctx): array
    {
        $a = self::pick($a, ['id', 'alt_text', 'title']);
        $id = (int)($a['id'] ?? 0);
        $item = $GLOBALS['media']->findById($id);
        if (!$item) {
            throw new McpException('Media not found.');
        }
        $alt = array_key_exists('alt_text', $a) ? (string)$a['alt_text'] : $item['alt_text'];
        $title = array_key_exists('title', $a) ? (string)$a['title'] : $item['title'];
        if ($ctx['dryRun']) {
            return ['dry_run' => true, 'planned' => 'update media metadata', 'id' => $id];
        }
        $GLOBALS['media']->updateMeta($id, $alt, $title);
        self::audit('media.updated', $ctx, (int)$ctx['user']['id'], 'media', (string)$id, ['tool' => 'cms.update_media_metadata']);
        return ['id' => $id, 'updated' => true];
    }

    // ---- comments ---------------------------------------------------------
    public static function moderateComment(array $a, array $ctx): array
    {
        $a = self::pick($a, ['id', 'status']);
        $id = (int)($a['id'] ?? 0);
        $status = (string)($a['status'] ?? '');
        if (!in_array($status, ['approved', 'pending', 'rejected', 'spam'], true)) {
            throw new McpException('status must be approved, pending, rejected, or spam.');
        }
        if ($ctx['dryRun']) {
            return ['dry_run' => true, 'planned' => 'moderate comment', 'id' => $id, 'status' => $status];
        }
        $GLOBALS['comments']->setStatus($id, $status);
        self::audit('comment.' . $status, $ctx, (int)$ctx['user']['id'], 'comment', (string)$id, ['tool' => 'cms.moderate_comment']);
        return ['id' => $id, 'status' => $status];
    }

    // ---- menus ------------------------------------------------------------
    public static function updateMenu(array $a, array $ctx): array
    {
        $a = self::pick($a, ['item_id', 'label', 'url', 'sort_order', 'is_active']);
        $itemId = (int)($a['item_id'] ?? 0);
        if ($itemId <= 0) {
            throw new McpException('item_id is required.');
        }
        $existing = $GLOBALS['menus']->findItem($itemId);
        if (!$existing) {
            throw new McpException('Menu item not found.');
        }
        $label = array_key_exists('label', $a) ? (string)$a['label'] : $existing['label'];
        $url = array_key_exists('url', $a) ? (string)$a['url'] : $existing['url'];
        $sort = array_key_exists('sort_order', $a) ? (int)$a['sort_order'] : (int)$existing['sort_order'];
        $active = array_key_exists('is_active', $a) ? (bool)$a['is_active'] : (bool)$existing['is_active'];

        if ($ctx['dryRun']) {
            return ['dry_run' => true, 'planned' => 'update menu item', 'item_id' => $itemId, 'label' => $label, 'url' => $url];
        }
        $GLOBALS['menus']->updateItem($itemId, $label, $url, null, $sort, $active);
        self::audit('menu.item.updated', $ctx, (int)$ctx['user']['id'], 'menu_item', (string)$itemId, ['tool' => 'cms.update_menu']);
        return ['item_id' => $itemId, 'updated' => true];
    }
}
