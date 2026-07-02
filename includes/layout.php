<?php
declare(strict_types=1);

function renderHeader(string $title, ?array $user = null, ?array $seo = null): void
{
    $config = $GLOBALS['config'] ?? [];
    $appName = setting('site.name', $config['app']['name'] ?? 'MAMPSlate CMS');
    $baseUrl = rtrim(setting('app.base_url', $config['app']['base_url'] ?? ''), '/');
    $defaultDescription = setting('default_meta_description', $config['app']['default_meta_description'] ?? '');
    $seo = $seo ?? [];
    $nonce = csp_nonce();
    security_headers($nonce);

    $headerMenu = $GLOBALS['menus']?->itemsForLocation('header') ?? [];

    $description = $seo['description'] ?? $defaultDescription;
    $canonical = $seo['canonical'] ?? currentPath();
    if ($canonical !== '' && !preg_match('#^https?://#', $canonical)) {
        $canonical = $baseUrl . $canonical;
    }
    $ogType = $seo['og_type'] ?? 'website';
    $ogImage = $seo['og_image'] ?? null;
    if ($ogImage !== null && $ogImage !== '' && !preg_match('#^https?://#', $ogImage)) {
        $ogImage = $baseUrl . $ogImage;
    }
    // Default social share image so every page has an og:image.
    if ($ogImage === null || $ogImage === '') {
        $ogImage = ($baseUrl !== '' ? $baseUrl : '') . '/assets/img/og-default.png';
    }
    $jsonLd = $seo['jsonLd'] ?? null;
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script nonce="<?= e($nonce) ?>">
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (!t) {
                    t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.dataset.theme = t;
            } catch (e) {}
        })();
    </script>
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <title><?= e($title) ?> | <?= e($appName) ?></title>
    <?php if ($description !== ''): ?>
        <meta name="description" content="<?= e($description) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:site_name" content="<?= e($appName) ?>">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php if ($description !== ''): ?>
        <meta property="og:description" content="<?= e($description) ?>">
    <?php endif; ?>
    <?php if ($ogImage): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
        <meta name="twitter:card" content="summary_large_image">
    <?php else: ?>
        <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <?php if ($jsonLd !== null): ?>
        <script type="application/ld+json" nonce="<?= e($nonce) ?>"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <link rel="icon" href="/assets/img/favicon.ico" sizes="any">
    <link rel="icon" href="/assets/img/icon-32.png" type="image/png" sizes="32x32">
    <link rel="icon" href="/assets/img/icon-16.png" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="preload" href="/assets/fonts/montserrat-latin-600.woff2" as="font" type="font/woff2" crossorigin>
    <meta name="theme-color" content="#2458a6" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f131a" media="(prefers-color-scheme: dark)">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/site.css?v=20260702">
    <link rel="alternate" type="application/rss+xml" title="<?= e($appName) ?>" href="<?= e($baseUrl . '/feed') ?>">
