# Deployment Checklist

## Server

- Configure Apache document root to point to `public_html`.
- Enable `mod_rewrite`.
- Disable directory indexes globally where possible.
- Enable HTTPS.
- Redirect HTTP to HTTPS.

## First-Run Setup (recommended)

The fastest path: drop the code in place and visit `http(s)://<host>/`. If the
database is not configured, you are redirected to `/setup`. See
[setup.md](setup.md):

1. Create the site-master password (first visit only).
2. Enter MySQL credentials; test the server, create the database, test it.
3. Initialize the schema (runs `sql_init/001`–`021`).
4. Create the initial administrator account.
5. Save configuration — `config/config.local.php` is written and you are sent
   to the site.

Requirements for the wizard: the `config/` directory must be writable by the web
server, and the MySQL user needs `CREATE` plus full privileges on the target
database during setup (switch to a least-privilege user afterward).

## PHP

- Use PHP 8.2 or newer.
- Set production error handling:
  - `display_errors=Off`
  - `log_errors=On`
- Ensure PDO MySQL extension is enabled.

## Database (manual alternative to the wizard)

- Create the database.
- Create a least-privilege database user with `SELECT, INSERT, UPDATE, DELETE` on the CMS database (no DDL needed at runtime).
- Apply the SQL scripts in order: `001_schema`, `002_seed`, `003_oauth`, `004_capabilities`, `005_media`, `006_content`, `007_comments`, `008_profile_avatars`, `009_apikey_own_capability`, `010_invite_codes`, `011_audit_events`, `012_password_reset`, `013_pages`, `014_menus`, `015_settings`, `016_content_revisions`, `017_extend_user_profiles`, `018_user_email_privacy`, `019_user_email_privacy_default_hidden`, `020_starter_subsystems`, `021_seed_author_moderator_roles`. (Or use the migration runner via `/admin/migrations`, which records each in `schema_migrations`.)
- Change seeded admin password immediately.

For local development only, run `sql_init/000_reset_dev.sql` before the schema script if a previous failed import left partial tables behind.

## Application

- Copy `config/config.example.php` to `config/config.local.php`.
- Fill in database credentials, app secret, `base_url`, `signup_mode`, `mail`, `rate_limits`, and media/comment settings.
- Ensure `cache/` (health + rate-limit files) and `logs/` (mailer log) are writable by the web server.
- Review `security.csp_extra` / `security.csp_report_only` and set `security.secure_cookies`/HTTPS for production.
- Confirm `/admin/audit-log` is reachable only to administrators (`audit.view`).
- If using MCP for AI management: set `mcp.enabled` in `config.local.php`, create a dedicated AI user + API key, and review `mcp.dry_run`/`allow_publish`/`allow_delete`. See [mcp-ai-management.md](mcp-ai-management.md). Leave `mcp.enabled=false` otherwise.
- Configure OAuth providers in `config.local.php` if federated login is used (see `docs/oauth-setup.md`).
- Toggle unused subsystems off in the `features` block.
- Keep `config/config.local.php` out of version control.
- Verify login works.
- Verify administrator user management is protected.
- Verify `/api/me` requires credentials.
- Verify `/api/session` issues an expiring session key.

## Pre-Launch

- Replace default seed credentials.
- Confirm backups.
- Confirm log rotation.
- Confirm PHP and database security updates.
- Confirm Apache cannot serve `config`, `includes`, `sql_init`, or `docs`.
