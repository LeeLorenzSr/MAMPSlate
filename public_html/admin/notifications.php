<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('notification.view');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $notifications->markAllRead((int)$currentUser['id']);
    redirect('/admin/notifications');
}
$items = $notifications->recent((int)$currentUser['id']);
renderHeader('Notifications', $currentUser);
?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button>Mark all read</button></form>
<section class="panel"><h2>Recent activity</h2><?php if ($items === []): ?><p class="muted">No notifications yet.</p><?php else: ?><ul class="activity-list"><?php foreach ($items as $item): ?><li><strong><?= e($item['title']) ?></strong><?php if ($item['body']): ?> — <?= e($item['body']) ?><?php endif; ?><?php if ($item['url']): ?> <a href="<?= e($item['url']) ?>">Open</a><?php endif; ?><span class="muted"> <?= e($item['created_at']) ?></span></li><?php endforeach; ?></ul><?php endif; ?></section>
<?php renderFooter();
