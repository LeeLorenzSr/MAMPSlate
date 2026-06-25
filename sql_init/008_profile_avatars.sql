-- Profile avatars: stored path under uploads/profilepics/.
-- Idempotent: only adds the column if it does not already exist.
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'avatar'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER display_name',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
