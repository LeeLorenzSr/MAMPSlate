<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

requireMethod('POST');

$data = readJsonBody();

if (!verifyCsrfToken(isset($data['csrf_token']) ? (string)$data['csrf_token'] : null)) {
    jsonResponse([
        'ok' => false,
        'error' => 'invalid_csrf',
        'message' => 'Invalid session. Please refresh and try again.',
    ], 400);
}

$email = (string)($data['email'] ?? '');
$password = (string)($data['password'] ?? '');

if ($email === '' || $password === '') {
    jsonResponse([
        'ok' => false,
        'error' => 'invalid_credentials',
        'message' => 'Email and password are required.',
    ], 400);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!rate_limit('login:' . $ip, 'login') || !rate_limit('login_user:' . strtolower(trim($email)), 'login_user')) {
    jsonResponse([
        'ok' => false,
        'error' => 'rate_limited',
        'message' => 'Too many attempts. Please wait a while and try again.',
    ], 429);
}

if ($auth->attempt($email, $password)) {
    $user = $auth->user();
    $audit->log('user.login.success', (int)$user['id'], 'user', (string)$user['id']);
    $redirect = $auth->canAccessAdmin() ? '/admin' : '/profile';
    jsonResponse(['ok' => true, 'redirect' => $redirect]);
}

$audit->log('user.login.failed', null, 'user', null, ['email' => strtolower(trim($email))]);

jsonResponse([
    'ok' => false,
    'error' => 'invalid_credentials',
    'message' => 'Invalid email or password.',
], 401);
