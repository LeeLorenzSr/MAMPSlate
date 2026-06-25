<?php
declare(strict_types=1);

/**
 * Shared OAuth Authorization Code callback handler.
 *
 * Verifies the CSRF state, exchanges the code for an access token, resolves
 * the local user (existing linked identity, verified-email link, or new
 * account), then logs the user in and redirects home.
 */
function complete_oauth_login(OAuthClient $oauth, UserRepository $users, Auth $auth, string $provider): never
{
    $error = $_GET['error'] ?? null;
    if ($error !== null) {
        redirect('/?auth_error=provider_denied');
    }

    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    $expectedState = $_SESSION['oauth_state'] ?? '';
    unset($_SESSION['oauth_state']);

    if ($code === '' || !is_string($state) || $expectedState === '' || !hash_equals($expectedState, $state)) {
        redirect('/?auth_error=state');
    }

    try {
        $tokens = $oauth->exchangeCode($provider, $code);
        $identity = $oauth->fetchIdentity($provider, $tokens['access_token']);
    } catch (Throwable $e) {
        $GLOBALS['audit']?->log('user.login.failed', null, 'user', null, ['provider' => $provider, 'reason' => 'exchange_failed']);
        redirect('/?auth_error=oauth_failed');
    }

    $providerUserId = $identity['provider_user_id'];
    $email = $identity['email'];
    $emailVerified = (bool)$identity['email_verified'];
    $displayName = $identity['display_name'];

    // 1. Already-linked identity -> log straight in.
    $user = $users->findOAuthIdentity($provider, $providerUserId);
    if ($user && (bool)$user['is_active']) {
        $auth->loginById((int)$user['id']);
        $GLOBALS['audit']?->log('user.login.success', (int)$user['id'], 'user', (string)$user['id'], ['provider' => $provider]);
        redirect('/');
    }

    // 2/3 require a verified email to link or create by email.
    if (!$emailVerified || $email === '') {
        $GLOBALS['audit']?->log('user.login.failed', null, 'user', null, ['provider' => $provider, 'reason' => 'no_verified_email']);
        redirect('/?auth_error=no_verified_email');
    }

    // 2. Existing account with this verified email -> link and log in.
    $user = $users->findByEmail($email);
    if ($user) {
        if (!(bool)$user['is_active']) {
            redirect('/?auth_error=inactive');
        }
        $users->linkOAuthIdentity((int)$user['id'], $provider, $providerUserId, $email, $displayName);
        $auth->loginById((int)$user['id']);
        $GLOBALS['audit']?->log('user.login.success', (int)$user['id'], 'user', (string)$user['id'], ['provider' => $provider, 'linked' => true]);
        redirect('/');
    }

    // 3. No existing account -> create an OAuth-only account and link it.
    $roleId = $users->findRoleIdByName(Auth::SIGNUP_ROLE);
    if ($roleId === null) {
        redirect('/?auth_error=oauth_failed');
    }
    $newId = $users->createOAuthUser($email, $displayName, $roleId);
    $users->linkOAuthIdentity($newId, $provider, $providerUserId, $email, $displayName);
    $auth->loginById($newId);
    $GLOBALS['audit']?->log('user.signup', $newId, 'user', (string)$newId, ['provider' => $provider]);
    $GLOBALS['audit']?->log('user.login.success', $newId, 'user', (string)$newId, ['provider' => $provider]);
    redirect('/');
}
