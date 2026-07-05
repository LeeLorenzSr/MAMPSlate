# First-Run Setup

The CMS ships with a built-in first-run setup wizard so an administrator can
drop the code into a host and configure it from the browser — no manual SQL or
config editing required.

## When the wizard appears

`includes/bootstrap.php` tries to connect to the database on every request and
verifies the core schema (the `user_roles` table) is installed. If the database
is unreachable **or** the schema is missing, the request is redirected to
`/setup`. This fails closed: a database problem shows the (locked) setup page
rather than leaking errors or serving a half-broken site.

## The site-master password

The setup page is protected by a **site-master password**, stored as a
`password_hash` in `config/sitemaster.hash` (outside the web root, gitignored).

- **First visit** (no hash file): you create the site-master password. This is
  the only unauthenticated step; perform it in a private/trusted context (e.g.
  before the site is publicly announced, or behind a temporary access restriction).
- **Later visits** (hash file exists): you must enter the site-master password
  to unlock setup. This protects reconfiguration if the database goes down later.

The site-master password guards the **setup page only**. It is separate from the
administrator account created during setup.

## The wizard steps

1. **Site master** — create or enter the site-master password.
2. **Database connection** — enter MySQL host, port, database name, user,
   password. Buttons:
   - **Test server** — connects to the MySQL server (without selecting a
     database) to verify the user/password and host.
   - **Create database** — runs `CREATE DATABASE IF NOT EXISTS ... utf8mb4`
     (requires a user with the `CREATE` privilege).
   - **Test database** — connects to the named database and reports whether the
     schema is already installed.
3. **Initialize schema** — runs the migration runner, which applies pending
   `sql_init/001`–`021` migrations in order (idempotent; safe to re-run) and
   records each in a `schema_migrations` ledger. Per-file results are shown; it
   stops at the first error.
4. **Administrator account** — sets the initial administrator email and
   password. If you keep `admin@example.test`, its password is updated; if you
   use a different email, that account is created and the seeded
   `admin@example.test` / `change-me` account is removed.
5. **Save configuration & finish** — writes `config/config.local.php` (database
   settings + detected base URL), verifies the new configuration connects and
   the schema is installed, then redirects to the site.

## What the wizard writes

- `config/config.local.php` — full configuration (based on
  `config/config.example.php`) with the database credentials and `base_url`
  filled in.
- `config/sitemaster.hash` — the site-master password hash.
- `config/installed` — a marker that tells `bootstrap.php` the schema is
  installed, so the per-request `SHOW TABLES` check is skipped (an optimization).
  The marker is also created lazily on the first healthy request, so manual
  setups benefit too. It is removed and re-verified each time setup saves.
- The database schema (`sql_init/001`–`021`) and the initial administrator row.

## Permissions required

- The `config/` directory must be writable by the web server during setup (to
  write `config.local.php` and `sitemaster.hash`).
- The MySQL user used during setup needs `CREATE` to create the database, and
  full privileges on the target database to run the migrations. After setup you
  may switch to a least-privilege user (`SELECT, INSERT, UPDATE, DELETE`) for
  runtime — the application performs no DDL at runtime.

## Returning to setup

Visit `/setup` at any time and enter the site-master password to re-test the
connection, re-run migrations (idempotent), or re-save the configuration. The
destructive `000_reset_dev.sql` is intentionally **not** run from the wizard.

## Manual alternative

If you prefer not to use the wizard, the manual steps remain documented in the
[Deployment Checklist](deployment-checklist.md): create the database and a
least-privilege user, apply `sql_init/001`–`021`, and copy
`config/config.example.php` to `config/config.local.php` with credentials
filled in.
