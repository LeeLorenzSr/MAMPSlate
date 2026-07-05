# Starter Customization Guide

This guide shows the smallest repeatable path for adding a project-specific
subsystem while keeping MAMPSlate generic.

Example subsystem: `events`.

## Success Criteria

1. A migration creates the storage and capabilities.
2. A repository owns all SQL.
3. Public routes expose published records only.
4. Admin routes are capability-gated.
5. API/docs/MCP notes describe the new surface.
6. `php tools/verify.php` passes.

## Build Your First Subsystem

### 1. Migration

Create `sql_init/0NN_events.sql`.

```sql
CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    summary VARCHAR(500) NOT NULL DEFAULT '',
    starts_at TIMESTAMP NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_events_status_starts (status, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO capabilities (name, description)
VALUES ('event.manage', 'Create, edit, publish, and delete events')
ON DUPLICATE KEY UPDATE description = VALUES(description);
```

Grant the capability to `administrator` in the same migration.

### 2. Repository

Add `includes/EventRepository.php`. Keep SQL here, not in route files. Include:

- `findById()`
- `findBySlug()`
- `slugExists()`
- `listPublished()`
- `listForAdmin()`
- `create()`
- `update()`
- `delete()`

Use `Slug::ensureUnique()` in callers before writes.

### 3. Bootstrap

Require and instantiate the repository in `includes/bootstrap.php`.

```php
require_once APP_ROOT . '/includes/EventRepository.php';
$events = new EventRepository($pdo);
```

### 4. Public Routes

Add:

- `public_html/events.php`
- `public_html/event.php`
- rewrite rules in `public_html/.htaccess`

Public routes must only show `status = published` and dates that are not in the
future.

### 5. Admin Routes

Add:

- `public_html/admin/events.php`
- `public_html/admin/event-edit.php`

Gate both with `event.manage`, use CSRF on every POST, and audit mutations.

### 6. API and MCP Notes

If external clients need the type, add it to `/api/v1` and document the object
shape in `docs/api-v1.md`. If MCP tools are useful, add a checklist entry first;
only add tools when a deterministic route/repository operation already exists.

### 7. Verification

Run:

```bash
php tools/verify.php
```

For larger subsystems, add focused tests before implementation and keep the
verification script no-dependency.
