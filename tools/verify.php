<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from CLI: php tools/verify.php\n");
    exit(1);
}

$root = dirname(__DIR__);
$failures = [];

$check = static function (string $name, bool $ok, string $detail = '') use (&$failures): void {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $name . ($detail !== '' ? ' - ' . $detail : '') . PHP_EOL;
    if (!$ok) {
        $failures[] = $name . ($detail !== '' ? ': ' . $detail : '');
    }
};

// PHP lint baseline.
$phpFiles = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    if (strtolower($file->getExtension()) === 'php') {
        $phpFiles[] = $path;
    }
}
sort($phpFiles);
foreach ($phpFiles as $path) {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
    $check('php -l ' . str_replace($root . DIRECTORY_SEPARATOR, '', $path), $rc === 0, $rc === 0 ? '' : implode(' ', $out));
}

// Migration smoke: numeric files should be contiguous from 001 upward, with 000 reserved for dev reset.
$migrationFiles = glob($root . '/sql_init/*.sql') ?: [];
$numbers = [];
foreach ($migrationFiles as $path) {
    if (preg_match('/^(\d{3})_/', basename($path), $m)) {
        $n = (int)$m[1];
        if ($n > 0) {
            $numbers[] = $n;
        }
    }
}
sort($numbers);
$expected = range(1, max($numbers ?: [0]));
$check('migration numbering is contiguous', $numbers === $expected, 'found ' . implode(',', $numbers));

// Setup sanity: example config exposes the keys the setup/bootstrap paths need.
$config = require $root . '/config/config.example.php';
foreach (['app', 'database', 'security', 'mail', 'rate_limits', 'features'] as $key) {
    $check('config has ' . $key, array_key_exists($key, $config));
}
foreach (['articles', 'pages', 'comments', 'media', 'listings', 'contact_forms', 'custom_fields', 'relationships', 'taxonomies', 'collections', 'embeds', 'analytics'] as $feature) {
    $check('feature flag ' . $feature, array_key_exists($feature, $config['features']));
}
foreach (['login', 'signup', 'api_session', 'contact'] as $limit) {
    $check('rate limit ' . $limit, array_key_exists($limit, $config['rate_limits']));
}

// Deterministic slug and repository surface checks.
require_once $root . '/includes/Slug.php';
require_once $root . '/includes/ListingLinkNormalizer.php';
require_once $root . '/includes/EmbedProvider.php';
require_once $root . '/includes/MarkdownRenderer.php';
require_once $root . '/includes/WebhookTargetValidator.php';
$check('slug transliteration', Slug::slugify('Build Your First Subsystem!') === 'build-your-first-subsystem');
$seen = ['example' => true, 'example-2' => true];
$unique = Slug::ensureUnique(fn($slug) => isset($seen[$slug]), 'example');
$check('slug unique suffix', $unique === 'example-3', $unique);
$normalizedLink = ListingLinkNormalizer::fromText('Website | example.com');
$check('listing link normalization adds https', ($normalizedLink[0]['url'] ?? '') === 'https://example.com', $normalizedLink[0]['url'] ?? '');
try {
    ListingLinkNormalizer::fromText('Bad | javascript:alert(1)');
    $check('listing link rejects unsafe schemes', false, 'javascript URL accepted');
} catch (InvalidArgumentException) {
    $check('listing link rejects unsafe schemes', true);
}
$check('webhook target accepts public IPv4', WebhookTargetValidator::isPublicIp('8.8.8.8'));
foreach (['127.0.0.1', '10.0.0.1', '169.254.169.254', '192.168.1.1', '::1', 'fc00::1'] as $address) {
    $check('webhook target rejects non-public IP ' . $address, !WebhookTargetValidator::isPublicIp($address));
}
$apiAuthSource = file_get_contents($root . '/includes/ApiAuth.php') ?: '';
$check('API bearer auth rejects inactive users', str_contains($apiAuthSource, 'activeUser') && str_contains($apiAuthSource, "(bool)\$user['is_active']"));
try {
    $embed = EmbedProvider::normalize('https://open.spotify.com/track/abc', 'spotify');
    $check('embed provider normalizes allowlisted source', ($embed['provider'] ?? '') === 'spotify');
    EmbedProvider::normalize('https://example.com/embed', '');
    $check('embed provider rejects unknown host', false, 'unknown host accepted');
} catch (InvalidArgumentException) {
    $check('embed provider rejects unknown host', true);
}

