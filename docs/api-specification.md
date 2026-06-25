# API Specification

## Format

- Request bodies use JSON unless otherwise documented.
- Responses use JSON.
- Successful responses include `ok: true`.
- Error responses include `ok: false`, `error`, and `message`.

## Authentication

The API supports two credential types:

- Revokable API keys sent as `Authorization: Bearer <api_key>`.
- Temporal session keys sent as `Authorization: Session <session_key>`.

API keys and session keys are stored as hashes. Plaintext values are never persisted.

## Endpoint Naming

- Use extensionless URLs for API consumers.
- Keep route files as `.php` on disk.
- Apache rewrite rules map `/api/me` to `/api/me.php`.

## Initial Endpoints

### `GET /api/health`

Public, unauthenticated status check. Returns `status` (`ok` or `degraded`),
`db` (`ok`/`error`), `service`, `cached_at`, and `ttl_seconds`. HTTP status is
`200` when healthy, `503` when degraded.

The response is cached server-side to `cache/health.json` for 60 seconds so a
flood of health checks cannot hammer the database. Cache hits serve the stored
JSON without any database access; at most one `SELECT 1` runs per minute. Clients
are also told to cache via `Cache-Control: public, max-age=60`.

Example response:

```json
{
  "status": "ok",
  "db": "ok",
  "service": "MAMPSlate CMS",
  "cached_at": "2026-06-19T12:00:00+00:00",
  "ttl_seconds": 60
}
```

### `GET /api/me`

Returns the authenticated user, including their public-profile fields. Like
all API responses, it follows the `ok: true` convention and is authenticated by
a stateless bearer credential (`Authorization: Bearer <api_key>` or
`Session <session_key>`); no CSRF token is required.

Example response:

```json
{
  "ok": true,
  "user": {
    "id": 1,
    "email": "admin@example.test",
    "display_name": "Administrator",
    "role": "administrator",
    "slug": "administrator",
    "bio": "Short work-focused introduction.",
    "cover_photo": "coverpics/0123456789abcdef.webp",
    "social_links": {
      "github": "https://github.com/you",
      "linkedin": null,
      "website": "https://yoursite.com"
    }
  }
}
```

`slug` is the public profile handle (resolves at `/user/{slug}`). `social_links`
keys are `github`, `linkedin`, `website` and are `null` when unset. The
`hide_email` preference is not exposed here; it only governs the public profile
view.

### `POST /api/session`

Accepts email and password, then returns a temporal API session key.

Example request:

```json
{
  "email": "admin@example.test",
  "password": "change-me"
}
```

Example response:

```json
{
  "ok": true,
  "session_key": "shown-once",
  "expires_at": "2026-06-18T02:00:00+00:00"
}
```

## Error Codes

- `bad_request`: malformed or missing input.
- `unauthorized`: missing or invalid credentials.
- `forbidden`: authenticated but not allowed.
- `not_found`: resource not found.
- `method_not_allowed`: unsupported HTTP method.
- `server_error`: unexpected server-side failure.

## Versioning

The versioned CRUD API lives at `/api/v1/...` (articles, pages, media, comments)
with full documentation in [api-v1.md](api-v1.md) and an OpenAPI spec in
[openapi-v1.yaml](openapi-v1.yaml). The unversioned routes here (`/api/me`,
`/api/session`, `/api/health`) are for internal bootstrapping and monitoring.
