-- Extend user profiles with public identity and web-presence fields.
--
-- Adds: slug (unique public handle), bio, cover_photo, and separate social
-- link columns (GitHub, LinkedIn, Website). Idempotent: the column block and
-- the unique index are only added once.
--
-- Schema only. The slug backfill for existing users is done in PHP
-- (UserRepository::backfillSlugs, triggered once from bootstrap.php) because
-- MySQL 5.x has neither REGEXP_REPLACE nor window functions, and a clean
-- transliterated slug cannot be produced in pure SQL. The unique index allows
-- multiple NULLs, so it is safe to add before the backfill runs.

-- 1. Add the new columns as a group (only if `slug` is not present yet).
SET @has_slug := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'slug'
);
SET @sql := IF(@has_slug = 0,
    'ALTER TABLE users
        ADD COLUMN slug            VARCHAR(120) NULL AFTER display_name,
        ADD COLUMN bio             VARCHAR(250)  NULL AFTER slug,
        ADD COLUMN cover_photo     VARCHAR(255)  NULL AFTER bio,
        ADD COLUMN social_github   VARCHAR(255)  NULL AFTER cover_photo,
        ADD COLUMN social_linkedin VARCHAR(255)  NULL AFTER social_github,
        ADD COLUMN social_website  VARCHAR(255)  NULL AFTER social_linkedin',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Unique index on slug (multiple NULLs allowed, so pre-existing rows are fine).
SET @has_idx := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND index_name = 'uniq_users_slug'
);
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE users ADD UNIQUE INDEX uniq_users_slug (slug)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
