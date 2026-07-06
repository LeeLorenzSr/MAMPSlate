# Export Privacy Controls

`/admin/exports` is gated by `export.manage` and supports JSON/CSV downloads.
Exports are schema allowlists, not raw table dumps. When a repository gains a
new column, it is not exported until this document and
`public_html/admin/exports.php` are updated together.

## Dataset Field Allowlists

| Dataset | Exported fields | Notes |
|---------|-----------------|-------|
| `users` | `id`, `email`, `display_name`, `role_name`, `is_active`, `created_at` | Excludes password hashes, reset tokens, OAuth identities, profile private fields, and login timestamps. |
| `articles` | `id`, `title`, `slug`, `status`, `published_at`, `updated_at`, `author_user_id`, `author_name`, `category_name` | Admin listing metadata only; body Markdown/HTML is not included. |
| `media` | `id`, `stored_name`, `original_name`, `mime_type`, `file_size`, `width`, `height`, `alt_text`, `title`, `created_at`, `uploader_name` | Metadata only; file bytes are backed up separately. |
| `settings` | `key`, `value` | Non-secret settings table only. Config secrets stay in `config/config.local.php` and are never exported here. |
| `listings` | `id`, `title`, `slug`, `status`, `published_at`, `updated_at`, `owner_name` | Admin listing metadata only; body and links are omitted from bulk exports. |
| `contact_submissions` | `id`, `form_id`, `form_name`, `form_slug`, `name`, `email`, `subject`, `message`, `status`, `created_at`, `updated_at` | Includes submitter-provided contact content. Excludes `ip_hash` and `user_agent`. |

## Change Rule

Before adding a field, decide whether it is needed for a real operator task and
whether it contains secrets, tokens, IP-derived values, operational diagnostics,
or unnecessary personal data. Update the table above, the allowlist in
`public_html/admin/exports.php`, and run `php tools/verify.php`.
