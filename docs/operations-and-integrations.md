# Operations and integrations

## Branding and onboarding

`/admin/settings` controls the site name, logo media ID, accent color, font,
homepage ordering, footer text, and safe social links. `/admin/getting-started`
is the repeatable post-install sequence for branding, first content, SMTP,
backups, OAuth, MCP, contact forms, listings, and demo content.
`/admin/theme-preview` shows the saved logo in light/dark contexts plus the
default Open Graph image, favicon assets, accent, and typography.

## Public profiles

Profiles can be marked creator or organization, public/unlisted/private, and
can expose additional validated social links. Organization profiles may opt in
to a claim request. A signed-in claimant submits a short request on the public
profile; an administrator reviews it at `/admin/profile-claims`. Approval
records the claimant and closes future claims. Identity evidence remains an
operator responsibility and is intentionally not collected by the CMS.

## Optional media

Images remain enabled with `media`. Enable `media_documents`, `media_audio`, or
`media_video` only when needed. Supported non-image types are PDF/TXT/DOC/DOCX,
common MP3/OGG/WAV/M4A/FLAC audio, and MP4/WebM/Ogg video. Files are validated
with `finfo`; browser previews are provided for audio/video and direct links for
documents.

## Webhooks and notifications

Webhooks are disabled by default (`features.webhooks=0`). An administrator with
`webhook.manage` creates an HTTPS endpoint at `/admin/webhooks` and must
explicitly activate it; activation is the approval to deliver external requests.
The CMS sends signed JSON for `content.published`, `user.signed_up`,
`comment.pending`, and `form.submitted`. The `X-MAMPSlate-Signature` header is
an HMAC-SHA256 of the request body using the configured secret.

Delivery is synchronous (two-second connect / four-second overall timeout),
does not follow redirects, and does not silently retry. The endpoint and a
short response/error summary are recorded for operators. Contact payloads avoid
the submitter's message and email. `/admin/notifications` provides a local
read/unread activity feed for these events.

## Analytics and accessibility

The analytics hook records aggregate `outbound_click` events only: it excludes
IP address, user agent, referrer, account identity, and URL query data. The
dashboard exposes the 30-day aggregate.

`/admin/accessibility` runs deterministic content checks for missing image alt
text, empty Markdown alt text, skipped heading levels, and unlabeled managed
links. It is a review aid, not a substitute for keyboard, screen-reader, and
contrast testing.

## Sitemap registry

`SitemapRegistry` owns core article/page/listing entries and merges optional
module callbacks. This prevents a new content type from requiring manual edits
to `public_html/sitemap.php`.
