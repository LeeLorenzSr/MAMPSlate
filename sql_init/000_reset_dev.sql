-- Development-only reset. Do not run this against production data.
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS schema_migrations;
DROP TABLE IF EXISTS content_revisions;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS menus;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS audit_events;
DROP TABLE IF EXISTS invite_codes;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS article_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS articles;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS media;
DROP TABLE IF EXISTS role_capabilities;
DROP TABLE IF EXISTS capabilities;
DROP TABLE IF EXISTS user_oauth_identities;
DROP TABLE IF EXISTS api_keys;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS user_roles;

SET FOREIGN_KEY_CHECKS = 1;
