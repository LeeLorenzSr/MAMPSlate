-- Invite codes for invite-only signup mode. Codes are stored as hashes; the
-- plaintext is shown only once at creation time (mirrors API keys).
CREATE TABLE IF NOT EXISTS invite_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code_hash CHAR(64) NOT NULL UNIQUE,
    code_prefix VARCHAR(16) NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    max_uses INT UNSIGNED NOT NULL DEFAULT 1,
    uses INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invite_code_hash (code_hash),
    CONSTRAINT fk_invite_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
