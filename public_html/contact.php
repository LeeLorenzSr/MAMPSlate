<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$currentUser = $auth->user();
$appName = setting('site.name', $config['app']['name'] ?? 'MAMPSlate CMS');
$repoUrl = 'https://github.com/LeeLorenzSr/MAMPSlate';
$issuesUrl = 'https://github.com/LeeLorenzSr/MAMPSlate/issues';

renderHeader('Contact', $currentUser, [
    'canonical' => '/contact',
    'hide_h1' => true,
    'description' => 'Get in touch with the ' . $appName . ' project on GitHub.',
]);
?>
<section class="hero hero--centered">
    <div class="hero-inner">
        <div class="hero-copy">
            <img class="hero-logo" src="/assets/img/logo-dark.svg" alt="<?= e($appName) ?> logo">
            <h1 class="hero-title">Contact</h1>
            <p class="hero-sub">The <?= e($appName) ?> project lives on GitHub — that's the fastest way to reach us.</p>
        </div>
    </div>
</section>

<section class="panel">
    <img class="contact-banner" src="/assets/img/auth-banner.jpg" alt="">
    <h2><i class="bi bi-chat-dots"></i> Get in touch</h2>
    <p>For questions, bug reports, feature requests, or contributions, please use the GitHub repository below. Issues are the primary channel for support and feedback.</p>
    <div class="cta-row">
        <a class="btn-primary" href="<?= e($repoUrl) ?>" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> View the repository</a>
        <a class="btn-outline" href="<?= e($issuesUrl) ?>" rel="noopener"><i class="bi bi-exclamation-circle"></i> Open an issue</a>
    </div>
</section>

<section class="panel">
    <h2><i class="bi bi-globe2"></i> Repository</h2>
    <p class="contact-repo"><i class="bi bi-link-45deg"></i> <a href="<?= e($repoUrl) ?>" rel="noopener"><?= e($repoUrl) ?></a></p>
</section>
<?php renderFooter();
