# Backup and Restore

This runbook covers backing up and restoring the CMS. Backups contain secrets
(database password, `config.local.php`, `sitemaster.hash`) — **never commit
backups to git**. The `backups/` directory is gitignored.

## What to back up

1. **Database** — all content, users, settings, audit history.
2. **Uploaded files** — `public_html/uploads/` (articles covers images, media,
   profile avatars).
3. **Config** — `config/config.local.php` and `config/sitemaster.hash` (secrets).
   The code itself is in git and does not need a file backup.

Optional CLI helpers live in `tools/` (outside `public_html`). They are not web
routes.

## Database backup

### Option A: `mysqldump` (Mac/Linux MAMP, or any MySQL client)

```bash
mysqldump \
  --host=127.0.0.1 --port=3306 \
  --user=mpsqladmin --password \
  --single-transaction --no-tablespaces \
  --default-character-set=utf8mb4 \
  cmstest | gzip > backups/db_$(date +%Y%m%d_%H%M%S).sql.gz
```

`--single-transaction` gives a consistent dump without locking InnoDB tables.
`--no-tablespaces` avoids needing the PROCESS privilege.

### Option B: `tools/backup_db.php`

Reads credentials from `config/config.local.php` and writes a gzipped dump to
`backups/`:

```bash
php tools/backup_db.php
```

Requires `mysqldump` on the PATH (MAMP: `C:\MAMP\bin\mysql\bin` on Windows,
`/Applications/MAMP/Library/bin` on macOS).

## Files backup

Tar the uploads and config:

```bash
tar -czf backups/files_$(date +%Y%m%d_%H%M%S).tar.gz \
  public_html/uploads config/config.local.php config/sitemaster.hash
```

Or use `tools/backup_files.php`:

```bash
php tools/backup_files.php
```

## Restore order

1. **Restore the database** to a clean, empty database (or the existing one).
   ```bash
   gunzip -c backups/db_YYYYmmdd_HHMMSS.sql.gz | mysql \
     --host=127.0.0.1 --user=mpsqladmin --password cmstest
   ```
2. **Restore config** by copying `config/config.local.php` and
   `config/sitemaster.hash` back into `config/`.
3. **Restore uploads** by extracting the files tar:
   ```bash
   tar -xzf backups/files_YYYYmmdd_HHMMSS.tar.gz
   ```
4. **Delete the `config/installed` marker** if it exists, so bootstrap re-verifies
   the schema on next request (it will recreate it automatically).
5. Visit the site and confirm it loads. If the database was restored to a
   different name/credentials than `config.local.php`, update config first.

## Local dev restore

For a full dev wipe-and-reload, `sql_init/000_reset_dev.sql` drops all tables
(see its header warning — never run in production). Then re-apply migrations via
`/admin/migrations` → "Run pending migrations", or the setup wizard.

## Production cautions

- Take backups **before** running new migrations. Migrations are idempotent but a
  failed migration mid-way can leave partial schema.
- Store backups off-server and encrypt at rest; they contain credentials and
  personal data (emails, IPs in the audit log).
- Test restores periodically on a staging copy.
- The least-privilege runtime DB user (`SELECT/INSERT/UPDATE/DELETE`) cannot run
  `mysqldump --single-transaction` reliably on all setups; use a backup user with
  `SELECT, LOCK TABLES` (and `PROCESS` if you drop `--no-tablespaces`).

## What not to commit to git

- `backups/*` (gitignored)
- `config/config.local.php`, `config/sitemaster.hash`, `config/installed`
- `public_html/uploads/*` (content)
- `logs/*`, `cache/*`
