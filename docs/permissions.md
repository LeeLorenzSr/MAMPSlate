# Permissions (Role → Capability)

Authorization is capability-based. Each role is granted a set of capabilities via
the `role_capabilities` join table; routes call `Auth::requireCapability('...')`
(or `Auth::can('...')` for conditional UI). Roles are still stored in
`user_roles`, but role *names* are no longer used for access checks — only
capabilities are.

## Capability catalog

| Capability             | Description                                  |
|------------------------|----------------------------------------------|
| `user.manage`          | Create, edit, and deactivate user accounts   |
| `role.manage`          | Map capabilities to roles                    |
| `apikey.manage`        | Manage all API keys (admin)                  |
| `apikey.own`           | Create and revoke own API keys               |
| `article.create`       | Create new articles                          |
| `article.edit.own`     | Edit own articles                            |
| `article.edit.any`     | Edit any article                             |
| `article.publish`      | Publish or unpublish articles                |
| `article.delete.own`   | Delete own articles                          |
| `article.delete.any`   | Delete any article                           |
| `page.create`          | Create new static pages                      |
| `page.edit.own`        | Edit own pages                               |
| `page.edit.any`        | Edit any page                                |
| `page.publish`         | Publish or unpublish pages                   |
| `page.delete.own`      | Delete own pages                             |
| `page.delete.any`      | Delete any page                              |
| `media.upload`         | Upload and manage media                      |
| `comment.create`       | Post comments                                |
| `comment.edit.own`     | Edit own comments                            |
| `comment.delete.own`   | Delete own comments                          |
| `comment.moderate`     | Approve, reject, or delete any comment       |
| `audit.view`           | View the audit log                           |
| `menu.manage`          | Manage navigation menus                      |
| `settings.manage`      | Manage non-secret site settings              |
| `listing.manage`       | Manage generic directory listings            |
| `contact.manage`       | Manage contact forms and submissions         |
| `system.view`          | View system status diagnostics               |
| `backup.manage`        | Trigger and download guarded backups         |
| `export.manage`        | Export site data                             |
| `demo.manage`          | Seed optional demo content                   |

## Default grants (seeded by `sql_init/004_capabilities.sql`)

- **administrator**: all capabilities (including `audit.view`).
- **editor**: `article.create`, `article.edit.own`, `article.edit.any`,
  `article.publish`, `article.delete.own`, `page.create`, `page.edit.own`,
  `page.edit.any`, `page.publish`, `page.delete.own`, `media.upload`,
  `comment.moderate`, `comment.create`, `apikey.own`.
- **viewer**, **user**: `comment.create`, `comment.edit.own`,
  `comment.delete.own`.
- `sql_init/020_starter_subsystems.sql` also grants `listing.manage`,
  `contact.manage`, `system.view`, `backup.manage`, `export.manage`, and
  `demo.manage` to administrators.

> API key access is split: `apikey.own` lets a user create/revoke their **own**
> keys on `/profile`; `apikey.manage` lets an administrator manage **all** keys
> on `/admin/api-keys`. Grant `apikey.own` to whichever roles should have
> personal API keys via `/admin/roles`.

## Managing grants

Administrators with the `role.manage` capability edit the role × capability
matrix at **/admin/roles**. Changes take effect on the next request (capabilities
are loaded fresh per request in `Auth::user()`).

## Adding capabilities

1. Add a row to `capabilities` (a migration `INSERT`).
2. Reference it with `Auth::requireCapability('new.cap')` in the new route.
3. Grant it to the appropriate roles via `/admin/roles` (or seed it in the
   migration).
