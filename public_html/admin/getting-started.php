<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('settings.manage');
renderHeader('Getting started', $currentUser);
?>
<section class="panel"><h2>Launch checklist</h2><p class="muted">A guided, repeatable post-setup path for every new MAMPSlate deployment.</p><ol><li><a href="/admin/settings">Set branding</a>: site name, colors, typography, homepage and footer text, social links.</li><li><a href="/admin/pages">Create the first page</a> and choose its route/menu placement.</li><li><a href="/admin/articles">Create the first article</a>; add taxonomy terms, custom fields, links, and embeds after its first save.</li><li>Configure mail and OAuth in <code>config/config.local.php</code>, then confirm <a href="/admin/system-status">system status</a>.</li><li><a href="/admin/backups">Run a backup</a> and verify a restore path before launch.</li><li><a href="/admin/contact-forms">Configure the contact form</a> and recipient.</li><li>Review <a href="/admin/listings">listings</a>, <a href="/admin/demo-content">demo content</a>, <a href="/admin/webhooks">webhooks</a>, and <a href="/admin/accessibility">accessibility checks</a> as needed.</li><li>Create an API key only for an integration that needs it; then consult the API and MCP documentation.</li></ol></section>
<?php renderFooter();
