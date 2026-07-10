<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!feature('analytics') || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(404);
    exit('Not found');
}

$link = $contentExtensions->linkById((int)($_GET['link'] ?? 0));
if (!$link) {
    http_response_code(404);
    exit('Not found');
}

// The click record deliberately excludes IP address, user agent, referrer, and
// account identity. It is aggregate outbound-link measurement only.
$analytics->recordOutboundClick($link);
header('Referrer-Policy: no-referrer');
header('Location: ' . $link['url'], true, 302);
exit;
