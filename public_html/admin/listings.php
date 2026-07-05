<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('listings');

$currentUser = $auth->requireCapability('listing.manage');
$message = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['listing_id'] ?? 0);
    $item = $listings->findById($id);
    if (!$item) {
        $error = 'Listing not found.';
    } elseif ($action === 'publish') {
        $listings->publish($id);
        $audit->log('listing.published', (int)$currentUser['id'], 'listing', (string)$id);
        $message = 'Listing published.';
    } elseif ($action === 'delete') {
        $listings->delete($id);
        $audit->log('listing.deleted', (int)$currentUser['id'], 'listing', (string)$id);
        $message = 'Listing deleted.';
    }
}

$items = $listings->listForAdmin();

renderHeader('Listings', $currentUser);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>

<section class="panel">
    <p><a href="/admin/listing-edit">New listing</a></p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Title</th><th>Status</th><th>Owner</th><th>Updated</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><a href="/admin/listing-edit?id=<?= (int)$item['id'] ?>"><?= e($item['title']) ?></a></td>
                        <td><?= e($item['status']) ?></td>
                        <td><?= e($item['owner_name'] ?: 'None') ?></td>
                        <td><?= e($item['updated_at'] ?? '') ?></td>
                        <td>
                            <div class="action-bar">
                                <a class="icon-action" href="/admin/listing-edit?id=<?= (int)$item['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php if ($item['status'] !== 'published'): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="publish">
                                        <input type="hidden" name="listing_id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="icon-action" title="Publish" data-confirm="Publish this listing?"><i class="bi bi-send"></i></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="listing_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="icon-action danger" title="Delete" data-confirm="Delete this listing?"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();
