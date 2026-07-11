# Security Standards

## Core Principles

- Deny by default.
- Keep secrets outside `public_html`.
- Treat every request as untrusted.
- Separate authentication from authorization.
- Log security-relevant events without logging passwords, tokens, or API keys.

## Passwords

- Store passwords with `password_hash`.
- Verify passwords with `password_verify`.
- Never store plaintext passwords.
- Require password reset on first login for seeded/admin bootstrap accounts.

## Sessions

- Use secure cookie settings in production:
  - `httponly`
  - `secure`
  - `samesite=Lax` or stricter
- Regenerate session IDs after login.
- Store temporal session keys in the database for revocation and audit.
- Expire sessions after inactivity and absolute lifetime limits.

## API Keys

- API keys are bearer credentials and must be treated like passwords.
- Store only API key hashes in the database.
- Show the plaintext API key only once at creation time.
- Support revocation with `revoked_at`.
- Track last-used timestamp and source IP where possible.
- Scope API keys with roles or explicit capabilities before adding sensitive API endpoints.
- Deactivating an account immediately blocks its API keys and temporal session
  keys, even when the individual credential has not expired or been revoked.

## Outbound webhooks

- Webhooks accept HTTPS URLs only, reject embedded URL credentials, and require
  every resolved address to be public (no loopback, private, link-local, or
  reserved networks).
- Targets are resolved again at delivery time and the validated public address
  is pinned for the cURL connection, limiting DNS-rebinding and internal-network
  SSRF. Redirects remain disabled.

## Authorization

- Authorization is **capability-based**, not role-name-based. Roles map to many
  capabilities via `role_capabilities`. See [permissions.md](permissions.md).
- Protect routes with `Auth::requireCapability('...')`; use `Auth::can('...')`
  for conditional UI. Never rely on hidden navigation as authorization.
- `Auth::requireRole()` remains only as a backward-compatible wrapper; new code
  uses capabilities.

## CSRF

- Browser form POST requests require CSRF tokens.
- API bearer-token requests do not use CSRF tokens.

## SQL Injection

- Use PDO prepared statements.
- Avoid dynamic SQL.
- If dynamic ordering/filtering is needed, use allowlists.

## XSS

- Escape all HTML output with `htmlspecialchars` (the `e()` helper).
- Do not print raw user-supplied HTML unless it has passed a sanitizer with an explicit allowlist.
- Article bodies are authored in Markdown and rendered by
  `includes/MarkdownRenderer.php`, which **escapes raw HTML** and restricts
  link/image URL schemes to `http(s)`, `mailto`, and safe relative URLs. The
  cached `body_html` may be output raw because it has passed through this
  renderer. If Markdown is ever replaced with a WYSIWYG HTML editor, route the
  HTML through an allowlist sanitizer (e.g. HTML Purifier) before caching.
- Set a Content Security Policy before production launch.

## OAuth (federated login)

- The OAuth Authorization Code flow uses a random `state` value stored in the
  session and verified with `hash_equals` on callback to prevent CSRF.
- OAuth identities are linked to a local account **only when the provider
  returns a verified email** (see [oauth-setup.md](oauth-setup.md)).
- Provider access tokens are used once server-side and are never stored or
  logged.

## Media uploads

- Validate uploads with `getimagesize` and an allowlist of image MIME types
  (JPEG, PNG, GIF, WebP). Reject non-images.
- Enforce a maximum upload size (`app.media_max_upload_bytes`) and downscale
  large images (`app.media_image_max_width`).
- Store files outside any script-execution context. `public_html/uploads/.htaccess`
  denies execution of script extensions and disables the PHP engine where the
  handler exists.
- Generate stored filenames server-side with `random_bytes` (never trust the
  client-supplied filename for storage).

## Comments

- Comments require an authenticated user (no anonymous posting).
- A per-user throttle (`app.comments_per_minute`) limits rapid posting.
- Comments are escaped on output with `e()`/`nl2br()`; they are never rendered
  as raw HTML.
- Optional moderation queue (`app.comments_require_approval`) holds comments as
  `pending` until approved.

## First-run setup

- If the database is unreachable or the schema is missing, all requests redirect
  to `/setup` (fail closed) rather than leaking errors. See [setup.md](setup.md).
- The setup page is gated by a site-master password stored as a `password_hash`
  in `config/sitemaster.hash` (outside the web root, gitignored).
- The only unauthenticated action is creating the site-master password on the
  very first visit (when no hash file exists). Deploy in a private context.
- Setup writes `config/config.local.php` (database credentials) using
  `var_export`, which safely escapes special characters in credentials.
- The destructive `000_reset_dev.sql` is never run from the wizard.

## Audit logging

- Security-relevant events are recorded in `audit_events` via
  `includes/AuditLogger.php`: login success/failure, logout, signup, password
  reset requested/completed, user created/updated/activated/deactivated, role
  changes, API key created/revoked, article created/updated/published/archived/
  deleted, comment approved/rejected/spam/deleted, and API session creation.
