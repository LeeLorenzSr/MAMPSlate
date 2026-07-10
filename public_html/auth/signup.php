<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

requireMethod('POST');

$mode = setting('signup_mode', $config['app']['signup_mode'] ?? 'off');

// Treat 'off' and unknown modes as disabled.
$allowedModes = ['open', 'restricted', 'invite'];
if (!in_array($mode, $allowedModes, true)) {
    jsonResponse([
        'ok' => false,
        'error' => 'signup_disabled',
        'message' => 'Public signup is not available.',
    ], 403);
}

$data = readJsonBody();

if (!verifyCsrfToken(isset($data['csrf_token']) ? (string)$data['csrf_token'] : null)) {
    jsonResponse([
        'ok' => false,
        'error' => 'invalid_csrf',
        'message' => 'Invalid session. Please refresh and try again.',
    ], 400);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!rate_limit('signup:' . $ip, 'signup')) {
    jsonResponse([
        'ok' => false,
        'error' => 'rate_limited',
        'message' => 'Too many signup attempts. Please wait a while and try again.',
    ], 429);
}

// Validate an invite code up front so we never create an account for a bad code.
$invite = null;
if ($mode === 'invite') {
    $code = trim((string)($data['invite_code'] ?? ''));
    if ($code === '') {
        jsonResponse([
            'ok' => false,
            'error' => 'invite_required',
            'message' => 'An invite code is required to sign up.',
        ], 422);
    }

    $invite = $invites->findByCode($code);
    if (!$invite || !$invites->isValid($invite)) {
        jsonResponse([
            'ok' => false,
            'error' => 'invite_invalid',
            'message' => 'That invite code is invalid, expired, or already used.',
        ], 422);
    }
}

// 'restricted' creates an inactive account pending admin approval.
$active = $mode !== 'restricted';
$login = $active;

try {
    $userId = $auth->signup(
        (string)($data['email'] ?? ''),
        (string)($data['display_name'] ?? ''),
        (string)($data['password'] ?? ''),
        $active,
        $login
    );
} catch (Throwable $exception) {
    jsonResponse([
        'ok' => false,
        'error' => 'signup_failed',
        'message' => $exception->getMessage(),
    ], 422);
}

if ($invite !== null) {
    $invites->consume((int)$invite['id']);
}

$audit->log($active ? 'user.signup' : 'user.signup.pending', $login ? $userId : null, 'user', (string)$userId, ['mode' => $mode]);
$notifications->create(null, 'user.signed_up', $active ? 'New user signup' : 'New user awaiting approval', '', '/admin/users');
$webhookDispatcher->dispatch('user.signed_up', ['user_id' => $userId, 'active' => $active, 'mode' => $mode]);

if (!$active) {
    jsonResponse(['ok' => true, 'redirect' => '/?signup=pending']);
}

jsonResponse(['ok' => true, 'redirect' => '/profile']);