foreach ([
    'ArticleRepository' => ['slugExists', 'searchPublished', 'searchAdmin'],
    'PageRepository' => ['slugExists', 'searchPublished', 'searchAdmin'],
    'ListingRepository' => ['slugExists', 'searchPublished', 'searchAdmin'],
    'ContactRepository' => ['createSubmission', 'submissions', 'setSubmissionStatus'],
] as $class => $methods) {
    require_once $root . '/includes/' . $class . '.php';
    foreach ($methods as $method) {
        $check($class . '::' . $method . ' exists', method_exists($class, $method));
    }
}

foreach (['ContentExtensionRepository', 'TaxonomyRepository', 'CollectionRepository', 'WebhookRepository', 'NotificationRepository', 'AnalyticsRepository'] as $class) {
    $check('extension repository file exists ' . $class, is_file($root . '/includes/' . $class . '.php'));
}
$check('subsystem scaffold exists', is_file($root . '/tools/make_subsystem.php'));
$check('OpenAPI generator exists', is_file($root . '/tools/generate_openapi.php'));
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/generate_openapi.php') . ' --check 2>&1', $openApiGeneratorOut, $openApiGeneratorRc);
$check('OpenAPI generated route inventory is current', $openApiGeneratorRc === 0, $openApiGeneratorRc === 0 ? '' : implode(' ', $openApiGeneratorOut));

// Auth surface check without creating a database connection.
require_once $root . '/includes/Auth.php';
foreach (['requireLogin', 'requireCapability', 'can', 'canAccessAdmin'] as $method) {
    $check('Auth::' . $method . ' exists', method_exists(Auth::class, $method));
}

// OpenAPI drift check: every /api/v1 route resource/method should be documented.
$apiSource = file_get_contents($root . '/public_html/api/v1/index.php') ?: '';
$openapi = file_get_contents($root . '/docs/openapi-v1.yaml') ?: '';
$routeMethods = api_route_methods($apiSource);
$docMethods = openapi_path_methods($openapi);
foreach ($routeMethods as $path => $methods) {
    foreach ($methods as $method) {
        $hasMethod = in_array($method, $docMethods[$path] ?? [], true);
        $check('OpenAPI documents ' . strtoupper($method) . ' ' . $path, $hasMethod);
    }
}

// Operations permission review: fresh setup and existing-upgrade paths both
// rely on 020 inserting the capabilities and granting them to administrators.
$capSql = file_get_contents($root . '/sql_init/020_starter_subsystems.sql') ?: '';
$baseCapSql = file_get_contents($root . '/sql_init/004_capabilities.sql') ?: '';
foreach (['system.view', 'backup.manage', 'export.manage', 'listing.manage', 'contact.manage', 'demo.manage'] as $cap) {
    $check('020 declares capability ' . $cap, str_contains($capSql, "'" . $cap . "'"));
    $check('020 grants administrator ' . $cap, str_contains($capSql, "'" . $cap . "'") && str_contains($capSql, "r.name = 'administrator'"));
}
$check('004 grants administrator all current capabilities on fresh setup', str_contains($baseCapSql, 'CROSS JOIN capabilities c') && str_contains($baseCapSql, "r.name = 'administrator'"));
$extensionCapSql = file_get_contents($root . '/sql_init/022_extensibility_and_operations.sql') ?: '';
foreach (['content.model.manage', 'taxonomy.manage', 'collection.manage', 'webhook.manage', 'notification.view', 'accessibility.view'] as $cap) {
    $check('022 declares capability ' . $cap, str_contains($extensionCapSql, "'" . $cap . "'"));
    $check('022 grants administrator ' . $cap, str_contains($extensionCapSql, "'" . $cap . "'") && str_contains($extensionCapSql, "r.name = 'administrator'"));
}

// Export privacy controls: exports must go through explicit allowlists, and
// known secret/operational fields must not be in those allowlists.
$exportsSource = file_get_contents($root . '/public_html/admin/exports.php') ?: '';
$check('exports use dataset allowlists', str_contains($exportsSource, 'function export_datasets') && str_contains($exportsSource, 'export_allowlist_row'));
foreach (['password_hash', 'api_key_hash', 'token_hash', 'ip_hash', 'user_agent'] as $field) {
    $check('exports do not allowlist ' . $field, !preg_match('/fields\'\s*=>[^\]]*\'' . preg_quote($field, '/') . '\'/s', $exportsSource));
}