- Metadata is sanitized: any key whose name contains `password`, `token`,
  `secret`, `api_key`, `hash`, `code`, or `credential` is replaced with
  `[redacted]`. Plaintext passwords, API keys, session keys, OAuth tokens, and
  reset tokens are never logged.
- The log is viewable at `/admin/audit-log` by users with the `audit.view`
  capability (administrators by default), with filters and pagination.

## Password reset

- Reset tokens are 32 random bytes, stored only as a SHA-256 hash
  (`password_reset_tokens`). The plaintext token appears only in the emailed
  link and is never persisted or logged.
- Tokens expire (default 30 minutes) and are single-use. On a successful reset,
  all of the user's other reset tokens are invalidated and their temporal API
  sessions are revoked.
- `/auth/forgot-password` always returns the same generic success message
  whether or not the email exists, to prevent user enumeration.
- Reset requests and completions are audit-logged. The reset link is delivered
  through the mailer abstraction (`includes/Mailer.php`); in `log` mode it is
  written to `logs/mail.log` (outside the web root, gitignored) for local
  testing.

## Rate limiting

- A file-backed sliding-window limiter (`includes/RateLimiter.php`) under
  `cache/ratelimit/` protects login, signup, password reset (request and reset),
  and API session creation. Comment posting is throttled separately via the
  existing per-user DB check.
- Limits are configurable in `rate_limits` (per IP and, for login/forgot, per
  email). Blocked attempts return a generic `rate_limited` error that does not
  reveal whether an email exists.
- Blocked attempts are logged via `error_log` (not the audit table) to avoid
  log spam.

## Security headers and CSP

- `includes/http.php::security_headers()` sets `Content-Security-Policy`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy`,
  `X-Frame-Options: DENY` (plus CSP `frame-ancestors 'none'`),
  `Permissions-Policy`, and HSTS over HTTPS. Authenticated/admin/JSON responses
  are marked `no-store` via `prevent_caching()`.
- The default CSP is `default-src 'self'; script-src 'self' 'nonce-<per-request>'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'`. Extra directives can be added via
  `security.csp_extra`; set `security.csp_report_only` to test in Report-Only
  mode.
- Inline scripts (theme bootstrap, JSON-LD) carry a per-request nonce. Inline
  `onclick` confirm handlers were removed in favor of `data-confirm`
  attributes wired by a nonce'd script, so no `'unsafe-inline'` is needed for
  scripts. `style-src 'unsafe-inline'` remains for inline styles (avatar sizing,
  setup page).
- OAuth flows are top-level redirects and need no CSP exceptions.

## MCP (AI management)

- The MCP endpoint at `/mcp` reuses the same API auth (bearer/session) and
  capability authorization as the web UI and `/api/v1`; it never bypasses Auth,
  repositories, validation, audit, feature toggles, or Markdown rendering.
- MCP is off by default (`mcp.enabled`). `dry_run`, `allow_publish`, and
  `allow_delete` flags provide layered safety; hard delete and security/admin
  tools are not exposed in v1.
- Only the bare `public_html/mcp/index.php` is web-served; all implementation is
  in `includes/Mcp/` (outside the doc root).
- Tool inputs are validated with strict allowlists (`additionalProperties:
  false`); `body_html`, `author_id` overrides, and unknown fields are rejected.
- Mutations are audit-logged with `source="mcp"` and the tool name; credentials
  and secrets are never logged. See [mcp-ai-management.md](mcp-ai-management.md).

## Manual verification

1. **Audit:** sign in, out, create/edit/publish/delete an article, approve a
   comment, create/revoke an API key — confirm each appears in `/admin/audit-log`
   with no secret values in the Details column.
2. **Password reset:** visit `/auth/forgot-password`, enter an email; in `log`
   mail mode, open `logs/mail.log`, click the reset link, set a new password,
   and sign in. Confirm a non-existent email returns the same generic message.
3. **Rate limiting:** submit wrong logins repeatedly from one IP until the
   `rate_limited` message appears; confirm it clears after the window. Repeat
   for forgot-password and `/api/session`.
4. **CSP:** open browser devtools on a normal page and the article editor; confirm
   no CSP violations in the console and that inline scripts (theme, preview) run.
5. **Headers:** `curl -I` a page and confirm `Content-Security-Policy`,
   `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, and
   `Cache-Control: no-store` (on `/profile` or `/admin/*`).

## Apache

- Disable directory indexes in every public subdirectory with `.htaccess`.
- Route API extensionless URLs through rewrite rules.
- Do not allow direct access to `config`, `includes`, `sql_init`, or `docs`.

## Production Checklist

- Use HTTPS only.
- Set `APP_ENV=production`.
- Disable PHP display errors.
- Restrict database users to the minimum privileges required.
- Back up the database before applying schema changes.
- Rotate seeded passwords and generated secrets after first deployment.
