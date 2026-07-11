<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/http.php';
require_once APP_ROOT . '/includes/security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/UserRepository.php';
require_once APP_ROOT . '/includes/ApiKeyRepository.php';
require_once APP_ROOT . '/includes/CapabilityRepository.php';
require_once APP_ROOT . '/includes/InviteCodeRepository.php';
require_once APP_ROOT . '/includes/MediaRepository.php';
require_once APP_ROOT . '/includes/MediaUsage.php';
require_once APP_ROOT . '/includes/ImageProcessor.php';
require_once APP_ROOT . '/includes/MediaUploadProcessor.php';
require_once APP_ROOT . '/includes/Slug.php';
require_once APP_ROOT . '/includes/ListingLinkNormalizer.php';
require_once APP_ROOT . '/includes/EmbedProvider.php';
require_once APP_ROOT . '/includes/ModuleRegistry.php';
require_once APP_ROOT . '/includes/ContentExtensionRepository.php';
require_once APP_ROOT . '/includes/TaxonomyRepository.php';
require_once APP_ROOT . '/includes/CollectionRepository.php';
require_once APP_ROOT . '/includes/NotificationRepository.php';
require_once APP_ROOT . '/includes/AnalyticsRepository.php';
require_once APP_ROOT . '/includes/AccessibilityChecker.php';
require_once APP_ROOT . '/includes/WebhookRepository.php';
require_once APP_ROOT . '/includes/WebhookTargetValidator.php';
require_once APP_ROOT . '/includes/WebhookDispatcher.php';
require_once APP_ROOT . '/includes/SitemapRegistry.php';
require_once APP_ROOT . '/includes/ContentExtensions.php';
require_once APP_ROOT . '/includes/MarkdownRenderer.php';
require_once APP_ROOT . '/includes/ArticleRepository.php';
require_once APP_ROOT . '/includes/PageRepository.php';
require_once APP_ROOT . '/includes/ListingRepository.php';
require_once APP_ROOT . '/includes/CommentRepository.php';
require_once APP_ROOT . '/includes/ContactRepository.php';
require_once APP_ROOT . '/includes/MenuRepository.php';
require_once APP_ROOT . '/includes/SettingsRepository.php';
require_once APP_ROOT . '/includes/MigrationRunner.php';
require_once APP_ROOT . '/includes/RevisionRepository.php';
require_once APP_ROOT . '/includes/AuditLogger.php';
require_once APP_ROOT . '/includes/Mailer.php';
require_once APP_ROOT . '/includes/RateLimiter.php';
require_once APP_ROOT . '/includes/PasswordResetRepository.php';
require_once APP_ROOT . '/includes/Auth.php';
require_once APP_ROOT . '/includes/ApiAuth.php';
require_once APP_ROOT . '/includes/OAuthClient.php';
require_once APP_ROOT . '/includes/oauth_flow.php';

$configPath = APP_ROOT . '/config/config.local.php';
if (!is_file($configPath)) {
    $configPath = APP_ROOT . '/config/config.example.php';
}

$config = require $configPath;
$modules = new ModuleRegistry(APP_ROOT . '/modules');

// Fail fast on insecure production defaults. Local env (the shipped default)
// is exempt, so first-run and localhost development are unaffected. This only
// triggers when an operator switches env away from 'local' without setting a
// real secret / production OAuth redirect URIs.
if (($config['app']['env'] ?? 'local') !== 'local') {
    if (($config['security']['app_secret'] ?? '') === 'replace-with-at-least-32-random-characters') {
        http_response_code(500);
        exit('Server misconfiguration.');
    }
    foreach (['google', 'github'] as $provider) {
        $oc = $config['oauth'][$provider] ?? [];
        if (!empty($oc['enabled']) && stripos((string)($oc['redirect_uri'] ?? ''), 'localhost') !== false) {
            http_response_code(500);
            exit('Server misconfiguration.');
        }
    }
}