// Backup download behavior should accept only generated backup filenames and
// reject path traversal / host-specific separator tricks.
$backupPattern = '/^(db|files)_\d{8}_\d{6}\.(sql\.gz|tar\.gz)$/';
foreach (['db_20260706_120000.sql.gz', 'files_20260706_120000.tar.gz'] as $file) {
    $check('backup download accepts ' . $file, preg_match($backupPattern, $file) === 1);
}
foreach (['../db_20260706_120000.sql.gz', '..\\db_20260706_120000.sql.gz', 'db_20260706_120000.sql', 'files_20260706_120000.zip'] as $file) {
    $check('backup download rejects ' . $file, preg_match($backupPattern, $file) !== 1);
}
$backupsSource = file_get_contents($root . '/public_html/admin/backups.php') ?: '';
$check('backup UI exposes safe failure detail', str_contains($backupsSource, 'backup_failure_detail') && str_contains($backupsSource, '[redacted]'));

// Contact/listing admin polish checks.
$contactAdminSource = file_get_contents($root . '/public_html/admin/contact-forms.php') ?: '';
$contactPublicSource = file_get_contents($root . '/public_html/contact.php') ?: '';
$check('contact forms validate recipient email server-side', str_contains($contactAdminSource, 'FILTER_VALIDATE_EMAIL'));
$check('contact notifications do not break public submission', str_contains($contactPublicSource, 'contact notification failed') && str_contains($contactPublicSource, 'catch (Throwable'));
$check('inactive contact form returns 404', str_contains($contactPublicSource, '!(bool)$form[\'is_active\']') && str_contains($contactPublicSource, 'http_response_code(404)'));

run_optional_db_smoke($root, $check);

