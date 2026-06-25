# MAMPSlate CMS

A small, dependency-free PHP/MySQL base CMS for MAMP/LAMP. Designed to be copied
into a new project and specialized — not forked. Server-rendered pages, PDO
repositories, capability-based authorization, Markdown articles, a media
library, logged-in comments, OAuth login, and SEO-friendly extensionless URLs.

See [`docs/`](docs/README.md) for full documentation, starting with
[System Requirements](docs/requirements.md) and the
[Deployment Checklist](docs/deployment-checklist.md).

## Stack

- PHP 8.2+ (pdo_mysql, mbstring required; gd and curl conditionally)
- MySQL 5.7+ / MariaDB 10.4+ (SQL targets 5.7; see `docs/database-specification.md`)
- Apache 2.4 with `mod_rewrite`

## Quick start (MAMP)

1. Point the document root at `public_html/`.
2. Visit `http://localhost/`. If the database isn't configured yet, you're
   redirected to `/setup` — the first-run wizard. See
   [`docs/setup.md`](docs/setup.md).
3. In the wizard: create a site-master password, enter MySQL credentials, create
   the database, initialize the schema, create the admin account, and save.
4. You're dropped into the running site. Sign in with the admin account you just
   created.

Prefer the manual route? Create the database and a least-privilege MySQL user,
apply `sql_init/001`–`010`, and copy `config/config.example.php` →
`config/config.local.php` with credentials.

## Layout

```
public_html/   document root (routes, assets, uploads)
includes/      bootstrap, repositories, auth, renderers (not web-accessible)
config/        local config and secrets (not web-accessible)
sql_init/      schema, seed, and migration scripts
docs/          specifications and operating notes
```

## Features

- Capability-based roles (`docs/permissions.md`)
- Modal login, signup (open/restricted/invite/off modes), Google/GitHub OAuth (`docs/oauth-setup.md`)
- Profile pictures, self-service password change, per-role API keys
- Markdown articles with media (`docs/content-management.md`)
- Static pages, navigation menus, admin dashboard, site settings UI
- Public + admin search
- Threaded comments with moderation
- SEO: sitemap, robots, RSS, Open Graph, JSON-LD
- Light/dark theme toggle
- Security hardening: audit log, password reset, rate limiting, CSP/security headers, mailer abstraction
- Content revisions (history + restore), migration runner, versioned CRUD API v1
- Media usage tracking + orphan cleanup; backup/restore runbook + CLI helpers
- Feature toggles in `config` to disable unused subsystems
- Extension checklist for AI-agent specialization (`docs/extending-the-cms.md`)
