<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = $auth->user();
if ($user) {
    $audit->log('user.logout', (int)$user['id'], 'user', (string)$user['id']);
}

$auth->logout();
redirect('/');
