<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireFeature('comments');

requireMethod('POST');
requireValidCsrf();

$currentUser = $auth->user();
if (!$currentUser) {
    redirect('/');
}

$articleId = (int)($_POST['article_id'] ?? 0);
$parentId = (int)($_POST['parent_id'] ?? 0);
$parentId = $parentId > 0 ? $parentId : null;
$body = trim((string)($_POST['body'] ?? ''));

$article = $articles->findById($articleId);
if (!$article || $article['status'] !== 'published') {
    http_response_code(404);
    exit('Article not found.');
}

if ($body === '') {
    $_SESSION['comment_error'] = 'Comment cannot be empty.';
    redirect('/articles/' . $article['slug'] . '#comments');
}

// Throttle: limit comments per minute per user.
$perMinute = max(1, (int)setting('comments_per_minute', $config['app']['comments_per_minute'] ?? 5));
$since = date('Y-m-d H:i:s', time() - 60);
if ($comments->countByUserSince((int)$currentUser['id'], $since) >= $perMinute) {
    $_SESSION['comment_error'] = 'You are commenting too quickly. Please wait a moment.';
    redirect('/articles/' . $article['slug'] . '#comments');
}

$status = (string)setting('comments_require_approval') === '1' ? 'pending' : 'approved';

// If replying, ensure the parent belongs to the same article.
if ($parentId !== null) {
    $parent = $comments->findById($parentId);
    if (!$parent || (int)$parent['article_id'] !== $articleId) {
        $parentId = null;
    }
}

$commentId = $comments->create($articleId, (int)$currentUser['id'], $parentId, $body, $status);

$_SESSION['comment_notice'] = $status === 'pending'
    ? 'Your comment is awaiting moderation.'
    : null;

redirect('/articles/' . $article['slug'] . '#comment-' . $commentId);
