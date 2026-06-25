<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

requireMethod('POST');

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!rate_limit('api_session:' . $ip, 'api_session')) {
    jsonResponse([
        'ok' => false,
        'error' => 'rate_limited',
        'message' => 'Too many attempts. Please wait and try again.',
    ], 429);
}

$body = readJsonBody();
$email = (string)($body['email'] ?? '');
$password = (string)($body['password'] ?? '');

$session = $apiAuth->issueSessionKey($email, $password);

if (!$session) {
    $audit->log('api.session.failed', null, 'user', null, ['email' => strtolower(trim($email))]);
    jsonResponse([
        'ok' => false,
        'error' => 'unauthorized',
        'message' => 'Invalid email or password.',
    ], 401);
}

$user = $users->findByEmail($email);
$audit->log('api.session.created', $user ? (int)$user['id'] : null, 'user', $user ? (string)$user['id'] : null);

jsonResponse([
    'ok' => true,
    'session_key' => $session['session_key'],
    'expires_at' => $session['expires_at'],
]);
