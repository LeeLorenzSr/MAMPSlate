-- Flip the email-privacy default to HIDDEN: a user's email is not shown on
-- their public profile (/user/{slug}) until they explicitly opt in on /profile.
--
-- Migration 018 added hide_email with DEFAULT 0 (visible). This backfills
-- existing accounts to 1 (hidden) and changes the column default to 1 so future
-- accounts are hidden by default too. A separate migration is used (rather than
-- editing 018) because the runner never re-applies a migration that already
-- succeeded, so editing 018 would not affect databases where it already ran.
--
-- Idempotent and MySQL 5.7-safe. Re-running is harmless: the UPDATE is a no-op
-- once every row is 1, and MODIFY to the same default does not error.
UPDATE users SET hide_email = 1 WHERE hide_email = 0;

ALTER TABLE users MODIFY COLUMN hide_email TINYINT(1) NOT NULL DEFAULT 1;
