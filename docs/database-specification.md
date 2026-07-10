# Database Specification

## Conventions

- Target **MySQL 5.7+** (and MariaDB 10.4+). SQL must run on 5.7, so migrations
  avoid 8.0-only features: no window functions (`ROW_NUMBER()`, etc.), no
  `REGEXP_REPLACE`, no 8.0-only CTE semantics. Operations 5.7 cannot express in
  pure SQL — notably accent-transliterated slug generation — are done in PHP
  (`includes/Slug.php`, `UserRepository::backfillSlugs()`). Migrations are
  idempotent (`CREATE TABLE IF NOT EXISTS`, `ON DUPLICATE KEY UPDATE`,
  `information_schema` guards) and applied by `MigrationRunner`.
- Engine: InnoDB. Charset: `utf8mb4`, collation `utf8mb4_unicode_ci`.
- Integer primary keys (unsigned) unless otherwise noted.
- Foreign keys with explicit `ON DELETE` actions for relational integrity.
- Timestamps stored as `TIMESTAMP` in UTC.
- No DDL is performed at runtime. Migrations in `sql_init/` are applied by a
  privileged user; the application user has `SELECT, INSERT, UPDATE, DELETE`
  only.

## Tables

### Identity and access

- **`user_roles`** — role names (administrator, editor, viewer, user).
- **`users`** — identity, role, active status, password hash, and optional
  `avatar` path. `password_hash` is nullable to support OAuth-only accounts.
  Public-profile fields: `slug` (unique URL handle for `/user/{slug}`,
  auto-generated from `display_name`), `bio` (≤250 chars), `cover_photo` path
  (stored under `uploads/coverpics/`), `hide_email` (boolean; when true the
  email is omitted from the public profile — defaults to true/hidden, see
  migrations 018/019), and social links `social_github`, `social_linkedin`,
  `social_website`.
  Tracks `last_login_at`, `created_at`, `updated_at`.
- **`user_oauth_identities`** — federated identities (provider + provider user id)
  linked to a `users` row. Unique on `(provider, provider_user_id)`.
- **`user_sessions`** — temporal API session keys (hashed), with source, IP, user
  agent, expiry, and revocation.
- **`api_keys`** — revokable API credentials (hashed), with prefix, scopes
  (JSON), expiry, and revocation.
- **`invite_codes`** — invite codes for invite-only signup (hashed), with max
  uses, use count, expiry, revocation, and creator.

### Authorization

- **`capabilities`** — the capability catalog (e.g. `article.create`,
  `comment.moderate`). See [permissions.md](permissions.md).
- **`role_capabilities`** — many-to-many grant of capabilities to roles.

### Content

- **`pages`** — static (evergreen) pages: title, unique slug, summary, Markdown
  body + cached HTML, status, author, cover media, SEO meta, `published_at`.
  Mirrors `articles` without categories/tags.
- **`categories`** — optional article categories, with slug and optional
  `parent_id` (for future nesting).
- **`articles`** — title, unique slug, summary, `body_markdown` and cached
  `body_html`, status (`draft`/`published`/`archived`), author, category, cover
  media, SEO meta fields, and `published_at`. Indexed on
  `(status, published_at)` and `slug`.
- **`tags`** — unique name and slug.
- **`article_tags`** — many-to-many join of articles to tags.
- **`media`** — uploaded image metadata: stored name, original name, MIME, size,
  dimensions, alt text, title, and uploader.
- **`listings`** - generic directory/listing content: title, slug, summary,
  Markdown + cached HTML, image, JSON links, JSON tags, owner, status, SEO meta,
  and publish timestamps.

### Feedback

- **`comments`** — logged-in comments on articles, with `parent_id` for threaded
  replies and `status` (`pending`/`approved`/`rejected`/`spam`). Indexed on
  `(article_id, status, created_at)`.
- **`contact_forms`** - public form definitions, recipient email, active flag,
  and notification flag.
- **`contact_submissions`** - public form submissions with moderation status,
  hashed IP, user agent, and timestamps.

### Security operations