if ($failures) {
    echo PHP_EOL . 'Verification failed:' . PHP_EOL . '- ' . implode(PHP_EOL . '- ', $failures) . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Verification passed.' . PHP_EOL;

function openapi_path_methods(string $yaml): array
{
    $paths = [];
    $current = null;
    foreach (preg_split('/\R/', $yaml) ?: [] as $line) {
        if (preg_match('/^  (\/[^:]+):$/', $line, $m)) {
            $current = $m[1];
            $paths[$current] = [];
            continue;
        }
        if ($current !== null && preg_match('/^    (get|post|patch|put|delete):(\s|$)/', $line, $m)) {
            $paths[$current][] = $m[1];
        }
    }

    return $paths;
}

function api_route_methods(string $source): array
{
    preg_match_all("/case '([^']+)':/", $source, $matches);
    $resources = array_values(array_unique($matches[1] ?? []));
    $routes = [];

    foreach ($resources as $resource) {
        $body = function_body($source, 'route_' . $resource);
        if ($body === '') {
            continue;
        }
        $base = '/' . $resource;
        $item = '/' . $resource . '/{id}';
        $routes[$base] = [];
        $routes[$item] = [];
        if (str_contains($body, "\$method === 'GET' && \$id === null")) {
            $routes[$base][] = 'get';
        }
        if (str_contains($body, "\$method === 'POST' && \$id === null")) {
            $routes[$base][] = 'post';
        }
        if (str_contains($body, "\$method === 'GET' && \$id !== null")) {
            $routes[$item][] = 'get';
        }
        if (str_contains($body, "\$method === 'PATCH' && \$id !== null") || str_contains($body, "(\$method === 'PATCH' || \$method === 'PUT') && \$id !== null")) {
            $routes[$item][] = 'patch';
        }
        if (str_contains($body, "(\$method === 'PATCH' || \$method === 'PUT') && \$id !== null")) {
            $routes[$item][] = 'put';
        }
        if (str_contains($body, "\$method === 'DELETE' && \$id !== null")) {
            $routes[$item][] = 'delete';
        }
        $routes[$base] = array_values(array_unique($routes[$base]));
        $routes[$item] = array_values(array_unique($routes[$item]));
    }

    return array_filter($routes, fn($methods) => $methods !== []);
}

function function_body(string $source, string $function): string
{
    $start = strpos($source, 'function ' . $function . '(');
    if ($start === false) {
        return '';
    }
    $next = strpos($source, "\nfunction ", $start + 1);

    return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
}

function run_optional_db_smoke(string $root, callable $check): void
{
    $dsn = getenv('MAMPSLATE_VERIFY_MYSQL_DSN') ?: '';
    if ($dsn === '') {
        $check('DB-backed smoke skipped', true, 'set MAMPSLATE_VERIFY_MYSQL_DSN to run migrations against a temporary MySQL database');
        return;
    }
    if (!str_starts_with($dsn, 'mysql:') || stripos($dsn, 'dbname=') !== false) {
        $check('DB-backed smoke configuration', false, 'DSN must be a mysql: server DSN without dbname=');
        return;
    }

    $user = getenv('MAMPSLATE_VERIFY_MYSQL_USER') ?: '';
    $password = getenv('MAMPSLATE_VERIFY_MYSQL_PASSWORD') ?: '';
    $dbName = getenv('MAMPSLATE_VERIFY_MYSQL_DATABASE') ?: ('mampslate_verify_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)));
    if (!preg_match('/^mampslate_verify_[A-Za-z0-9_]+$/', $dbName)) {
        $check('DB-backed smoke database name is safe', false, 'name must start with mampslate_verify_');
        return;
    }

    $server = null;
    try {
        $server = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $server->exec('CREATE DATABASE `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo = new PDO($dsn . ';dbname=' . $dbName, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        require_once $root . '/includes/MigrationRunner.php';
        $runner = new MigrationRunner($pdo, $root . '/sql_init');
        $results = $runner->runPending();
        $allOk = $results !== [] && !in_array(false, array_map(fn($r) => $r['ok'], $results), true);
        $check('DB-backed migrations execute', $allOk, $allOk ? $dbName : json_encode($results));

        $grantCount = (int)$pdo->query(
            "SELECT COUNT(*)
             FROM user_roles r
             INNER JOIN role_capabilities rc ON rc.role_id = r.id
             INNER JOIN capabilities c ON c.id = rc.capability_id
             WHERE r.name = 'administrator'
               AND c.name IN ('system.view', 'backup.manage', 'export.manage', 'listing.manage', 'contact.manage', 'demo.manage',
                              'content.model.manage', 'taxonomy.manage', 'collection.manage', 'webhook.manage', 'notification.view', 'accessibility.view')"
        )->fetchColumn();
        $check('DB-backed administrator operations grants', $grantCount === 12, (string)$grantCount);

        foreach (['content_field_definitions', 'entity_relationships', 'taxonomies', 'external_links', 'content_embeds', 'content_collections', 'webhook_endpoints', 'notifications', 'analytics_events', 'profile_claim_requests'] as $table) {
            $exists = (bool)$pdo->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn();
            $check('DB-backed extension table ' . $table, $exists);
        }

        $listings = new ListingRepository($pdo);
        $listingId = $listings->create([
            'title' => 'Verify Listing',
            'slug' => 'verify-listing',
            'summary' => 'Repository smoke test.',
            'body_markdown' => 'Smoke body.',
            'status' => 'published',
            'image_media_id' => null,
            'owner_user_id' => null,
            'links' => [['label' => 'Example', 'url' => 'example.com']],
            'tags' => ['verify'],
            'meta_title' => '',
            'meta_description' => '',
            'published_at' => date('Y-m-d H:i:s'),
        ]);
        $listing = $listings->findById($listingId);
        $check('DB-backed listing repository smoke', ($listing['links'][0]['url'] ?? '') === 'https://example.com');

        $contacts = new ContactRepository($pdo);
        $form = $contacts->findFormBySlug('contact');
        $submissionId = $contacts->createSubmission([
            'form_id' => (int)$form['id'],
            'name' => 'Verify',
            'email' => 'verify@example.test',
            'subject' => 'Smoke',
            'message' => 'Repository smoke test.',
            'status' => 'pending',
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent' => 'verify.php',
        ]);
        $contacts->setSubmissionStatus($submissionId, 'handled');
        $submission = $contacts->findSubmission($submissionId);
        $check('DB-backed contact repository smoke', ($submission['status'] ?? '') === 'handled');
    } catch (Throwable $e) {
        $check('DB-backed smoke', false, $e->getMessage());
    } finally {
        if ($server instanceof PDO && empty(getenv('MAMPSLATE_VERIFY_MYSQL_KEEP'))) {
            $server->exec('DROP DATABASE IF EXISTS `' . $dbName . '`');
        }
    }
}
