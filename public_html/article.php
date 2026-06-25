<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

requireFeature('articles');

$currentUser = $auth->user();

$slug = (string)($_GET['slug'] ?? '');
$article = $slug !== '' ? $articles->findBySlug($slug) : null;

if (!$article || $article['status'] !== 'published'
    || $article['published_at'] === null
    || strtotime($article['published_at']) > time()) {
    http_response_code(404);
    renderHeader('Not found', $currentUser);
    echo '<p>The article you were looking for is not available.</p>';
    renderFooter();
    exit;
}

$tags = $articles->tagsForArticle((int)$article['id']);
$cover = null;
if ($article['cover_media_id']) {
    $cover = $media->findById((int)$article['cover_media_id']);
}

$approvedComments = $comments->listApprovedForArticle((int)$article['id']);
$commentTree = buildCommentTree($approvedComments);
$commentCount = $comments->countForArticle((int)$article['id']);
$commentError = $_SESSION['comment_error'] ?? null;
unset($_SESSION['comment_error']);
$commentNotice = $_SESSION['comment_notice'] ?? null;
unset($_SESSION['comment_notice']);

function buildCommentTree(array $flat, int $parentId = 0): array
{
    $tree = [];
    foreach ($flat as $row) {
        $pid = (int)($row['parent_id'] ?? 0);
        if ($pid === $parentId) {
            $row['children'] = buildCommentTree($flat, (int)$row['id']);
            $tree[] = $row;
        }
    }
    return $tree;
}

function renderComments(array $tree, int $depth = 0): void
{
    foreach ($tree as $comment) {
        $created = date('M j, Y \a\t g:ia', strtotime($comment['created_at']));
        ?>
        <div id="comment-<?= (int)$comment['id'] ?>" class="comment" style="margin-left: <?= $depth * 24 ?>px">
            <div class="comment-meta">
                <strong><?= e($comment['author_name']) ?></strong>
                <span class="muted"><?= e($created) ?></span>
            </div>
            <div class="comment-body"><?= nl2br(e($comment['body'])) ?></div>
            <button type="button" class="comment-reply" data-parent="<?= (int)$comment['id'] ?>" data-name="<?= e($comment['author_name']) ?>">Reply</button>
            <?php if (!empty($comment['children'])): ?>
                <?php renderComments($comment['children'], $depth + 1); ?>
            <?php endif; ?>
        </div>
        <?php
    }
}

$seoDescription = $article['meta_description'] !== '' ? $article['meta_description'] : $article['summary'];
$ogImage = $cover ? ($GLOBALS['config']['app']['base_url'] ?? '') . '/uploads/' . $cover['stored_name'] : null;
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $article['title'],
    'author' => ['@type' => 'Person', 'name' => $article['author_name']],
    'datePublished' => $article['published_at'] ? date('c', strtotime($article['published_at'])) : null,
    'dateModified' => date('c', strtotime($article['updated_at'])),
    'description' => $seoDescription,
];
if ($ogImage) {
    $jsonLd['image'] = $ogImage;
}

renderHeader(
    $article['meta_title'] !== '' ? $article['meta_title'] : $article['title'],
    $currentUser,
    [
        'description' => $seoDescription,
        'canonical' => '/articles/' . $article['slug'],
        'og_type' => 'article',
        'og_image' => $ogImage,
        'jsonLd' => $jsonLd,
    ]
);
?>
<article class="article-view">
    <?php if ($article['category_name']): ?>
        <p class="muted"><a href="/category/<?= e($article['category_slug']) ?>"><?= e($article['category_name']) ?></a></p>
    <?php endif; ?>
    <h2><?= e($article['title']) ?></h2>
    <p class="muted">by <?= e($article['author_name']) ?>
        <?php if ($article['published_at']): ?>
            on <time datetime="<?= e(date('c', strtotime($article['published_at']))) ?>"><?= e(date('M j, Y', strtotime($article['published_at']))) ?></time>
        <?php endif; ?>
    </p>

    <?php if ($cover): ?>
        <img class="article-cover" src="/uploads/<?= e($cover['stored_name']) ?>" alt="<?= e($cover['alt_text'] ?: $article['title']) ?>">
    <?php endif; ?>

    <div class="article-body">
        <?= $article['body_html'] ?>
    </div>

    <?php if ($tags): ?>
        <p class="article-tags">
            <?php foreach ($tags as $t): ?>
                <a class="tag" href="/tag/<?= e($t['slug']) ?>">#<?= e($t['name']) ?></a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
</article>

<?php /* Comments are added in Phase 4. */ ?>
<section class="panel" id="comments">
    <h2>Comments (<?= (int)$commentCount ?>)</h2>

    <?php if ($commentError): ?>
        <p class="notice error"><?= e($commentError) ?></p>
    <?php endif; ?>
    <?php if ($commentNotice): ?>
        <p class="notice success"><?= e($commentNotice) ?></p>
    <?php endif; ?>

    <?php if ($currentUser): ?>
        <form method="post" action="/comment" class="comment-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="article_id" value="<?= (int)$article['id'] ?>">
            <input type="hidden" name="parent_id" id="parent-id" value="">
            <p id="reply-context" class="muted" hidden></p>
            <label>
                <span class="sr-only">Your comment</span>
                <textarea name="body" rows="3" required placeholder="Write a comment…"></textarea>
            </label>
            <button type="submit">Post comment</button>
        </form>
    <?php else: ?>
        <p class="muted">
            <button type="button" class="auth-trigger">Sign in</button> to leave a comment.
        </p>
    <?php endif; ?>

    <?php if (!$commentTree): ?>
        <p class="muted">No comments yet.</p>
    <?php else: ?>
        <div class="comment-list">
            <?php renderComments($commentTree); ?>
        </div>
    <?php endif; ?>
</section>

<script src="/assets/comments.js" defer></script>
<?php renderFooter();
