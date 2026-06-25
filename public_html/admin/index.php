<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireLogin();
if (!$auth->canAccessAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}

$articleCounts = feature('articles') ? $articles->countByStatus() : ['draft' => 0, 'published' => 0, 'archived' => 0];
$pageCounts = feature('pages') ? $pages->countByStatus() : ['draft' => 0, 'published' => 0, 'archived' => 0];
$pendingComments = feature('comments') ? $comments->countPending() : 0;
$recentComments = feature('comments') ? $comments->recent(5) : [];
$recentUsers = $users->recent(5);
$recentAudit = $auth->can('audit.view') ? $audit->list([], 1, 5) : [];
$mediaCount = feature('media') ? $media->count() : 0;
$mediaStorage = feature('media') ? $media->totalStorage() : 0;

renderHeader('Dashboard', $currentUser);
?>
<section class="panel">
    <h2>Overview</h2>
    <div class="dashboard-grid">
        <?php if (feature('articles')): ?>
        <div class="dash-card">
            <h3>Articles</h3>
            <p><?= (int)$articleCounts['published'] ?> published · <?= (int)$articleCounts['draft'] ?> draft · <?= (int)$articleCounts['archived'] ?> archived</p>
            <?php if ($auth->can('article.create')): ?>
                <p><a href="/admin/articles">Manage articles →</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (feature('pages')): ?>
        <div class="dash-card">
            <h3>Pages</h3>
            <p><?= (int)$pageCounts['published'] ?> published · <?= (int)$pageCounts['draft'] ?> draft · <?= (int)$pageCounts['archived'] ?> archived</p>
            <?php if ($auth->can('page.create')): ?>
                <p><a href="/admin/pages">Manage pages →</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (feature('comments')): ?>
        <div class="dash-card">
            <h3>Comments</h3>
            <p><?= (int)$pendingComments ?> pending approval</p>
            <?php if ($auth->can('comment.moderate')): ?>
                <p><a href="/admin/comments">Moderate comments →</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (feature('media')): ?>
        <div class="dash-card">
            <h3>Media</h3>
            <p><?= (int)$mediaCount ?> files · <?= e(formatBytes($mediaStorage)) ?></p>
            <?php if ($auth->can('media.upload')): ?>
                <p><a href="/admin/media">Manage media →</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (feature('comments') && $recentComments && $auth->can('comment.moderate')): ?>
<section class="panel">
    <h2>Recent comments</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Author</th><th>On</th><th>Status</th><th>When</th></tr></thead>
            <tbody>
                <?php foreach ($recentComments as $c): ?>
                    <tr>
                        <td><?= e($c['author_name']) ?></td>
                        <td><a href="/articles/<?= e($c['article_slug']) ?>#comments"><?= e($c['article_title']) ?></a></td>
                        <td><?= e($c['status']) ?></td>
                        <td><?= e($c['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Recent users</h2>
    <?php if ($auth->can('user.manage')): ?>
        <p><a href="/admin/users">Manage users →</a></p>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
            <tbody>
                <?php foreach ($recentUsers as $u): ?>
                    <tr>
                        <td><?= e($u['display_name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['role_name']) ?></td>
                        <td><?= (bool)$u['is_active'] ? 'Active' : 'Inactive' ?></td>
                        <td><?= e($u['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($recentAudit): ?>
<section class="panel">
    <h2>Recent audit events</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Time</th><th>Event</th><th>Actor</th></tr></thead>
            <tbody>
                <?php foreach ($recentAudit as $ev): ?>
                    <tr>
                        <td><?= e($ev['created_at']) ?></td>
                        <td><code><?= e($ev['event_type']) ?></code></td>
                        <td><?= e($ev['actor_name'] ?: ('#' . ($ev['actor_user_id'] ?? '—'))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p><a href="/admin/audit-log">View full audit log →</a></p>
</section>
<?php endif; ?>
<?php renderFooter();

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 1) . ' GB';
}
