<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$currentUser = $auth->user();
$appName = setting('site.name', $config['app']['name'] ?? 'MAMPSlate CMS');
$repoUrl = 'https://github.com/LeeLorenzSr/MAMPSlate';

renderHeader('About', $currentUser, [
    'canonical' => '/about',
    'hide_h1' => true,
    'description' => 'About ' . $appName . ' — a fast, dependency-free PHP/MySQL publishing platform.',
]);
?>
<section class="hero hero--centered">
    <div class="hero-inner">
        <div class="hero-copy">
            <img class="hero-logo" src="/assets/img/logo-dark.svg" alt="<?= e($appName) ?> logo">
            <h1 class="hero-title">About <?= e($appName) ?></h1>
            <p class="hero-sub">A fast, dependency-free PHP/MySQL publishing platform — built to be copied and specialized for your own site.</p>
        </div>
    </div>
</section>

<section class="panel">
    <h2><i class="bi bi-info-circle"></i> What it is</h2>
    <p><?= e($appName) ?> is a small, server-rendered PHP/MySQL CMS designed as a reusable base. It runs on ordinary MAMP/LAMP hosting with no build step and no JavaScript framework — just PHP templates, PDO repositories, and a token-based stylesheet you can make your own.</p>
    <p>It ships with capability-based roles, Markdown articles, a media library, OAuth login, and SEO-friendly extensionless URLs, so you can spin up a real site quickly and specialize it for whatever you're publishing.</p>
</section>

<section class="panel">
    <h2><i class="bi bi-stars"></i> What's included</h2>
    <ul class="feature-grid">
        <li><i class="bi bi-file-earmark-text"></i><span><strong>Markdown articles</strong> with covers, tags, and revisions.</span></li>
        <li><i class="bi bi-file-earmark"></i><span><strong>Static pages</strong> for evergreen content.</span></li>
        <li><i class="bi bi-shield-check"></i><span><strong>Capability roles</strong> for fine-grained access.</span></li>
        <li><i class="bi bi-images"></i><span><strong>Media library</strong> with usage tracking.</span></li>
        <li><i class="bi bi-box-arrow-in-right"></i><span><strong>Google &amp; GitHub OAuth</strong> login.</span></li>
        <li><i class="bi bi-search"></i><span><strong>SEO</strong> — sitemap, robots, RSS, Open Graph.</span></li>
        <li><i class="bi bi-chat-dots"></i><span><strong>Threaded comments</strong> with moderation.</span></li>
        <li><i class="bi bi-plug"></i><span><strong>JSON API &amp; MCP</strong> for automation.</span></li>
    </ul>
</section>

<section class="panel">
    <h2><i class="bi bi-code-slash"></i> Open source</h2>
    <p><?= e($appName) ?> is developed in the open. Read the source, file issues, or contribute on GitHub.</p>
    <div class="cta-row">
        <a class="btn-primary" href="<?= e($repoUrl) ?>" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> View on GitHub</a>
    </div>
</section>
<?php renderFooter();
