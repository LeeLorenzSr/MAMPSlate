<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

requireMethod('POST');
requireFeature('pages');
$auth->requireCapability('page.create');

$data = readJsonBody();

if (!verifyCsrfToken(isset($data['csrf_token']) ? (string)$data['csrf_token'] : null)) {
    jsonResponse(['ok' => false, 'error' => 'invalid_csrf', 'message' => 'Invalid session.'], 400);
}

$markdownText = (string)($data['markdown'] ?? '');

jsonResponse(['ok' => true, 'html' => $markdown->render($markdownText)]);
