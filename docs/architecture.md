# Architecture

## Hosting Model

The application is hosted as a traditional MAMP/LAMP-style PHP application:

- Apache serves `public_html` as the document root.
- PHP handles page rendering and API endpoints.
- MySQL stores users, roles, capabilities, sessions, API keys, articles, media, and comments.
- Shared code is loaded from `includes`.
- Local secrets live in `config`.

## Request Flow

1. Apache receives a request under `public_html`.
2. `.htaccess` applies routing and directory protections.
3. PHP route file loads `includes/bootstrap.php`.
4. Bootstrap loads configuration, starts the session, attempts the PDO connection, and verifies the core schema is installed. If the database is unreachable or the schema is missing, the request is redirected to `/setup` (the first-run wizard). This fails closed.
5. Route code performs authorization and delegates data access to repository classes.
6. Browser routes render HTML; API routes return JSON.

## Public Routes

All public URLs are extensionless (`.php` is hidden via `.htaccess` rewrites).

- `/`: Public landing page. Visible to guests and signed-in users. Guests see a **Sign in** corner button that opens a modal for login, signup, and OAuth. Published articles are surfaced to guests via an **Articles** link and a recent-articles list.
- `/setup`: First-run setup wizard (database, schema, admin account, config). See [setup.md](setup.md).
- `/articles`: Paginated list of published articles; optional `?category=` or `?tag=` filter, `?page=` pagination.
- `/articles/{slug}`: A single published article with comments.
- `/pages/{slug}`: A single published static page.
- `/listings`: Paginated list of published generic directory/catalog records;
  optional `?tag=` filter.
- `/listings/{slug}`: A single published listing.
- `/contact`: Default public contact form backed by `contact_forms` and
  `contact_submissions`.
- `/category/{slug}`, `/tag/{slug}`: Filtered article listings.
- `/search`: Public search across published articles and pages.
- `/comment`: POST a comment (logged-in users only).
- `/sitemap.xml`, `/robots.txt`, `/feed`: SEO endpoints.
- `/profile`: Current user's profile.
- `/user/{slug}`: Public profile page for an active user.
- `/admin`: Admin dashboard (any user with an admin/editor capability).
- `/admin/users`, `/admin/roles`, `/admin/api-keys`, `/admin/media`, `/admin/media-cleanup`, `/admin/articles`, `/admin/article-edit`, `/admin/article-preview`, `/admin/pages`, `/admin/page-edit`, `/admin/page-preview`, `/admin/listings`, `/admin/listing-edit`, `/admin/contact-submissions`, `/admin/contact-forms`, `/admin/revisions`, `/admin/comments`, `/admin/invites`, `/admin/menus`, `/admin/settings`, `/admin/migrations`, `/admin/system-status`, `/admin/exports`, `/admin/backups`, `/admin/demo-content`, `/admin/search`, `/admin/audit-log`: Admin area, gated by capabilities.
- `/logout`: Logout.
- `/api/me`, `/api/session`: API endpoints (extensionless under `/api`).
- `/api/health`: Public, file-cached health/status check.
- `/api/v1/...`: Versioned CRUD API (articles, pages, media, listings, comments). See [api-v1.md](api-v1.md).
- `/mcp`: MCP (Model Context Protocol) JSON-RPC endpoint for AI management clients. Only the bare entry point lives in `public_html/mcp/`; implementation is in `includes/Mcp/`. See [mcp-ai-management.md](mcp-ai-management.md).
- `/auth/login`, `/auth/signup`: JSON endpoints for email/password login and self-registration.
- `/auth/forgot-password`, `/auth/reset-password`: self-service password recovery.
- `/auth/google`, `/auth/github`: Start an OAuth flow.
- `/auth/google-callback`, `/auth/github-callback`: OAuth callbacks.

## Authentication and Authorization

- Email/password login and signup are handled in a modal launched from the header; guests can browse the landing page and articles without an account. Signup behavior is configured by `app.signup_mode` (`open`, `restricted`, `invite`, `off`) — see [user-management.md](user-management.md).
- Federated login (Google, GitHub) uses a hand-rolled OAuth 2.0 Authorization Code client (`includes/OAuthClient.php`). See `docs/oauth-setup.md`.
- OAuth identities are stored in `user_oauth_identities` and linked to a `users` row. OAuth-only accounts have a nullable `password_hash`.
- Authorization is capability-based, not role-name-based. Roles map to many capabilities via `role_capabilities` (`includes/CapabilityRepository.php`, `Auth::can()` / `Auth::requireCapability()`). See `docs/permissions.md`. `Auth::requireRole()` remains as a thin backward-compatible wrapper.

## Content

