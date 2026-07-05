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
foreach (['articles', 'pages', 'comments', 'media', 'listings', 'contact_forms'] as $feature) {
    $check('feature flag ' . $feature, array_key_exists($feature, $config['features']));
}
foreach (['login', 'signup', 'api_session', 'contact'] as $limit) {
    $check('rate limit ' . $limit, array_key_exists($limit, $config['rate_limits']));
}

// Deterministic slug and repository surface checks.
require_once $root . '/includes/Slug.php';
$check('slug transliteration', Slug::slugify('Build Your First Subsystem!') === 'build-your-first-subsystem');
$seen = ['example' => true, 'example-2' => true];
$unique = Slug::ensureUnique(fn($slug) => isset($seen[$slug]), 'example');
$check('slug unique suffix', $unique === 'example-3', $unique);

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

// Auth surface check without creating a database connection.
require_once $root . '/includes/Auth.php';
foreach (['requireLogin', 'requireCapability', 'can', 'canAccessAdmin'] as $method) {
    $check('Auth::' . $method . ' exists', method_exists(Auth::class, $method));
}

if ($failures) {
    echo PHP_EOL . 'Verification failed:' . PHP_EOL . '- ' . implode(PHP_EOL . '- ', $failures) . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Verification passed.' . PHP_EOL;
