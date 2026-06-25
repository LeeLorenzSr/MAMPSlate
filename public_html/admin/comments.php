<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('comments');

$currentUser = $auth->requireCapability('comment.moderate');
$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['comment_id'] ?? 0);

    try {
        switch ($action) {
            case 'approve':
                $comments->setStatus($id, 'approved');
                $audit->log('comment.approved', (int)$currentUser['id'], 'comment', (string)$id);
                $message = 'Comment approved.';
                break;
            case 'pending':
                $comments->setStatus($id, 'pending');
                $audit->log('comment.unapproved', (int)$currentUser['id'], 'comment', (string)$id);
                $message = 'Comment unapproved.';
                break;
            case 'spam':
                $comments->setStatus($id, 'spam');
                $audit->log('comment.spam', (int)$currentUser['id'], 'comment', (string)$id);
                $message = 'Comment marked as spam.';
                break;
            case 'reject':
                $comments->setStatus($id, 'rejected');
                $audit->log('comment.rejected', (int)$currentUser['id'], 'comment', (string)$id);
                $message = 'Comment rejected.';
                break;
            case 'delete':
                $comments->delete($id);
                $audit->log('comment.deleted', (int)$currentUser['id'], 'comment', (string)$id);
                $message = 'Comment deleted.';
                break;
            default:
                $error = 'Unknown action.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$queue = $comments->listForModeration();

renderHeader('Comment moderation', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel">
    <h2>Moderation queue</h2>
    <?php if (!$queue): ?>
        <p class="muted">No comments to moderate.</p>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Author</th>
                    <th>Comment</th>
                    <th>On</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queue as $c): ?>
                    <tr>
                        <td><?= e($c['author_name']) ?></td>
                        <td class="comment-cell"><?= e(mb_strimwidth($c['body'], 0, 160, '…')) ?></td>
                        <td><a href="/articles/<?= e($c['article_slug']) ?>#comments"><?= e($c['article_title']) ?></a></td>
                        <td><?= e($c['status']) ?></td>
                        <td><?= e($c['created_at']) ?></td>
                        <td>
                            <form method="post" class="inline-actions">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                                <?php if ($c['status'] !== 'approved'): ?>
                                    <button type="submit" name="action" value="approve">Approve</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="pending">Unapprove</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="spam" class="danger">Spam</button>
                                <button type="submit" name="action" value="delete" class="danger" data-confirm="Delete this comment?">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();