- Articles are authored in **Markdown** and rendered to HTML by `includes/MarkdownRenderer.php` (cached in `articles.body_html` on save). The renderer is XSS-safe (escapes raw HTML, restricts URL schemes) and is the single seam to swap for a WYSIWYG + HTML Purifier path later.
- **Static pages** (`pages` table) work the same way for evergreen content, at `/pages/{slug}`.
- **Listings** (`listings` table) are generic directory/catalog records with
  Markdown body, image, owner, normalized external links, tags, status, and SEO
  fields. They are deliberately generic so copied projects can specialize them
  into artists, products, venues, resources, or similar records without adding
  vertical-specific behavior to the core.
- **Contact forms** (`contact_forms` and `contact_submissions`) provide a
  public `/contact` route, admin form configuration, submission review, optional
  notification email, honeypot handling, and rate limiting.
- **Menus** (`menus`/`menu_items`) drive the header and footer navigation; managed at `/admin/menus`. URLs are sanitized to `http(s)`/relative.
- **Settings** (`settings` table) hold editable non-secret site config (site name, tagline, SEO defaults, signup mode, comment/media limits, feature toggles). The `setting()` helper reads DB first with config fallback; `feature()` consults `features.<name>` settings too. Secrets stay in config files.
- Media (images) are uploaded via GD (`includes/ImageProcessor.php`) and stored under `public_html/uploads/{YYYY}/{MM}/`.
- Comments are logged-in only and support threaded replies with a moderation queue.
- **Search**: public `/search` over published articles+pages; admin `/admin/search` over articles/pages/media/comments/users, scoped to the viewer's capabilities.
- **Content revisions**: every article/page save that changes tracked fields records a JSON snapshot in `content_revisions`; `/admin/revisions` shows history and can restore (a restore itself creates a new revision).
- **Versioned API**: `/api/v1/...` provides capability-gated CRUD for articles,
  pages, media, listings, and comments, authenticated by API key or temporal
  session key (no CSRF). See [api-v1.md](api-v1.md).

## Operations

- **Migration runner** (`includes/MigrationRunner.php`): tracks applied
  migrations in `schema_migrations` and applies pending ones in filename order.
  Wired into the setup wizard ("Initialize schema") and `/admin/migrations`. The
  dev-only `000_reset_dev.sql` is never run by the runner.
- **Media usage**: `includes/MediaUsage.php` tracks cover/Markdown references;
  `/admin/media` shows usage and blocks unsafe delete unless forced;
  `/admin/media-cleanup` lists orphan rows and orphan disk files.
- **Backups**: CLI helpers in `tools/` + the [backup-restore.md](backup-restore.md) runbook.
- **Exports**: `/admin/exports` provides JSON/CSV exports behind
  `export.manage`. Every dataset uses an explicit field allowlist so new
  repository columns do not automatically become downloadable.

## User Profile and Themes

- `/profile` lets a signed-in user edit their public profile details (short bio,
  profile URL handle, social links, cover photo), upload a profile picture
  (stored under `public_html/uploads/profilepics/`), change their password, and
  manage their own API keys (when their role has `apikey.own`). Profile-detail
  and cover-photo changes are written to the audit log.
- `/user/{slug}` renders a user's **public profile** — avatar, cover photo,
  display name, bio, social links, and "Member since" date. The `slug` is
  generated from the display name in PHP and made unique. Public profiles are
  viewable by anyone (guests included); inactive users return 404.
- The UI supports **light and dark themes**. A small inline script in `<head>`
  applies the saved theme (or the OS `prefers-color-scheme`) before paint to
  avoid a flash; a header toggle (`assets/theme.js`) flips it and persists the
  choice to `localStorage`. Theme colors are CSS custom properties overridden
  under `[data-theme="dark"]`.

## Feature toggles

The `features` config block (`articles`, `pages`, `comments`, `media`,
`categories`, `tags`, `seo_sitemap`, `rss_feed`, `listings`, `contact_forms`)
lets a copied project disable unused subsystems. Disabled features hide their
admin nav and return 404 on their routes via the `feature()` /
`requireFeature()` helpers in `includes/http.php`.

## Extensibility and operational extensions

Migration 022 adds a generic metadata/relationship/taxonomy/link/embed/
collection layer. `ContentExtensionRepository` owns the polymorphic extension
tables; `SitemapRegistry` merges core and local-module sitemap entries; and
`ModuleRegistry` loads trusted manifests from `modules/*/module.php`.

The public/admin/API routes share the same workflow and scheduling predicate:
scheduled content is visible only once `published_at <= CURRENT_TIMESTAMP`.
The settings screen owns lightweight branding. Optional media, notifications,
webhooks, aggregate analytics, and accessibility checks are behind feature
toggles and documented in [operations-and-integrations.md](operations-and-integrations.md).

## Private Code

The following folders must not be web-accessible:

- `config`
- `includes`
- `sql_init`
- `docs`

They are outside `public_html` in this scaffold, which is the preferred control.

## Future Architecture Additions

- Add a front controller if route count grows.
- Add Composer autoloading when dependencies are introduced.
- Add a migration runner once schema changes become frequent.
- Add centralized audit logging for user, session, and API-key management.
