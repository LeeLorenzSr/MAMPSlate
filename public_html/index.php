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
    <section class="hero hero-public">
        <div class="hero-inner">
            <div class="hero-copy">
                <span class="hero-eyebrow">MAMPSlate CMS</span>
                <h1 class="hero-title"><?= e($config['app']['name']) ?></h1>
                <p class="hero-sub">A fast, dependency-free publishing platform. Read the latest articles, create an account, or sign in with Google or GitHub.</p>
                <div class="hero-cta">
                    <button type="button" class="auth-trigger"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
                    <?php if (feature('articles')): ?>
                        <a class="btn-ghost" href="/articles"><i class="bi bi-collection"></i> Browse Articles</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-graphic" aria-hidden="true">
                <img src="/assets/img/logo.png" alt="">
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
                <a class="btn-ghost" href="/profile"><i class="bi bi-person"></i> Your profile</a>
                <?php if ($auth->canAccessAdmin()): ?>
                    <a class="btn-ghost" href="/admin"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (feature('articles')): ?>
    <?php $recentArticles = $articles->listPublished(1, 5, null, null); ?>
    <?php if ($recentArticles): ?>
    <section class="panel">
        <h2><i class="bi bi-newspaper"></i> Recent articles</h2>
        <div class="article-list">
            <?php foreach ($recentArticles as $item): ?>
                <article class="article-card">
                    <?php $cardCover = !empty($item['cover']) ? '/uploads/' . $item['cover'] : '/assets/img/default-cover.jpg'; ?>
                    <a class="article-card-cover" href="/articles/<?= e($item['slug']) ?>">
                        <img src="<?= e($cardCover) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                    </a>
                    <div class="article-card-body">
                        <h3><a href="/articles/<?= e($item['slug']) ?>"><?= e($item['title']) ?></a></h3>
                        <?php if ($item['summary']): ?>
                            <p><?= e($item['summary']) ?></p>
                        <?php endif; ?>
                        <p class="muted article-card-meta">
                            <span><i class="bi bi-person"></i> <?= e($item['author_name']) ?></span>
                            <?php if ($item['published_at']): ?>
                                <span><i class="bi bi-calendar3"></i> <?= e(date('M j, Y', strtotime($item['published_at']))) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="space-top"><a href="/articles"><i class="bi bi-arrow-right"></i> Browse all articles</a></p>
    </section>
    <?php endif; ?>
<?php endif; ?>

<?php
renderFooter();