</head>
<body>
<header class="site-header">
    <a class="brand" href="/" aria-label="<?= e($appName) ?> — home">
        <span class="brand-logo" aria-hidden="true"></span>
        <span class="brand-name"><?= e($appName) ?></span>
    </a>
    <?php if ($headerMenu): ?>
    <nav class="menu-header" aria-label="Primary">
        <?php foreach ($headerMenu as $item): ?>
            <a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <div class="header-right">
        <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle light or dark theme" title="Toggle theme"></button>
        <?php if (feature('articles')): ?>
            <a href="/articles" class="nav-link"><i class="bi bi-collection"></i> Articles</a>
        <?php endif; ?>
        <?php if ($user): ?>
            <a class="header-avatar" href="/profile" title="Your profile"><?php renderAvatar($user, 32) ?></a>
            <nav class="nav">
                <a href="/profile"><i class="bi bi-person"></i> Profile</a>
                <?php $caps = $user['_capabilities'] ?? []; ?>
                <?php if ($GLOBALS['auth']->canAccessAdmin()): ?>
                    <a href="/admin"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="/admin/search"><i class="bi bi-search"></i> Search</a>
                <?php endif; ?>
                <?php if (in_array('article.create', $caps, true) && feature('articles')): ?>
                    <a href="/admin/articles"><i class="bi bi-file-earmark-text"></i> Articles</a>
                <?php endif; ?>
                <?php if (in_array('page.create', $caps, true) && feature('pages')): ?>
                    <a href="/admin/pages"><i class="bi bi-file-earmark"></i> Pages</a>
                <?php endif; ?>
                <?php if (in_array('user.manage', $caps, true)): ?>
                    <a href="/admin/users"><i class="bi bi-people"></i> Users</a>
                    <a href="/admin/invites"><i class="bi bi-person-plus"></i> Invites</a>
                <?php endif; ?>
                <?php if (in_array('role.manage', $caps, true)): ?>
                    <a href="/admin/roles"><i class="bi bi-shield-check"></i> Roles</a>
                <?php endif; ?>
                <?php if (in_array('apikey.manage', $caps, true)): ?>
                    <a href="/admin/api-keys"><i class="bi bi-key"></i> API keys</a>
                <?php endif; ?>
                <?php if (in_array('media.upload', $caps, true) && feature('media')): ?>
                    <a href="/admin/media"><i class="bi bi-images"></i> Media</a>
                <?php endif; ?>
                <?php if (in_array('comment.moderate', $caps, true) && feature('comments')): ?>
                    <a href="/admin/comments"><i class="bi bi-chat-dots"></i> Comments</a>
                <?php endif; ?>
                <?php if (in_array('menu.manage', $caps, true)): ?>
                    <a href="/admin/menus"><i class="bi bi-list"></i> Menus</a>
                <?php endif; ?>
                <?php if (in_array('settings.manage', $caps, true)): ?>
                    <a href="/admin/settings"><i class="bi bi-gear"></i> Settings</a>
                    <a href="/admin/migrations"><i class="bi bi-database-up"></i> Migrations</a>
                <?php endif; ?>
                <?php if (in_array('audit.view', $caps, true)): ?>
                    <a href="/admin/audit-log"><i class="bi bi-clipboard-data"></i> Audit log</a>
                <?php endif; ?>
                <?php if (in_array('audit.view', $caps, true)): ?>
                    <a href="/admin/audit-log"><i class="bi bi-clipboard-data"></i> Audit log</a>
                <?php endif; ?>
                <a href="/logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </nav>
        <?php else: ?>
            <button type="button" class="auth-trigger" aria-haspopup="dialog"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
        <?php endif; ?>
    </div>
</header>
<?php if (!$user): ?>
    <?php renderAuthModal(); ?>
<?php endif; ?>
<main class="page">
    <?php if (empty($seo['hide_h1'])): ?>
    <h1><?= e($title) ?></h1>
    <?php endif; ?>
<?php
}

/**
 * Render the login/signup modal. Call once on public pages that show it to guests.
 */
