<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('audit.view');

$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));

$filters = [
    'event_type' => $_GET['event_type'] ?? null,
    'actor' => $_GET['actor'] ?? null,
    'entity_type' => $_GET['entity_type'] ?? null,
    'from' => $_GET['from'] ?? null,
    'to' => $_GET['to'] ?? null,
];
$filters = array_map(fn($v) => (is_string($v) && trim($v) !== '') ? trim($v) : null, $filters);

$total = $audit->count($filters);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$events = $audit->list($filters, $page, $perPage);
$eventTypes = $audit->eventTypes();

renderHeader('Audit log', $currentUser);
?>
<section class="panel">
    <form method="get" class="grid-form">
        <label>Event type
            <select name="event_type">
                <option value="">(any)</option>
                <?php foreach ($eventTypes as $t): ?>
                    <option value="<?= e($t) ?>" <?= $filters['event_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Actor user id
            <input type="number" name="actor" value="<?= e($filters['actor'] ?? '') ?>">
        </label>
        <label>Entity type
            <input type="text" name="entity_type" value="<?= e($filters['entity_type'] ?? '') ?>">
        </label>
        <label>From
            <input type="datetime-local" name="from" value="<?= e($filters['from'] ?? '') ?>">
        </label>
        <label>To
            <input type="datetime-local" name="to" value="<?= e($filters['to'] ?? '') ?>">
        </label>
        <button type="submit">Filter</button>
    </form>
</section>

<section class="panel">
    <h2>Events (<?= number_format($total) ?>)</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Event</th>
                    <th>Actor</th>
                    <th>Entity</th>
                    <th>IP</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $ev): ?>
                    <tr>
                        <td><?= e($ev['created_at']) ?></td>
                        <td><code><?= e($ev['event_type']) ?></code></td>
                        <td><?= e($ev['actor_name'] ?: ('#' . ($ev['actor_user_id'] ?? '—'))) ?></td>
                        <td><?php if ($ev['entity_type']): ?><code><?= e($ev['entity_type']) ?></code> #<?= e((string)$ev['entity_id']) ?><?php else: ?>—<?php endif; ?></td>
                        <td><?= e($ev['ip_address'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($ev['metadata_json'])): ?>
                                <code class="audit-meta"><?php
                                    $meta = json_decode((string)$ev['metadata_json'], true);
                                    echo e(is_array($meta) ? json_encode($meta, JSON_UNESCAPED_SLASHES) : (string)$ev['metadata_json']);
                                ?></code>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php $query = http_build_query(array_filter(array_merge($filters, ['page' => $p]), fn($v) => $v !== null && $v !== '')); ?>
                <a class="<?= $p === $page ? 'current' : '' ?>" href="/admin/audit-log?<?= e($query) ?>"><?= $p ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>
<?php renderFooter();
