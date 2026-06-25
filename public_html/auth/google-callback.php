<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

requireMethod('GET');

if (empty($config['app']['allow_oauth']) || !$oauth->isEnabled('google')) {
    http_response_code(404);
    exit('Not found');
}

complete_oauth_login($oauth, $users, $auth, 'google');
