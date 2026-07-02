<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$currentUser = $auth->user();

$signupNotice = null;
if (($_GET['signup'] ?? '') === 'pending') {
    $signupNotice = 'Your account has been created and is awaiting approval. An administrator must activate it before you can sign in.';
}
$resetNotice = $_SESSION['reset_notice'] ?? null;
unset($_SESSION['reset_notice']);

renderHeader('Home', $currentUser, [
    'canonical' => '/',
    'og_type' => 'website',
    'hide_h1' => true,
    'jsonLd' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $config['app']['name'] ?? 'MAMPSlate CMS',
        'url' => rtrim($config['app']['base_url'] ?? '', '/'),
    ],
]);
?>
<?php if ($signupNotice): ?>
    <p class="notice success"><?= e($signupNotice) ?></p>
<?php endif; ?>
<?php if ($resetNotice): ?>
    <p class="notice success"><?= e($resetNotice) ?></p>
<?php endif; ?>
<?php if (empty($currentUser)): ?>
    <section class="hero">
        <div class="hero-inner">
            <span class="hero-eyebrow">MAMPSlate CMS</span>
            <h1 class="hero-title"><?= e($config['app']['name']) ?></h1>
            <p class="hero-sub">A fast, dependency-free publishing platform. Read the latest below, create an account, or sign in with Google or GitHub.</p>
            <div class="hero-cta">
                <button type="button" class="auth-trigger">Sign in</button>
                <?php if (feature('articles')): ?>
                    <a class="btn-ghost" href="/articles">Browse articles</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="hero">
        <div class="hero-inner">
            <span class="hero-eyebrow">Signed in</span>
            <h1 class="hero-title">Welcome back, <?= e($currentUser['display_name']) ?></h1>
            <p class="hero-sub">You're signed in as <strong><?= e($currentUser['role_name']) ?></strong>. Jump to your profile or the dashboard.</p>
            <div class="hero-cta">
                <a class="btn-ghost" href="/profile">Your profile</a>
                <?php if ($auth->canAccessAdmin()): ?>
                    <a class="btn-ghost" href="/admin">Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (feature('articles')): ?>
    <?php $recentArticles = $articles->listPublished(1, 5, null, null); ?>
    <?php if ($recentArticles): ?>
    <section class="panel">
        <h2>Recent articles</h2>
        <div class="article-list">
            <?php foreach ($recentArticles as $item): ?>
                <article class="article-card">
                    <?php if (!empty($item['cover'])): ?>
                        <a class="article-card-cover" href="/articles/<?= e($item['slug']) ?>">
                            <img src="/uploads/<?= e($item['cover']) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                        </a>
                    <?php endif; ?>
                    <div class="article-card-body">
                        <h3><a href="/articles/<?= e($item['slug']) ?>"><?= e($item['title']) ?></a></h3>
                        <?php if ($item['summary']): ?>
                            <p><?= e($item['summary']) ?></p>
                        <?php endif; ?>
                        <p class="muted">by <?= e($item['author_name']) ?>
                            <?php if ($item['published_at']): ?>
                                on <?= e(date('M j, Y', strtotime($item['published_at']))) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="space-top"><a href="/articles">Browse all articles →</a></p>
    </section>
    <?php endif; ?>
<?php endif; ?>

<?php
renderFooter();
