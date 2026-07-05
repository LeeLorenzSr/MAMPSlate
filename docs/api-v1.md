# API v1

Stable, versioned JSON API under `/api/v1/...` for programmatic consumers. It
coexists with the unversioned bootstrap API (`/api/me`, `/api/session`).

## Authentication

All v1 endpoints require a bearer credential in the `Authorization` header:

- **API key**: `Authorization: Bearer <api_key>` — created on `/profile`
  (capability `apikey.own`) or `/admin/api-keys`. Only the hash is stored.
- **Temporal session key**: `Authorization: Session <session_key>` — issued by
  `POST /api/session` with email + password.

Bearer/session requests are **stateless and do not require CSRF**. Browser
form/JSON routes (login, signup, comments, admin) still require CSRF tokens.

Missing or invalid credentials return `401 unauthorized`.

## Authorization

Authorization is by **capability**, not role name. The authenticated user's role
capabilities determine what they can do. Each endpoint below lists the required
capability. A `403 forbidden` response means the user lacks the capability.

## Conventions

- Request bodies are JSON (`Content-Type: application/json`) except media upload.
- Successful responses include `ok: true`. Single-resource responses use `data`;
  list responses use `data` plus a `pagination` object.
- Error responses: `{ "ok": false, "error": "<code>", "message": "<human text>" }`.
- Password hashes, token hashes, API key hashes, reset token hashes, and secret
  config are never exposed.
- **Profile data**: an article's `author` object includes a `slug` so consumers
  can link to the author's public profile (`/user/{slug}`). The full profile
  (bio, cover photo, social links) is available via the bootstrap
  `GET /api/me` endpoint for the authenticated user; v1 does not expose other
  users' private fields. Profile fields are edited self-service on `/profile`,
  not via v1 write endpoints.

### Error codes

| Code                | HTTP | Meaning                                    |
|---------------------|------|--------------------------------------------|
| `unauthorized`      | 401  | Missing/invalid bearer or session credential. |
| `forbidden`         | 403  | Authenticated but lacks the capability.    |
| `not_found`         | 404  | Resource does not exist.                   |
| `method_not_allowed`| 405  | HTTP method not supported on this resource. |
| `validation`        | 422  | Request body failed validation.            |
| `rate_limited`      | 429  | Too many attempts (login/session endpoints). |
| `bad_request`       | 400  | Malformed JSON body.                       |

### Pagination

List endpoints accept `?page=` (1-based) and `?per_page=` (1–100, default 20).

```json
{
  "ok": true,
  "data": [ /* items */ ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 47,
    "total_pages": 3
  }
}
```

## Endpoints

### Articles

| Method   | Path                  | Capability                          | Notes |
|----------|-----------------------|-------------------------------------|-------|
| GET      | `/api/v1/articles`    | (any authenticated)                 | Published only. Paginated. |
| GET      | `/api/v1/articles/{id}` | (any) for published; `article.create` otherwise | |
| POST     | `/api/v1/articles`    | `article.create`                    | Creates a draft. |
| PATCH    | `/api/v1/articles/{id}` | `article.edit.any` or `article.edit.own` | `article.publish` required to set status=published. |
| DELETE   | `/api/v1/articles/{id}` | `article.delete.any` or `article.delete.own` | Hard-deletes (matches admin behavior). |

Article object: `id, title, slug, summary, status, body_markdown, body_html,
author{id,name,slug}, category, cover_media_id, meta_title, meta_description,
published_at, updated_at`. `GET one` also includes `tags` (array of names).
`author.slug` is the author's public-profile handle and resolves at
`/user/{slug}`; it is `null` if the account predates the slug column.

Write body fields (all optional on PATCH except where noted): `title` (required
on POST), `body_markdown`, `slug`, `summary`, `status`, `category_id`,
`cover_media_id`, `meta_title`, `meta_description`, `tags` (array).

Example:

```bash
curl -X POST http://localhost/api/v1/articles \
  -H "Authorization: Bearer mpk_xxx" \
  -H "Content-Type: application/json" \
  -d '{"title":"Hello","body_markdown":"# Hi","status":"draft"}'
```

### Pages

| Method   | Path                | Capability | Notes |
|----------|---------------------|------------|-------|
| GET      | `/api/v1/pages`     | (any)      | Published only. Paginated. |
| GET      | `/api/v1/pages/{id}`| (any) published; `page.create` otherwise | |
| POST     | `/api/v1/pages`     | `page.create` | |
| PATCH    | `/api/v1/pages/{id}`| `page.edit.any` or `page.edit.own` | `page.publish` to publish. |
| DELETE   | `/api/v1/pages/{id}`| `page.delete.any` or `page.delete.own` | |

Page object: `id, title, slug, summary, status, body_markdown, body_html,
author{id,name}, cover_media_id, meta_title, meta_description, published_at,
updated_at`. Write fields: `title` (POST), `body_markdown`, `slug`, `summary`,
`status`, `cover_media_id`, `meta_title`, `meta_description`.

### Media

| Method | Path                 | Capability     | Notes |
|--------|----------------------|----------------|-------|
| GET    | `/api/v1/media`      | `media.upload` | List all media. |
| GET    | `/api/v1/media/{id}` | `media.upload` | One item. |
| POST   | `/api/v1/media`      | `media.upload` | Multipart upload, field name `file`. |
| DELETE | `/api/v1/media/{id}` | `media.upload` | Deletes row + file. |

Media object: `id, url, original_name, mime_type, width, height, alt_text,
title, created_at`. `url` is the relative `/uploads/...` path.

Upload example:

```bash
curl -X POST http://localhost/api/v1/media \
  -H "Authorization: Bearer mpk_xxx" \
  -F "file=@/path/to/image.jpg"
```

### Listings

| Method | Path                    | Capability | Notes |
|--------|-------------------------|------------|-------|
| GET    | `/api/v1/listings`      | (any authenticated) | Published only. Optional `?tag=`. |
| GET    | `/api/v1/listings/{id}` | (any) for published; `listing.manage` otherwise | |
| POST   | `/api/v1/listings`      | `listing.manage` | Creates a listing. |
| PATCH  | `/api/v1/listings/{id}` | `listing.manage` | |
| DELETE | `/api/v1/listings/{id}` | `listing.manage` | |

Listing object: `id, title, slug, summary, status, body_markdown, body_html,
image_media_id, owner_user_id, owner_name, links, tags, meta_title,
meta_description, published_at, updated_at`.

Write fields: `title`, `body_markdown`, `slug`, `summary`, `status`,
`image_media_id`, `owner_user_id`, `links` (array of `{label,url}`), `tags`
(array of strings), `meta_title`, `meta_description`.

### Comments

| Method | Path                       | Capability        | Notes |
|--------|----------------------------|-------------------|-------|
| GET    | `/api/v1/comments`         | `comment.moderate`| Optional `?status=pending|approved|rejected|spam`. |
| PATCH  | `/api/v1/comments/{id}`    | `comment.moderate`| Body `{"status":"approved"}`. |

Comment object (as returned): `id, body, status, created_at, article_title,
article_slug, author_name`.

## Versioning

These routes are stable under `/api/v1`. Breaking changes require a new
`/api/v2` namespace. The unversioned `/api/me` and `/api/session` remain for
internal bootstrapping.
