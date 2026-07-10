<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

requireFeature('webhooks');
$currentUser = $auth->requireCapability('webhook.manage');
$message = null;
$error = null;
$createdSecret = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    try {
        if (($_POST['action'] ?? '') === 'create') {
            $secret = trim((string)($_POST['signing_secret'] ?? ''));
            if ($secret === '') { $secret = bin2hex(random_bytes(24)); }
            $webhooks->create(['name' => $_POST['name'] ?? '', 'event_name' => $_POST['event_name'] ?? '', 'target_url' => $_POST['target_url'] ?? '', 'signing_secret' => $secret, 'is_active' => isset($_POST['is_active'])]);
            $createdSecret = $secret;
            $message = 'Webhook created.';
        } elseif (($_POST['action'] ?? '') === 'delete') {
            $webhooks->delete((int)($_POST['id'] ?? 0));
            $message = 'Webhook deleted.';
        }
        $audit->log('webhook.updated', (int)$currentUser['id'], 'webhook');
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
renderHeader('Webhooks', $currentUser);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?><?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<?php if ($createdSecret): ?><section class="notice success"><strong>Copy this signing secret now:</strong> <code><?= e($createdSecret) ?></code>. It is not displayed again.</section><?php endif; ?>
<section class="panel"><h2>New webhook endpoint</h2><p class="muted">Activating an endpoint is an explicit administrator approval for delivery. The system signs JSON with <code>X-MAMPSlate-Signature</code> and records failures; it does not retry in the background.</p><form method="post" class="grid-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create"><label>Name<input name="name" required></label><label>Event<select name="event_name"><?php foreach (WebhookRepository::EVENTS as $event): ?><option value="<?= e($event) ?>"><?= e($event) ?></option><?php endforeach; ?></select></label><label>HTTPS target URL<input name="target_url" type="url" required placeholder="https://example.com/hooks/content"></label><label>Signing secret (blank to generate)<input name="signing_secret" autocomplete="off"></label><label class="inline"><input type="checkbox" name="is_active"> Activate delivery</label><p><button>Create webhook</button></p></form></section>
<section class="panel"><h2>Configured endpoints</h2><?php $endpoints = $webhooks->all(); if ($endpoints === []): ?><p class="muted">No webhooks configured.</p><?php else: ?><table><thead><tr><th>Name</th><th>Event</th><th>Target</th><th>State</th><th>Last delivery</th><th></th></tr></thead><tbody><?php foreach ($endpoints as $endpoint): ?><tr><td><?= e($endpoint['name']) ?></td><td><code><?= e($endpoint['event_name']) ?></code></td><td><?= e($endpoint['target_url']) ?></td><td><?= !empty($endpoint['is_active']) ? 'Active' : 'Disabled' ?></td><td><?= e($endpoint['last_delivered_at'] ?: 'Never') ?> <?= $endpoint['last_error'] ? '— ' . e($endpoint['last_error']) : '' ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$endpoint['id'] ?>"><button class="danger" data-confirm="Delete this webhook?">Delete</button></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<?php renderFooter();
