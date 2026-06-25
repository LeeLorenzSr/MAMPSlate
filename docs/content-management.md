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

- **/search** (public) searches published articles and pages by title, summary,
  and body, with pagination and excerpts.
- **/admin/search** searches articles, pages, media, comments, and users, but
  only returns each record type if the viewer has the relevant capability.

## SEO

- Per-article meta title/description, canonical URLs, Open Graph/Twitter tags,
  and JSON-LD `Article` schema are rendered in `<head>` by `renderHeader()`.
- `/sitemap.xml` lists the home page, article index, categories, and all
  published articles.
- `/robots.txt` allows crawling of public content and disallows `/admin`,
  `/auth`, `/api`.
- `/feed` is an RSS 2.0 feed of the 20 most recent published articles.

## Feature toggles

The `features` config block can disable unused subsystems when this base is
copied to a new project: `articles`, `comments`, `media`, `categories`, `tags`,
`seo_sitemap`, `rss_feed`. Disabled features hide their admin nav and return 404
on their routes. Use the `feature()` / `requireFeature()` helpers
(`includes/http.php`) to gate new subsystems the same way.
