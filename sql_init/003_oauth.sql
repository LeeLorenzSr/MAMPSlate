-- OAuth identities for federated login (Google, GitHub, ...).
-- One user may have multiple linked identities; each (provider, provider_user_id) is unique.
CREATE TABLE IF NOT EXISTS user_oauth_identities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    provider VARCHAR(32) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    display_name VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_oauth_provider_uid (provider, provider_user_id),
    INDEX idx_oauth_user (user_id),
    CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth-only accounts have no password. Existing password rows are unaffected.
ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL;
