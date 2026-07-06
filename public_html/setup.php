<?php
declare(strict_types=1);

/**
 * First-run setup wizard.
 *
 * Standalone: does NOT load bootstrap.php (which redirects here when the
 * database is not ready). Handles:
 *   1. Site-master password gate (hash stored in config/sitemaster.hash).
 *   2. Database server connection test.
 *   3. Database creation.
 *   4. Running the sql_init migrations.
 *   5. Creating the initial administrator account.
 *   6. Writing config/config.local.php.
 *
 * Security: once sitemaster.hash exists, the wizard requires the site-master
 * password to do anything. The very first visit (no hash file) lets the
 * deployer create that password; deploy in a private/trusted context.
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/http.php';
require_once APP_ROOT . '/includes/security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/MigrationRunner.php';

session_name('mampslate_setup');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

security_headers();
prevent_caching();

$siteMasterFile = APP_ROOT . '/config/sitemaster.hash';
$exampleConfigPath = APP_ROOT . '/config/config.example.php';
$localConfigPath = APP_ROOT . '/config/config.local.php';
$sqlInitDir = APP_ROOT . '/sql_init';

$hasSiteMaster = is_file($siteMasterFile) && filesize($siteMasterFile) > 0;

// Once the site has been configured, the first-run wizard is locked unless an
// operator explicitly re-enables it in config.local.php. save_finish writes
// 'setup' => ['enabled' => false] so the wizard auto-locks after first run.
$localCfg = is_file($localConfigPath) ? (require $localConfigPath) : [];
$setupEnabled = is_array($localCfg) && (bool)($localCfg['setup']['enabled'] ?? true);

$authed = (bool)($_SESSION['setup_authed'] ?? false);
$message = null;
$error = null;
$results = null;

// Hard gate: when disabled, refuse every action and render a locked page.
// This runs before any POST handling so no setup action can execute.
if (!$setupEnabled) {
    http_response_code(403);
    security_headers();
    prevent_caching();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup disabled | MAMPSlate CMS</title>
    <?php setup_head_icons(); ?>
    <link rel="stylesheet" href="/assets/site.css?v=20260702-8">
</head>
<body>
<header class="site-header">
    <?php setup_brand_link(); ?>
</header>
<main class="page setup-wrap">
    <h1>Setup is disabled</h1>
    <p class="notice error">First-run setup has already completed and is locked for safety.</p>
    <p class="muted">To re-run setup, set <code>'setup' =&gt; ['enabled' =&gt; true]</code> in <code>config/config.local.php</code> and reload this page. Set it back to <code>false</code> when finished to re-lock.</p>
    <p><a class="link-button" href="/">Return to the site</a></p>
</main>
</body>
</html>
    <?php
    exit;
}

/** Branded <head> icons + font preload (matches includes/layout.php). */
function setup_head_icons(): void
{
    ?>
    <link rel="icon" href="/assets/img/favicon.ico" sizes="any">
    <link rel="icon" href="/assets/img/icon-32.png" type="image/png" sizes="32x32">
    <link rel="icon" href="/assets/img/icon-16.png" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="preload" href="/assets/fonts/montserrat-latin-600.woff2" as="font" type="font/woff2" crossorigin>
    <meta name="theme-color" content="#2458a6" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f131a" media="(prefers-color-scheme: dark)">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <?php
}

/** Branded header logo + wordmark (matches includes/layout.php). */
function setup_brand_link(): void
{
    ?>
    <a class="brand" href="/" aria-label="MAMPSlate CMS — home">
        <span class="brand-logo" aria-hidden="true"></span>
        <span class="brand-name">MAMPSlate CMS</span>
    </a>
    <?php
}

