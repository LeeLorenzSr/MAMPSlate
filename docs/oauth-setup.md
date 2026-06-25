# OAuth Setup (Google & GitHub)

The CMS supports federated login with Google and GitHub using a hand-rolled
OAuth 2.0 Authorization Code client (no Composer dependencies). This document
describes how to register provider apps and wire them into the config.

## How it works

1. The modal exposes **Continue with Google / GitHub** buttons linking to
   `/auth/google.php` and `/auth/github.php`.
2. Those endpoints generate a random `state` value, store it in the session,
   and redirect the browser to the provider's consent screen.
3. After consent, the provider redirects back to `/auth/google-callback.php`
   (or `github-callback.php`) with a `code` and the original `state`.
4. The callback verifies `state`, exchanges the code for an access token, and
   fetches the user's identity.
5. The user is resolved as follows:
   - If the provider identity is already linked to a local user, log in.
   - Else, if the provider returns a **verified** email matching an existing
     local account, link the identity to that account and log in.
   - Else, if the provider returns a verified email, create a new OAuth-only
     account, link the identity, and log in.
   - Otherwise, redirect home with an `?auth_error=no_verified_email` message.

OAuth-only accounts have `password_hash = NULL` and cannot log in with a
password. They can be managed like any other user from `/admin/users.php`.

## Config

Provider credentials live in `config/config.local.php` (and the example
template) under the `oauth` key:

```php
'oauth' => [
    'google' => [
        'enabled' => true,
        'client_id' => '...',
        'client_secret' => '...',
        'redirect_uri' => 'http://localhost/auth/google-callback.php',
        'scope' => 'openid email profile',
    ],
    'github' => [
        'enabled' => true,
        'client_id' => '...',
        'client_secret' => '...',
        'redirect_uri' => 'http://localhost/auth/github-callback.php',
        'scope' => 'read:user user:email',
    ],
],
```

- Set `enabled => false` (the default) to hide a provider's button and 404 its endpoints.
- Set `app.allow_oauth => false` to disable OAuth entirely.
- The `redirect_uri` here must exactly match the callback URL registered at the provider.

## Google

1. Open the Google Cloud Console → **APIs & Services → Credentials**.
2. Create an **OAuth client ID** of type *Web application*.
3. Add the exact `redirect_uri` (e.g. `http://localhost/auth/google-callback.php`)
   to **Authorized redirect URIs**.
4. Copy the Client ID and Client Secret into `config.local.php` and set
   `enabled => true`.

## GitHub

1. Open GitHub → **Settings → Developer settings → OAuth Apps → New OAuth App**.
2. Set the **Authorization callback URL** to the exact `redirect_uri`
   (e.g. `http://localhost/auth/github-callback.php`).
3. Copy the Client ID and generate a Client Secret into `config.local.php`,
   set `enabled => true`.

GitHub may return a null email when the address is set to private; the client
fetches `/user/emails` to find the primary verified address in that case.

## Security notes

- The `state` parameter guards against CSRF on the OAuth redirect.
- Account linking only happens for **verified** provider emails, which prevents
  linking to an arbitrary local account via an unverified email. Treat the
  provider email as the source of truth for identity.
- Provider access tokens are used once, server-side, and are never stored or
  logged.
