# Extending the CMS

This document is the contract for AI agents (and humans) adding a new subsystem
to the base CMS. Follow it so new features stay consistent, secure, and
toggleable.

## Standard subsystem pattern

A "subsystem" is a new content type or feature area (e.g. events, newsletters,
forms). Every subsystem must include these layers:

1. **Migration** — `sql_init/0NN_description.sql`, idempotent
   (`CREATE TABLE IF NOT EXISTS`, `ON DUPLICATE KEY UPDATE`). Add the table to
   `sql_init/000_reset_dev.sql`. Use the next free number; never renumber
   existing files. The migration runner applies it automatically in filename
   order.
2. **Repository** — `includes/ThingRepository.php`, PDO prepared statements, no
   raw user input in SQL. Add search/count helpers if the feature is searchable.
3. **Route files** — thin files in `public_html/` (and `public_html/admin/`).
   Public routes use extensionless URLs via `.htaccess`. Admin routes gate with
   `requireCapability()`. Call `requireFeature('things')` at the top.
4. **Admin nav** — add the link in `includes/layout.php`, gated by the capability
   AND `feature('things')`.
5. **Capabilities** — add via the same migration (INSERT into `capabilities`,
   grant to `administrator`, optionally `editor`). Document in
   `docs/permissions.md`.
6. **Feature toggle** — add `things` to the `features` block in
   `config/config.example.php` and to the toggles list in `/admin/settings`.
   `feature('things')` reads DB settings first, then config. Disabled features
   must hide their nav and 404 their routes.
7. **API** (if applicable) — add a route case in `public_html/api/v1/index.php`,
   authorized by capability, never exposing hashes. Document in
   `docs/api-v1.md` and `docs/openapi-v1.yaml`.
8. **Tests / manual verification** — add focused tests when business rules are
   non-trivial; otherwise document manual verification steps in the relevant
   doc.
9. **Documentation** — update `docs/architecture.md` (routes),
   `docs/database-specification.md` (table + migration row),
   `docs/content-management.md` (or a new feature doc), and
   `docs/deployment-checklist.md` (migration in the apply list).

## Naming conventions

- **Tables**: snake_case, plural (`events`, `event_registrations`).
- **PHP classes**: PascalCase (`EventRepository`).
- **Files**: `EventRepository.php`, `public_html/admin/events.php`,
  `public_html/admin/event-edit.php`.
- **URLs**: lowercase kebab-case, extensionless (`/events`, `/admin/event-edit`).
- **Capabilities**: `<thing>.<action>` (`event.create`, `event.edit.own`).
- **Feature keys**: lowercase single word (`events`).
- **Migration files**: `0NN_short_snake_case.sql`, zero-padded, in apply order.

## Security requirements

- **Never put private code, config, or logs under `public_html/`.** Only route
  entry points and public assets belong there. Repositories, config, SQL, docs,
  and tools live outside the document root.
- Escape all output with `e()`. Render Markdown through `MarkdownRenderer`; never
  echo raw user HTML.
- Use CSRF tokens for browser POST/JSON routes (`requireValidCsrf()` or
  `verifyCsrfToken()` from the JSON body). Bearer/session API routes do NOT use
  CSRF.
- Authorize by **capability**, never by role name.
- Never log or return plaintext secrets (passwords, tokens, API keys, hashes).
  The `AuditLogger` redacts metadata keys containing `password`, `token`,
  `secret`, `api_key`, `hash`, `code`, or `credential` as a backstop.
- Apply rate limiting to any new public mutation endpoint via `rate_limit()`.
- Send `security_headers()` on HTML/JSON responses (done automatically by
  `renderHeader()` and `jsonResponse()`).

## Completion checklist

Before declaring a feature complete, verify:

- [ ] Migration is idempotent and added to `000_reset_dev.sql`.
- [ ] Repository uses prepared statements; no SQL injection.
- [ ] Public routes are extensionless; admin routes are capability-gated.
- [ ] `requireFeature()` is called and the nav link is feature-gated.
- [ ] Capability added and granted; `permissions.md` updated.
- [ ] Feature toggle added to config + `/admin/settings`.
- [ ] Output escaped; no raw HTML; CSRF on browser mutations.
- [ ] No secrets logged or exposed.
- [ ] API endpoint (if any) is capability-gated, hash-free, and documented.
- [ ] Architecture, database-spec, content docs, and deployment checklist updated.
- [ ] Manual verification steps pass; existing flows still work.
- [ ] `php -l` passes on every new/changed PHP file.