/** Connect to MySQL, optionally selecting a database. */
function setup_connect(string $host, int $port, string $user, string $pass, ?string $dbname = null): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
    if ($dbname !== null && $dbname !== '') {
        $dsn .= ';dbname=' . $dbname;
    }

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/** Read DB fields from POST/session, falling back to example config. */
function setup_db_fields(): array
{
    $example = $GLOBALS['exampleConfigPath'] ?? null;
    $defaults = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'mampslate',
        'user' => '',
        'password' => '',
    ];
    if ($example && is_file($example)) {
        $cfg = require $example;
        $defaults['host'] = $cfg['database']['host'] ?? $defaults['host'];
        $defaults['port'] = $cfg['database']['port'] ?? $defaults['port'];
        $defaults['name'] = $cfg['database']['name'] ?? $defaults['name'];
    }
    if (($_POST['db_host'] ?? null) !== null) {
        return [
            'host' => trim((string)$_POST['db_host']),
            'port' => (int)($_POST['db_port'] ?? 3306),
            'name' => trim((string)$_POST['db_name']),
            'user' => trim((string)$_POST['db_user']),
            'password' => (string)$_POST['db_password'],
        ];
    }

    return $_SESSION['setup_db'] ?? $defaults;
}

function detect_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/** Execute every sql_init/*.sql (except the dev reset) in order. */
function setup_run_migrations(PDO $pdo): array
{
    $runner = new MigrationRunner($pdo, $GLOBALS['sqlInitDir']);
    return $runner->runPending();
}

$GLOBALS['exampleConfigPath'] = $exampleConfigPath;
$GLOBALS['sqlInitDir'] = $sqlInitDir;

