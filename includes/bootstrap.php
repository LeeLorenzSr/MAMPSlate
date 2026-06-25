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
require_once APP_ROOT . '/includes/Slug.php';
require_once APP_ROOT . '/includes/MarkdownRenderer.php';
require_once APP_ROOT . '/includes/ArticleRepository.php';
require_once APP_ROOT . '/includes/PageRepository.php';
require_once APP_ROOT . '/includes/CommentRepository.php';
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

session_name($config['app']['session_name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (bool)$config['security']['secure_cookies'],
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
try {
    $pdo = Database::connect($config['database']);
    if (!is_file($installedMarker)) {
        if (!Database::schemaInstalled($pdo)) {
            redirect('/setup');
        }
        @file_put_contents($installedMarker, date('c'));
    }
} catch (Throwable $e) {
    redirect('/setup');
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
$articles = new ArticleRepository($pdo);
$pages = new PageRepository($pdo);
$comments = new CommentRepository($pdo);
$menus = new MenuRepository($pdo);
$settings = new SettingsRepository($pdo, $config);
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
