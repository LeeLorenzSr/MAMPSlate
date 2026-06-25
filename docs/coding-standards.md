# Coding Standards

## PHP Version

- Target PHP 8.2 or newer.
- Use strict types in new PHP files: `declare(strict_types=1);`.
- Prefer small, explicit functions and classes over large procedural files.
- Keep public route files in `public_html` thin.
- Put reusable application logic in `includes`.
- Put environment-specific secrets and settings in `config`, outside the web root.

## Database / SQL

- Target **MySQL 5.7+** (and MariaDB 10.4+). Do not use 8.0-only SQL in
  migrations: no window functions (`ROW_NUMBER()`, `RANK()`, …), no
  `REGEXP_REPLACE`, no 8.0-only CTE semantics. If a transformation can't be
  expressed in 5.7 SQL (e.g. transliterated slugs), do it in PHP.
- All database access goes through PDO prepared statements in repository classes
  (`includes/*Repository.php`); never interpolate user input into SQL.
- Migrations in `sql_init/` are idempotent (`CREATE TABLE IF NOT EXISTS`,
  `ON DUPLICATE KEY UPDATE`, `information_schema` guards) and ordered by
  filename prefix. They are applied by `MigrationRunner`; the app performs no
  DDL at runtime.

## File Organization

- `public_html/`: Apache document root. Contains only public entry points and public assets.
- `public_html/api/`: API route entry points.
- `includes/`: application bootstrap, database access, authentication, authorization, repositories, helpers, and rendering.
- `config/`: local configuration and secrets. Never expose this folder through Apache.
- `sql_init/`: schema, seed data, and migration-style SQL scripts.
- `docs/`: specifications and operating notes.

## Naming

- Use `PascalCase` for PHP classes.
- Use `camelCase` for variables and methods.
- Use `snake_case` for database tables and columns.
- Use lowercase kebab-case for public URLs where practical.
- Use meaningful names over abbreviations.

## Database Access

- Use PDO with prepared statements for all database access.
- Do not concatenate user input into SQL.
- Store timestamps in UTC using `TIMESTAMP` or `DATETIME` consistently.
- Use integer primary keys unless there is a clear reason to use UUIDs.
- Add foreign keys for relational integrity.
- Migrations live in `sql_init/`, are numbered, idempotent
  (`CREATE TABLE IF NOT EXISTS`), and listed in `000_reset_dev.sql`.

## Error Handling

- Do not display stack traces or database errors in production.
- Log server-side details.
- Show users short, actionable messages.
- Return structured JSON errors from API endpoints.

## Forms

- Use POST for mutations.
- Require CSRF tokens for browser forms (including JSON endpoints — verify the
  token from the request body with `verifyCsrfToken()`).
- Validate all required fields server-side.
- Escape all output with `htmlspecialchars`.

## Authorization and Features

- Gate routes with `Auth::requireCapability('...')` (see
  [permissions.md](permissions.md)). New capabilities are added via a migration
  `INSERT` into `capabilities` and granted through `/admin/roles`.
- Toggle optional subsystems with the `features` config block and the
  `feature()` / `requireFeature()` helpers in `includes/http.php`. A disabled
  feature hides its admin nav and 404s its routes.
- URLs are extensionless (no `.php`) via `public_html/.htaccess`. Link to
  extensionless paths (e.g. `/profile`, `/admin/articles`, `/articles/{slug}`).

## Content rendering

- Article bodies are Markdown, rendered by `includes/MarkdownRenderer.php`. This
  is the single seam to replace with Parsedown or a WYSIWYG + HTML Purifier
  path. Never output raw, unrendered Markdown or untrusted HTML.

## Dependencies

- Prefer core PHP and small, well-maintained libraries.
- If Composer is added later, commit `composer.json` and `composer.lock`.
- Avoid copying third-party code into the repository without license review.

## Testing Expectations

- Add focused tests when business rules become non-trivial.
- Test authentication, authorization, role enforcement, API key revocation, and session expiry before production launch.