// ---- Handle POST -----------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Gate actions (no auth required).
    if ($action === 'create_sitemaster' && !$hasSiteMaster) {
        requireValidCsrf();
        $pw = (string)($_POST['sitemaster_password'] ?? '');
        $confirm = (string)($_POST['sitemaster_confirm'] ?? '');
        if (strlen($pw) < 10) {
            $error = 'Site-master password must be at least 10 characters.';
        } elseif ($pw !== $confirm) {
            $error = 'Site-master passwords do not match.';
        } elseif (@file_put_contents($siteMasterFile, password_hash($pw, PASSWORD_DEFAULT)) === false) {
            $error = 'Could not write config/sitemaster.hash. Check that config/ is writable.';
        } else {
            $_SESSION['setup_authed'] = true;
            $hasSiteMaster = true;
            $authed = true;
            $message = 'Site-master password set.';
        }
    }

    if ($action === 'login_sitemaster' && $hasSiteMaster) {
        requireValidCsrf();
        $pw = (string)($_POST['sitemaster_password'] ?? '');
        $hash = (string)file_get_contents($siteMasterFile);
        if (password_verify($pw, $hash)) {
            $_SESSION['setup_authed'] = true;
            $authed = true;
            $message = 'Unlocked.';
        } else {
            $error = 'Incorrect site-master password.';
        }
    }

    if ($action === 'lock') {
        unset($_SESSION['setup_authed']);
        $authed = false;
        $message = 'Locked.';
    }

    // Authenticated setup actions.
    if ($authed) {
        $db = setup_db_fields();
        $_SESSION['setup_db'] = $db;

        // The database name is used in DDL and the DSN, where it cannot be
        // parameterized; restrict it to a safe identifier charset.
        $nameActions = ['create_database', 'test_database', 'run_init', 'create_admin', 'save_finish'];
        if (in_array($action, $nameActions, true) && preg_match('/^[A-Za-z0-9_]+$/', $db['name']) !== 1) {
            $error = 'Invalid database name. Use letters, numbers, and underscores only.';
            $action = '';
        }

        if ($action === 'test_server') {
            requireValidCsrf();
            try {
                setup_connect($db['host'], $db['port'], $db['user'], $db['password']);
                $message = 'Connected to the database server as "' . $db['user'] . '" at ' . $db['host'] . ':' . $db['port'] . '.';
            } catch (Throwable $e) {
                $error = 'Server connection failed: ' . $e->getMessage();
            }
        }

        if ($action === 'create_database') {
            requireValidCsrf();
            try {
                $pdo = setup_connect($db['host'], $db['port'], $db['user'], $db['password']);
                $name = $db['name'];
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $message = 'Database "' . $name . '" is ready.';
            } catch (Throwable $e) {
                $error = 'Could not create database: ' . $e->getMessage();
            }
        }

        if ($action === 'test_database') {
            requireValidCsrf();
            try {
                $pdo = setup_connect($db['host'], $db['port'], $db['user'], $db['password'], $db['name']);
                $installed = Database::schemaInstalled($pdo);
                $message = $installed
                    ? 'Connected. Schema is already installed.'
                    : 'Connected. Schema is NOT installed yet — run "Initialize schema" below.';
            } catch (Throwable $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
        }

        if ($action === 'run_init') {
            requireValidCsrf();
            try {
                $pdo = setup_connect($db['host'], $db['port'], $db['user'], $db['password'], $db['name']);
                $results = setup_run_migrations($pdo);
                $allOk = $results && !in_array(false, array_map(fn($r) => $r['ok'], $results), true);
                $message = $allOk ? 'Schema initialized successfully.' : 'Schema initialization stopped at the first error (see below).';
                if (!$allOk) {
                    $error = 'One or more migration files failed.';
                }
            } catch (Throwable $e) {
                $error = 'Could not run migrations: ' . $e->getMessage();
            }
        }

        if ($action === 'create_admin') {
            requireValidCsrf();
            try {
                $pdo = setup_connect($db['host'], $db['port'], $db['user'], $db['password'], $db['name']);
                $email = strtolower(trim((string)($_POST['admin_email'] ?? '')));
                $pw = (string)($_POST['admin_password'] ?? '');
                $confirm = (string)($_POST['admin_confirm'] ?? '');

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Provide a valid admin email.');
                }
                if (strlen($pw) < 10) {
                    throw new RuntimeException('Admin password must be at least 10 characters.');
                }
                if ($pw !== $confirm) {
                    throw new RuntimeException('Admin passwords do not match.');
                }

                $roleRow = $pdo->prepare("SELECT id FROM user_roles WHERE name = 'administrator' LIMIT 1");
                $roleRow->execute();
                $roleId = (int)$roleRow->fetchColumn();
                if ($roleId <= 0) {
                    throw new RuntimeException('Administrator role not found. Run "Initialize schema" first.');
                }

                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $find = $pdo->prepare('SELECT id FROM users WHERE email = :email');
                $find->execute(['email' => $email]);
                $existing = (int)$find->fetchColumn();

                if ($existing > 0) {
                    $upd = $pdo->prepare('UPDATE users SET password_hash = :h, role_id = :r, is_active = 1 WHERE id = :id');
                    $upd->execute(['h' => $hash, 'r' => $roleId, 'id' => $existing]);
                } else {
                    $ins = $pdo->prepare('INSERT INTO users (email, display_name, role_id, password_hash, is_active) VALUES (:email, :name, :r, :h, 1)');
                    $ins->execute(['email' => $email, 'name' => 'Administrator', 'r' => $roleId, 'h' => $hash]);
                }

                // Remove the seeded weak default admin if a different one was created.
                if ($email !== 'admin@example.test') {
                    $pdo->prepare("DELETE FROM users WHERE email = 'admin@example.test'")->execute();
                }

                $message = 'Administrator account ready: ' . $email;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        if ($action === 'save_finish') {
            requireValidCsrf();
            try {
                if (!is_file($exampleConfigPath)) {
                    throw new RuntimeException('config/config.example.php is missing.');
                }
                $base = require $exampleConfigPath;
                $base['database'] = [
                    'host' => $db['host'],
                    'port' => $db['port'],
                    'name' => $db['name'],
                    'user' => $db['user'],
                    'password' => $db['password'],
                    'charset' => 'utf8mb4',
                ];
                $base['app']['base_url'] = detect_base_url();

                // Lock the first-run wizard now that the site is configured.
                $base['setup'] = ['enabled' => false];

                $written = @file_put_contents(
                    $localConfigPath,
                    "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($base, true) . ";\n"
                );
                if ($written === false) {
                    throw new RuntimeException('Could not write config/config.local.php. Check that config/ is writable.');
                }

                // Drop any stale `installed` marker, then verify the new
                // configuration actually works before leaving setup.
                $installedMarker = APP_ROOT . '/config/installed';
                @unlink($installedMarker);

                $check = Database::connect($base['database']);
                if (!Database::schemaInstalled($check)) {
                    throw new RuntimeException('Configuration saved, but the schema is not installed. Run "Initialize schema" then return.');
                }

                @file_put_contents($installedMarker, date('c'));

                unset($_SESSION['setup_authed'], $_SESSION['setup_db']);
                redirect('/');
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$db = setup_db_fields();
$baseUrl = detect_base_url();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup | MAMPSlate CMS</title>
    <?php setup_head_icons(); ?>
    <link rel="stylesheet" href="/assets/site.css?v=20260702-8">
    <style>
        .setup-wrap { max-width: 720px; margin: 32px auto; padding: 0 16px; }
        .setup-step { margin-bottom: 28px; }
        .setup-step h2 { margin-bottom: 12px; }
        .checklist { list-style: none; padding: 0; margin: 12px 0; }
        .checklist li { padding: 4px 0; }
        .checklist .ok { color: var(--success); }
        .checklist .bad { color: var(--danger); }
        .btn-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
        .btn-row button { min-width: 140px; }
        code.db { word-break: break-all; }
    </style>
</head>
<body>
<header class="site-header">
    <?php setup_brand_link(); ?>
    <div class="header-right"><span class="muted">First-run setup</span></div>
</header>
<main class="page setup-wrap">
    <h1>Setup</h1>

    <?php if ($message): ?>
        <p class="notice success"><?= e($message) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="notice error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if (!$authed): ?>
        <section class="panel setup-step">
            <?php if (!$hasSiteMaster): ?>
                <h2>Create the site-master password</h2>
                <p class="muted">This is the first time setup has been opened. Create a site-master password that protects this page. You will need it to return to setup later. Choose something strong (min 10 characters).</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="create_sitemaster">
                    <label>Site-master password
                        <input type="password" name="sitemaster_password" required minlength="10" autocomplete="new-password">
                    </label>
                    <label>Confirm password
                        <input type="password" name="sitemaster_confirm" required minlength="10" autocomplete="new-password">
                    </label>
                    <button type="submit">Set password and continue</button>
                </form>
            <?php else: ?>
                <h2>Enter site-master password</h2>
                <p class="muted">This page is locked. Enter the site-master password to manage setup.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="login_sitemaster">
                    <label>Site-master password
                        <input type="password" name="sitemaster_password" required autocomplete="current-password">
                    </label>
                    <button type="submit">Unlock</button>
                </form>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="panel setup-step">
            <h2>1. Site master</h2>
            <p class="muted">Setup is unlocked. <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="lock"><button type="submit" class="link-button">Lock setup</button></form></p>
        </section>

        <section class="panel setup-step">
            <h2>2. Database connection</h2>
            <p class="muted">Enter the MySQL credentials. The user needs CREATE privilege to create a new database, and full privileges on the target database to run migrations.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="grid-form">
                    <label>Host
                        <input type="text" name="db_host" value="<?= e($db['host']) ?>" required>
                    </label>
                    <label>Port
                        <input type="number" name="db_port" value="<?= e((string)$db['port']) ?>" required>
                    </label>
                    <label>Database name
                        <input type="text" name="db_name" value="<?= e($db['name']) ?>" required>
                    </label>
                    <label>User
                        <input type="text" name="db_user" value="<?= e($db['user']) ?>" required>
                    </label>
                    <label>Password
                        <input type="password" name="db_password" value="<?= e($db['password']) ?>" autocomplete="off">
                    </label>
                </div>
                <div class="btn-row">
                    <button type="submit" name="action" value="test_server">Test server</button>
                    <button type="submit" name="action" value="create_database">Create database</button>
                    <button type="submit" name="action" value="test_database">Test database</button>
                </div>
            </form>
        </section>

        <section class="panel setup-step">
            <h2>3. Initialize schema</h2>
            <p class="muted">Run the migration scripts (<code>001</code>–<code>021</code>) against the database. This is safe to re-run.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="db_host" value="<?= e($db['host']) ?>">
                <input type="hidden" name="db_port" value="<?= e((string)$db['port']) ?>">
                <input type="hidden" name="db_name" value="<?= e($db['name']) ?>">
                <input type="hidden" name="db_user" value="<?= e($db['user']) ?>">
                <input type="hidden" name="db_password" value="<?= e($db['password']) ?>">
                <button type="submit" name="action" value="run_init">Initialize schema</button>
            </form>
            <?php if ($results !== null): ?>
                <ul class="checklist space-top">
                    <?php foreach ($results as $r): ?>
                        <li class="<?= $r['ok'] ? 'ok' : 'bad' ?>">
                            <?= $r['ok'] ? '✓' : '✗' ?> <code><?= e($r['file']) ?></code>
                            <?php if (!$r['ok']): ?> — <?= e($r['error']) ?><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="panel setup-step">
            <h2>4. Administrator account</h2>
            <p class="muted">Set the initial administrator login. This replaces the seeded <code>admin@example.test</code> / <code>change-me</code> account.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="db_host" value="<?= e($db['host']) ?>">
                <input type="hidden" name="db_port" value="<?= e((string)$db['port']) ?>">
                <input type="hidden" name="db_name" value="<?= e($db['name']) ?>">
                <input type="hidden" name="db_user" value="<?= e($db['user']) ?>">
                <input type="hidden" name="db_password" value="<?= e($db['password']) ?>">
                <label>Admin email
                    <input type="email" name="admin_email" value="admin@example.test" required>
                </label>
                <div class="grid-form">
                    <label>Admin password
                        <input type="password" name="admin_password" required minlength="10" autocomplete="new-password">
                    </label>
                    <label>Confirm password
                        <input type="password" name="admin_confirm" required minlength="10" autocomplete="new-password">
                    </label>
                </div>
                <button type="submit" name="action" value="create_admin">Create / update administrator</button>
            </form>
        </section>

        <section class="panel setup-step">
            <h2>5. Save configuration & finish</h2>
            <p class="muted">Writes <code>config/config.local.php</code> with the database settings above and the detected base URL <code class="db"><?= e($baseUrl) ?></code>, then takes you to the site.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="db_host" value="<?= e($db['host']) ?>">
                <input type="hidden" name="db_port" value="<?= e((string)$db['port']) ?>">
                <input type="hidden" name="db_name" value="<?= e($db['name']) ?>">
                <input type="hidden" name="db_user" value="<?= e($db['user']) ?>">
                <input type="hidden" name="db_password" value="<?= e($db['password']) ?>">
                <button type="submit" name="action" value="save_finish">Save and finish</button>
            </form>
        </section>

        <section class="panel setup-step">
            <h2>Post-setup checklist</h2>
            <ul class="checklist">
                <li>Review <strong>System status</strong> for PHP extensions, writable directories, mail mode, upload limits, HTTPS/cookies, and migrations.</li>
                <li>Run a test <strong>Database backup</strong> and <strong>File backup</strong> on the target host.</li>
                <li>Confirm <strong>Exports</strong> are available only to trusted operators.</li>
                <li>Configure or disable <strong>Listings</strong> and <strong>Contact forms</strong> for the project.</li>
                <li>Seed <strong>Demo content</strong> only on sandbox or staging installs that need examples.</li>
            </ul>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