function renderAuthModal(): void
{
    $config = $GLOBALS['config'] ?? [];
    $signupMode = setting('signup_mode', $config['app']['signup_mode'] ?? 'off');
    $allowSignup = in_array($signupMode, ['open', 'restricted', 'invite'], true);
    $inviteMode = $signupMode === 'invite';
    $allowOauth = !empty($config['app']['allow_oauth']);
    $oauth = $GLOBALS['oauth'] ?? null;
    $googleEnabled = $allowOauth && $oauth?->isEnabled('google');
    $githubEnabled = $allowOauth && $oauth?->isEnabled('github');
    $anyOauth = $googleEnabled || $githubEnabled;
    ?>
<div class="modal-overlay" id="auth-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="auth-modal-title">
        <button type="button" class="modal-close" id="auth-close" aria-label="Close">&times;</button>
        <h2 class="modal-title" id="auth-modal-title">Welcome</h2>

        <div class="modal-tabs" role="tablist">
            <button type="button" class="modal-tab is-active" role="tab" data-tab="login" id="tab-login">Log in</button>
            <?php if ($allowSignup): ?>
                <button type="button" class="modal-tab" role="tab" data-tab="signup" id="tab-signup">Sign up</button>
            <?php endif; ?>
        </div>

        <p class="notice error" id="auth-error" hidden></p>

        <form id="auth-form" class="auth-form" autocomplete="on">
            <div class="auth-pane" data-pane="login">
                <label>
                    Email
                    <input type="email" name="email" required autocomplete="email">
                </label>
                <label>
                    Password
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <p class="muted"><a href="/auth/forgot-password">Forgot your password?</a></p>
            </div>
            <?php if ($allowSignup): ?>
            <div class="auth-pane" data-pane="signup" hidden>
                <?php if ($inviteMode): ?>
                    <label>
                        Invite code
                        <input type="text" name="invite_code" required autocomplete="off">
                    </label>
                <?php endif; ?>
                <label>
                    Display name
                    <input type="text" name="display_name" required autocomplete="name">
                </label>
                <label>
                    Email
                    <input type="email" name="email" required autocomplete="email">
                </label>
                <label>
                    Password
                    <input type="password" name="password" required autocomplete="new-password" minlength="10">
                </label>
                <p class="muted">Minimum 10 characters.<?php
                    if ($signupMode === 'restricted') {
                        echo ' Your account will be held for approval before you can sign in.';
                    }
                ?></p>
            </div>
            <?php endif; ?>
            <button type="submit" id="auth-submit">Log in</button>
        </form>

        <?php if ($anyOauth): ?>
            <div class="auth-divider"><span>or</span></div>
            <div class="oauth-buttons">
                <?php if ($googleEnabled): ?>
                    <a class="oauth-btn oauth-btn--google" href="/auth/google">Continue with Google</a>
                <?php endif; ?>
                <?php if ($githubEnabled): ?>
                    <a class="oauth-btn oauth-btn--github" href="/auth/github">Continue with GitHub</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="/assets/auth.js" defer></script>
<?php
}

function renderFooter(): void
{
    $nonce = $GLOBALS['csp_nonce'] ?? csp_nonce();
    $appName = setting('site.name', $GLOBALS['config']['app']['name'] ?? 'MAMPSlate CMS');
    $footerMenu = $GLOBALS['menus']?->itemsForLocation('footer') ?? [];
    ?>
</main>
<footer class="site-footer">
    <div class="footer-inner">
        <?php if ($footerMenu): ?>
        <nav class="menu-footer" aria-label="Footer">
            <?php foreach ($footerMenu as $item): ?>
                <a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <p class="muted"><i class="bi bi-globe2"></i> &copy; <?= e(date('Y')) ?> <?= e($appName) ?></p>
    </div>
</footer>
<script src="/assets/theme.js" defer></script>
<script nonce="<?= e($nonce) ?>">
    (function () {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (!window.confirm(el.getAttribute('data-confirm') || '')) {
                    e.preventDefault();
                }
            });
        });
    })();
</script>
</body>
</html>
<?php
}

/**
 * Render a user's avatar: the uploaded image, or a circle with the user's initial.
 */
function renderAvatar(?array $user, int $size = 32): void
{
    if ($user && !empty($user['avatar'])) {
        echo '<img class="avatar" src="/uploads/' . e($user['avatar'])
            . '" width="' . $size . '" height="' . $size
            . '" alt="' . e($user['display_name'] ?? '') . '">';
        return;
    }

    $initial = mb_strtoupper(mb_substr($user['display_name'] ?? '?', 0, 1)) ?: '?';
    echo '<span class="avatar avatar-initials" style="width:' . $size . 'px;height:' . $size
        . 'px;line-height:' . $size . 'px;font-size:' . max(12, (int)($size * 0.45)) . 'px">'
        . e($initial) . '</span>';
}
