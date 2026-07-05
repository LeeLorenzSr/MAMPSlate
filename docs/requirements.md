# System Requirements

This document lists the PHP modules, server, and database components required to
run the CMS. Required modules are needed for the core system to function;
optional modules are needed only when the corresponding feature is enabled.

## PHP

- **PHP 8.2 or newer.** The code uses `declare(strict_types=1)`, constructor
  property promotion, named arguments, `match`, `str_starts_with`, and
  nullsafe (`?->`) operators.

### Required PHP extensions

| Extension   | Why                                                                 |
|-------------|---------------------------------------------------------------------|
| `pdo_mysql` | All database access via PDO (`includes/Database.php`, repositories). |
| `mbstring`  | Multibyte string handling in slugs/tags (`mb_strtolower`) and admin comment previews (`mb_strimwidth`). |
| `session`   | Browser sessions and CSRF tokens (bundled, must not be disabled).   |
| `json`      | API/JSON responses and JSON-LD output (bundled).                    |
| `hash`      | CSRF token and credential hashing (`hash_equals`, `hash`) (bundled).|
| `filter`    | Email validation in signup (`filter_var`) (bundled).                |
| `standard`  | `random_bytes`, `date`, `file_get_contents`, `strtotime`, etc. (bundled). |

### Conditionally required PHP extensions

| Extension  | When needed                                            |
|------------|--------------------------------------------------------|
| `gd`       | The **media** feature (`includes/ImageProcessor.php`) — image upload validation, resizing, and thumbnailing. Requires JPEG, PNG, GIF, and WebP support compiled into GD. Disable the `media` feature to drop this dependency. |
| `curl`     | OAuth login (`includes/OAuthClient.php`) — token exchange and userinfo requests to Google/GitHub. Only required when at least one OAuth provider is enabled. |
| `intl`     | Recommended for slug generation (`includes/Slug.php`) — `transliterator_transliterate` strips accents to ASCII. `Slug::slugify()` falls back to a simpler transliteration if `intl` is absent, so it is optional but recommended for non-ASCII titles. |

### Verifying installed extensions

From a shell:

```bash
php -m
```

Or via a `phpinfo()` page in `public_html/`. Confirm at least: `pdo_mysql`,
`mbstring`, `gd` (if using media), `curl` (if using OAuth).

## Web server

- **Apache 2.4** with:
  - `mod_rewrite` enabled — required for extensionless URLs and content routes
    (`public_html/.htaccess`).
  - `AllowOverride All` (or at least `FileInfo` + `Options`) on the document
    root so `.htaccess` directives take effect.
  - Apache 2.4 authz syntax (`Require all denied`) is used to protect the
    uploads directory; `mod_php` `php_flag engine off` directives are guarded by
    `<IfModule>` so they are skipped when not applicable.
- Document root must point at `public_html/`. `config/`, `includes/`,
  `sql_init/`, and `docs/` live outside the document root and must never be
  served.
- MAMP (Mac/Windows) ships all of the above by default for local development.

## Database

- **MySQL 5.7+** or **MariaDB 10.4+**. SQL is written to target MySQL 5.7, so
  migrations avoid 8.0-only features (no window functions such as `ROW_NUMBER()`,
  no `REGEXP_REPLACE`). String transformations that 5.7 cannot do in pure SQL
  (e.g. transliterated URL slugs) are performed in PHP instead.
  - `utf8mb4` character set and `utf8mb4_unicode_ci` collation are used
    throughout.
  - `JSON` column type is used by `api_keys.scopes` (available in MySQL 5.7.8+).
  - `TIMESTAMP`, foreign keys, and `ON DELETE CASCADE`/`SET NULL` actions are
    used extensively.
- The application performs **no DDL at runtime**. The database user needs only
  `SELECT, INSERT, UPDATE, DELETE` on the CMS database. Apply schema/migration
  scripts (`sql_init/001`–`021`) as a separate privileged user.

## Filesystem

- `public_html/uploads/` must exist and be **writable by the web server** user
  (PHP creates `{YYYY}/{MM}/` subdirectories on upload). It is protected by
  `.htaccess` to prevent script execution.
- `config/config.local.php` must be readable by PHP but should never be
  committed to a shared repository (it is listed in `.gitignore`).
- The `config/` directory must be **writable by the web server** during
  first-run setup (the wizard writes `config.local.php` and `sitemaster.hash`),
  and the `installed` marker is written there on first healthy request.
  See [setup.md](setup.md).
- The `cache/` directory must be **writable by the web server** for the cached
  `/api/health` response (`cache/health.json`) and the file-backed rate limiter
  (`cache/ratelimit/`).
- The `logs/` directory must be **writable by the web server** for the mailer
  log in `log` mode (`logs/mail.log`).

## Local development (MAMP)

MAMP provides Apache, MySQL, PHP, and the required extensions out of the box.
After cloning:

1. Point MAMP's document root to `public_html/`.
2. Create the database and a least-privilege MySQL user.
3. Apply `sql_init/001` through `sql_init/021`.
4. Copy `config/config.example.php` to `config/config.local.php` and fill in
   credentials.
5. Visit `http://localhost/`.

See the [Deployment Checklist](deployment-checklist.md) for the full procedure.
