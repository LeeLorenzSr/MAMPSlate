<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

requireMethod('GET');

if (empty($config['app']['allow_oauth']) || !$oauth->isEnabled('github')) {
    http_response_code(404);
    exit('Not found');
}

$_SESSION['oauth_state'] = bin2hex(random_bytes(16));
redirect($oauth->authorizationUrl('github', $_SESSION['oauth_state']));
