-- User email privacy: lets a user hide their email address from their public
-- profile (/user/{slug}). Added with DEFAULT 0 (visible); migration 019 later
-- flips the default to 1 (hidden) and backfills existing accounts.
-- Idempotent and MySQL 5.7-safe (information_schema guard + plain ALTER).
SET @has_col := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'hide_email'
);
SET @sql := IF(@has_col = 0,
    'ALTER TABLE users ADD COLUMN hide_email TINYINT(1) NOT NULL DEFAULT 0 AFTER bio',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