session_name($config['app']['session_name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (bool)$config['security']['secure_cookies'] || is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// If the database is unreachable or the schema is not installed yet, route to
// the first-run setup wizard. This fails closed: a DB problem shows setup
// (itself gated by the site-master password) rather than leaking errors.
//
// The schema check is skipped when an `installed` marker file exists (written
// by setup or lazily on the first healthy request) to avoid a SHOW TABLES query
// on every request. The connection check still runs, so a down database still
// redirects to setup.
$installedMarker = APP_ROOT . '/config/installed';
$alreadyInstalled = is_file($installedMarker);
try {
    $pdo = Database::connect($config['database']);
    if (!$alreadyInstalled) {
        if (!Database::schemaInstalled($pdo)) {
            redirect('/setup');
        }
        @file_put_contents($installedMarker, date('c'));
    }
} catch (Throwable $e) {
    // First run (not installed yet): route to the setup wizard. On an already
    // configured site, a DB failure is an outage — serve a 503 instead of
    // exposing /setup on a live site.
    if (!$alreadyInstalled) {
        redirect('/setup');
    }
    http_response_code(503);
    exit('This site is temporarily unavailable.');
}

$users = new UserRepository($pdo);

// One-time backfill of profile slugs for accounts that predate the slug
// column (migration 017). Guarded by a marker file so it runs only once.
// Done in PHP because MySQL 5.x cannot produce clean transliterated slugs.
// The marker is written only once the slug column exists, so deploying this
// code before running migration 017 is safe: the backfill retries until the
// migration lands, then runs once.
$slugMarker = APP_ROOT . '/config/profiles_backfilled';
if (!is_file($slugMarker) && $users->backfillSlugs() !== null) {
    @file_put_contents($slugMarker, date('c'));
}
$apiKeys = new ApiKeyRepository($pdo);
$capabilities = new CapabilityRepository($pdo);
$invites = new InviteCodeRepository($pdo);
$media = new MediaRepository($pdo);
$mediaUsage = new MediaUsage($pdo);
$uploadsRoot = APP_ROOT . '/public_html/uploads';
$imageProcessor = new ImageProcessor($uploadsRoot);
$mediaUpload = new MediaUploadProcessor($uploadsRoot, $imageProcessor);
$articles = new ArticleRepository($pdo);
$pages = new PageRepository($pdo);
$listings = new ListingRepository($pdo);
$comments = new CommentRepository($pdo);
$contacts = new ContactRepository($pdo);
$menus = new MenuRepository($pdo);
$settings = new SettingsRepository($pdo, $config);
$contentExtensions = new ContentExtensionRepository($pdo);
$taxonomies = new TaxonomyRepository($pdo);
$collections = new CollectionRepository($pdo);
$notifications = new NotificationRepository($pdo);
$analytics = new AnalyticsRepository($pdo);
$accessibilityChecker = new AccessibilityChecker($articles, $pages, $listings, $media, $contentExtensions);
$webhooks = new WebhookRepository($pdo);
$webhookDispatcher = new WebhookDispatcher($webhooks);
$sitemapRegistry = new SitemapRegistry($articles, $pages, $listings, $modules);
$migrations = new MigrationRunner($pdo, APP_ROOT . '/sql_init');
$revisions = new RevisionRepository($pdo);
$markdown = new MarkdownRenderer();
$auth = new Auth($users, $capabilities);
$apiAuth = new ApiAuth($pdo, $users, (int)$config['app']['api_session_lifetime_minutes']);
$oauth = new OAuthClient($config['oauth'] ?? []);
$audit = new AuditLogger($pdo);
$rateLimiter = new RateLimiter(APP_ROOT . '/cache/ratelimit');
$passwordResets = new PasswordResetRepository($pdo);
$mailer = new Mailer($config['mail'] ?? [], APP_ROOT . '/logs/mail.log');