- **`audit_events`** — security-relevant events (login, logout, signup, password
  reset, user/role changes, API key, article, comment, page, menu, settings
  actions). Actor, event type, entity, IP, user agent, and sanitized JSON
  metadata. No secrets are stored. See [permissions.md](permissions.md) for the
  `audit.view` capability.
- **`password_reset_tokens`** — hashed reset tokens with expiry and single-use
  `used_at`. Only the hash is stored.

### Navigation and configuration

- **`menus`** — named menus keyed by location (`header`, `footer`).
- **`menu_items`** — label, URL (sanitized), optional `linked_type`/`linked_id`,
  `parent_id`, `sort_order`, `is_active`.
- **`settings`** — editable non-secret site settings (`key`/`value`). Secrets
  remain in config files.

### Operations

- **`schema_migrations`** — migration runner ledger (filename, hash, status,
  applied_at). Created by `includes/MigrationRunner.php`, not a numbered
  migration. The dev reset drops it.
- **`content_revisions`** — JSON snapshot history for articles and pages
  (`content_type`, `content_id`, `revision_number`, `changed_by_user_id`,
  `snapshot`, `change_note`).

## Migration Scripts

Applied in order under `sql_init/`:

| Script | Purpose |
|--------|---------|
| `000_reset_dev.sql` | Development-only full reset (drops all tables in FK-safe order). |
| `001_schema.sql` | Core identity/access tables (`user_roles`, `users`, `user_sessions`, `api_keys`). |
| `002_seed.sql` | Default roles and the bootstrap administrator. |
| `003_oauth.sql` | `user_oauth_identities`; makes `users.password_hash` nullable. |
| `004_capabilities.sql` | `capabilities`, `role_capabilities`, and default grants. |
| `005_media.sql` | `media`. |
| `006_content.sql` | `categories`, `articles`, `tags`, `article_tags`. |
| `007_comments.sql` | `comments`. |
| `008_profile_avatars.sql` | Adds `users.avatar` (idempotent). |
| `009_apikey_own_capability.sql` | `apikey.own` capability and default grants. |
| `010_invite_codes.sql` | `invite_codes`. |
| `011_audit_events.sql` | `audit_events` + `audit.view` capability. |
| `012_password_reset.sql` | `password_reset_tokens`. |
| `013_pages.sql` | `pages` + `page.*` capabilities. |
| `014_menus.sql` | `menus`, `menu_items` + `menu.manage` capability. |
| `015_settings.sql` | `settings` + `settings.manage` capability. |
| `016_content_revisions.sql` | `content_revisions`. |
| `017_extend_user_profiles.sql` | Public profile fields and profile slugs. |
| `018_user_email_privacy.sql` | User email privacy flag. |
| `019_user_email_privacy_default_hidden.sql` | Makes email privacy hidden by default. |
| `020_starter_subsystems.sql` | Listings, contact forms/submissions, operations capabilities, and feature flags. |
| `021_seed_author_moderator_roles.sql` | Author and moderator default roles. |
| `022_extensibility_and_operations.sql` | Generic fields, relationships, taxonomies, links, embeds, collections, profile claims, and operations primitives. |

The `schema_migrations` ledger table is created by the migration runner itself
(see [setup.md](setup.md) and `includes/MigrationRunner.php`), not by a numbered
file.

## Extensibility and operations tables

Migration `022_extensibility_and_operations.sql` adds
`content_field_definitions`/`content_field_values` for typed metadata,
`entity_relationships`, `taxonomies`/`taxonomy_terms`/`entity_terms`,
`external_links`, `content_embeds`, and curated content collections. It also
adds `webhook_endpoints`/`webhook_deliveries`, `notifications`, and aggregate
privacy-preserving `analytics_events` records. All polymorphic content links
are application-owned so local modules can add an entity type without a new core
foreign-key migration.

The same migration extends `users` with profile type, visibility, claimability,
claimant, and additional social-link JSON fields, and adds
`profile_claim_requests` for administrator-reviewed organization claims.

All migrations are idempotent (`CREATE TABLE IF NOT EXISTS`, `ON DUPLICATE KEY
UPDATE`). If schema changes become frequent, add a migrations table and a small
runner (see [architecture.md](architecture.md), Future Architecture Additions).
