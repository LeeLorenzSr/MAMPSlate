<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('accessibility_checker');
$currentUser = $auth->requireCapability('accessibility.view');
$issues = $accessibilityChecker->run();
renderHeader('Accessibility checker', $currentUser);
?>
<section class="panel"><h2>Content checks</h2><p class="muted">This deterministic pass flags likely issues for review: missing image alt text, empty Markdown image alt text, skipped heading levels, and unlabeled managed links. It does not replace a keyboard, screen-reader, or color-contrast audit.</p><?php if ($issues === []): ?><p class="notice success">No issues found.</p><?php else: ?><table><thead><tr><th>Severity</th><th>Content</th><th>Issue</th><th></th></tr></thead><tbody><?php foreach ($issues as $issue): ?><tr><td><?= e($issue['severity']) ?></td><td><?= e($issue['type'] . ' #' . $issue['id'] . ' ' . $issue['title']) ?></td><td><?= e($issue['message']) ?></td><td><a href="<?= e($issue['url']) ?>">Review</a></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<?php renderFooter();
