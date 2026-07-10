<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$currentUser = $auth->user();

$slug = (string)($_GET['slug'] ?? '');
$user = $slug !== '' ? $users->findBySlug($slug) : null;

if (!$user || !(bool)$user['is_active']) {
    http_response_code(404);
    renderHeader('Not found', $currentUser);
    echo '<p>The profile you were looking for is not available.</p>';
    renderFooter();
    exit;
}

$canViewPrivate = $currentUser && ((int)$currentUser['id'] === (int)$user['id'] || (int)$currentUser['id'] === (int)($user['claimed_by_user_id'] ?? 0));
if (($user['profile_visibility'] ?? 'public') === 'private' && !$canViewPrivate) {
    http_response_code(404);
    renderHeader('Not found', $currentUser);
    echo '<p>The profile you were looking for is not available.</p>';
    renderFooter();
    exit;
}

$claimMessage = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'claim_profile') {
    requireValidCsrf();
    if (!$currentUser || !$user['is_claimable'] || (int)$currentUser['id'] === (int)$user['id']) {
        http_response_code(403);
        exit('Forbidden');
    }
    $users->createProfileClaim((int)$user['id'], (int)$currentUser['id'], (string)($_POST['message'] ?? ''));
    $audit->log('profile.claim_requested', (int)$currentUser['id'], 'user', (string)$user['id']);
    $notifications->create(null, 'profile.claim_requested', 'Profile claim awaiting review', '', '/admin/profile-claims');
    $claimMessage = 'Claim request submitted for review.';
}

$memberSince = date('F j, Y', strtotime($user['created_at']));

// System badges: derived from existing fields, appended next to the name.
$badges = [];
if (($user['role_name'] ?? '') === 'administrator') {
    $badges[] = 'Administrator';
}
$createdTs = strtotime($user['created_at']);
if ($createdTs !== false && $createdTs < strtotime('-1 year')) {
    $badges[] = 'Veteran contributor';
}

// Build the social links that are set, in display order. Defense-in-depth: even
// though /profile validates http(s) on save, only render URLs whose scheme is
// http/https so a javascript: (or any non-web) value can never become a link.
$socialLinks = [];
foreach ([
    'GitHub'   => $user['social_github'] ?? null,
    'LinkedIn' => $user['social_linkedin'] ?? null,
    'Website'  => $user['social_website'] ?? null,
] as $label => $url) {
    if ($url !== null && $url !== '' && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
        $socialLinks[$label] = $url;
    }
}
foreach (json_decode((string)($user['profile_social_json'] ?? '[]'), true) ?: [] as $link) {
    $label = trim((string)($link['label'] ?? ''));
    $url = trim((string)($link['url'] ?? ''));
    if ($label !== '' && $url !== '' && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
        $socialLinks[$label] = $url;
    }
}

// Published articles by this author (only when the articles feature is on).
$articleItems = [];
$totalArticles = 0;
$totalPages = 1;
$page = 1;
if (feature('articles')) {
    $perPage = max(1, (int)($config['app']['articles_per_page'] ?? 10));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $totalArticles = $articles->countPublishedByAuthor((int)$user['id']);
    $totalPages = max(1, (int)ceil($totalArticles / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $articleItems = $articles->listPublishedByAuthor((int)$user['id'], $page, $perPage);
}

$seoDescription = $user['bio'] !== null && $user['bio'] !== ''
    ? $user['bio']
    : $user['display_name'] . ' on ' . setting('site.name', $config['app']['name'] ?? 'MAMPSlate CMS');

renderHeader(
    $user['display_name'],
    $currentUser,
    [
        'description' => $seoDescription,
        'canonical' => '/user/' . $user['slug'],
        'og_type' => 'profile',
    ]
);
?>
<section class="public-profile">
    <?php if (!empty($user['cover_photo'])): ?>
        <img class="profile-cover" src="/uploads/<?= e($user['cover_photo']) ?>" alt="">
    <?php endif; ?>

    <div class="profile-summary">
        <div class="profile-avatar-large"><?php renderAvatar($user, 120) ?></div>
        <div class="profile-meta">
            <h2 class="profile-name">
                <?= e($user['display_name']) ?>
                <span class="badge"><?= e(ucfirst((string)($user['profile_type'] ?? 'creator'))) ?></span>
                <?php foreach ($badges as $badge): ?>
                    <span class="badge"><?= e($badge) ?></span>
                <?php endforeach; ?>
            </h2>

            <?php if ($user['bio'] !== null && $user['bio'] !== ''): ?>
                <p class="profile-bio"><?= nl2br(e($user['bio'])) ?></p>
            <?php endif; ?>

            <?php if (!$user['hide_email']): ?>
                <p class="profile-email">
                    <a href="mailto:<?= e($user['email']) ?>"><?= e($user['email']) ?></a>
                </p>
            <?php endif; ?>

            <?php if ($socialLinks): ?>
                <ul class="profile-links">
                    <?php foreach ($socialLinks as $label => $url): ?>
                        <li>
                            <a href="<?= e($url) ?>" rel="nofollow noopener" target="_blank"><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p class="muted profile-since">Member since <?= e($memberSince) ?></p>
            <?php if ($claimMessage): ?><p class="notice success"><?= e($claimMessage) ?></p><?php endif; ?>
            <?php if ($currentUser && !empty($user['is_claimable']) && (int)$currentUser['id'] !== (int)$user['id']): ?>
                <form method="post" class="grid-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="claim_profile"><label>Claim message (optional)<textarea name="message" rows="2" maxlength="500"></textarea></label><button type="submit">Request claim</button></form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (feature('articles')): ?>
<section class="panel profile-articles">
    <h2>Articles by <?= e($user['display_name']) ?></h2>
    <?php if (!$articleItems): ?>
        <p class="empty-state"><?= e($user['display_name']) ?> has not published any articles yet.</p>
    <?php else: ?>
        <div class="article-list">
            <?php foreach ($articleItems as $item): ?>
                <article class="article-card">
                    <?php if (!empty($item['cover'])): ?>
                        <a class="article-card-cover" href="/articles/<?= e($item['slug']) ?>">
                            <img src="/uploads/<?= e($item['cover']) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                        </a>
                    <?php endif; ?>
                    <div class="article-card-body">
                        <h3><a href="/articles/<?= e($item['slug']) ?>"><?= e($item['title']) ?></a></h3>
                        <?php if ($item['category_name']): ?>
                            <p class="muted"><a href="/category/<?= e($item['category_slug']) ?>"><?= e($item['category_name']) ?></a></p>
                        <?php endif; ?>
                        <?php if ($item['summary']): ?>
                            <p><?= e($item['summary']) ?></p>
                        <?php endif; ?>
                        <?php if ($item['published_at']): ?>
                            <p class="muted"><?= e(date('M j, Y', strtotime($item['published_at']))) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php $query = http_build_query(['page' => $p]); ?>
                    <a class="<?= $p === $page ? 'current' : '' ?>" href="/user/<?= e($user['slug']) ?>?<?= e($query) ?>"><?= $p ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php renderFooter();
