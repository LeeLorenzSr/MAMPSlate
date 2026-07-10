# Content Management

## Articles

Articles are the primary published content. Each article has:

- Title and URL slug (auto-generated from the title when left blank; uniqueness
  is enforced with a `-2`, `-3` suffix).
- Summary (short excerpt used in listings and meta description).
- Body authored in **Markdown**, rendered to HTML on save and cached in
  `body_html`. The renderer (`includes/MarkdownRenderer.php`) supports headings,
  paragraphs, bold/italic, inline code, fenced code blocks, links, images,
  blockquotes, lists, and horizontal rules. Raw HTML in the source is escaped;
  link/image URLs are restricted to `http(s)`, `mailto`, and safe relative URLs.
- Status: `draft`, `published`, or `archived`. Only published articles with a
  `published_at` at or before now appear publicly.
- A category (optional) and tags (comma-separated).
- A cover image selected from the media library.
- SEO fields: meta title and meta description (used in `<title>`, meta tags,
  Open Graph, and JSON-LD).

### Authoring flow

1. **/admin/articles** lists all articles with edit/delete actions.
2. **/admin/article-edit** creates a new article; **/admin/article-edit?id=N**
   edits an existing one.
3. The editor has a live **Preview** button (renders the Markdown server-side via
   **/admin/article-preview**) and a media picker that inserts
   `![alt](/uploads/...)` Markdown into the body.
4. Only users with `article.publish` can set the status to `published`;
   otherwise the article is saved as a draft.
5. Editors can edit any article (`article.edit.any`); authors can edit their own
   (`article.edit.own`).

## Categories

Categories are optional and flat (a `parent_id` column allows future nesting).
Create a category inline from the article editor ("New category"), or by
inserting rows into `categories`. Articles filter by category at
`/category/{slug}`.

## Tags

Tags are free-form and comma-separated on the article editor. Unknown tags are
created automatically and de-duplicated by slug. Articles filter by tag at
`/tag/{slug}`.

## Media

Uploaded images are validated and (if larger than `app.media_image_max_width`)
downscaled by GD, then stored under `public_html/uploads/{YYYY}/{MM}/`. The
uploads directory disables PHP execution via `.htaccess`. Each media item has
editable alt text and title for accessibility and SEO.

## Comments

Comments are logged-in only and support one level of threaded replies (via
`parent_id`). By default comments are auto-approved; set
`comments_require_approval` (via **/admin/settings**) to queue them as `pending`
for moderation at **/admin/comments** (capability `comment.moderate`). A
per-user throttle (`comments_per_minute`) limits rapid posting.

## Static pages

Static pages are evergreen content (about, contact, legal, etc.) managed at
**/admin/pages** and **/admin/page-edit**. They mirror articles (Markdown body,
SEO meta, cover image, draft/published/archived status) but have no categories
or tags, and render at `/pages/{slug}`. Capabilities: `page.create`,
`page.edit.own`/`.any`, `page.publish`, `page.delete.own`/`.any`. Gated by the
`pages` feature toggle.

## Listings

Listings are a generic directory/catalog content type for projects that need
profiles, venues, organizations, products, partners, resources, or other
repeatable public records without baking vertical-specific fields into the CMS
core. They are managed at **/admin/listings** and **/admin/listing-edit**
(capability `listing.manage`) and render publicly at `/listings` and
`/listings/{slug}`.

Each listing has title, slug, summary, Markdown body, cached HTML, status,
optional image, optional owner user, normalized external links, free-form tags,
and SEO fields. Listing links are entered as `Label | URL` lines in admin or
`{label,url}` objects via API v1. Bare hosts such as `example.com` normalize to
`https://example.com`; non-http(s) schemes are rejected.

Use listings as the first customization point when a copied project needs a
simple public catalog. If the project needs strict typed fields or relationships
between records, add a project-specific subsystem rather than overloading the
generic listing body.

## Contact Forms

The default public contact route is `/contact`. Forms are configured at
**/admin/contact-forms** and submissions are reviewed at
**/admin/contact-submissions** (capability `contact.manage`). The default
`contact` form is seeded by migration `020_starter_subsystems.sql`.

Contact submissions store the selected form, name, email, subject, message,
moderation status, a hashed IP, and a truncated user agent. Public submission is
rate-limited and includes a honeypot field; spam/rate-limit rejections return
the same public thank-you response and log only a hashed signal. Forms can be
activated/deactivated from admin. Inactive forms return 404 publicly.

Notification email is optional per form. Recipient addresses are validated
server-side before save, and runtime mailer failures are logged without blocking
the public submission response.

## Navigation menus

Header and footer menus are managed at **/admin/menus** (capability
`menu.manage`). Each item has a label and a link that can be a custom URL, a
page, a category, or a tag. URLs are sanitized to `http(s)`/relative. Items have
a sort order and active flag. The header menu renders in the site header; the
footer menu renders in the site footer.

## Site settings

Non-secret site settings are edited at **/admin/settings** (capability
`settings.manage`) and stored in the `settings` table: site name, tagline,
default meta title/description, signup mode, comments approval/throttle, media
upload limits, and feature toggles. The `setting()` helper reads DB values first
with config fallback, so the system works before any setting is saved. Secrets
(database, OAuth, app secret) stay in config files.

## Search

- **/search** (public) searches published articles, pages, and listings by
  title, summary, and body, with pagination and excerpts.
- **/admin/search** searches articles, pages, media, comments, and users, but
  only returns each record type if the viewer has the relevant capability.

## SEO

- Per-article meta title/description, canonical URLs, Open Graph/Twitter tags,
  and JSON-LD `Article` schema are rendered in `<head>` by `renderHeader()`.
- `/sitemap.xml` lists the home page, article index, categories, and all
  published articles, pages, and listings.
- `/robots.txt` allows crawling of public content and disallows `/admin`,
  `/auth`, `/api`.
- `/feed` is an RSS 2.0 feed of the 20 most recent published articles.

## Feature toggles

The current feature list also includes `custom_fields`, `relationships`,
`taxonomies`, `link_manager`, `embeds`, `collections`, `webhooks`, `analytics`,
`accessibility_checker`, and optional `media_documents`, `media_audio`, and
`media_video`. See [extensibility.md](extensibility.md) and
[operations-and-integrations.md](operations-and-integrations.md).

The `features` config block can disable unused subsystems when this base is
copied to a new project: `articles`, `pages`, `comments`, `media`,
`categories`, `tags`, `seo_sitemap`, `rss_feed`, `listings`, and
`contact_forms`. Disabled features hide their admin nav and return 404 on their
routes. Use the `feature()` / `requireFeature()` helpers
(`includes/http.php`) to gate new subsystems the same way.
