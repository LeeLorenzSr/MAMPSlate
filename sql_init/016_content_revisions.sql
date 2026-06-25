-- Content revisions for articles and pages.
CREATE TABLE IF NOT EXISTS content_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type VARCHAR(32) NOT NULL,
    content_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    changed_by_user_id INT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    snapshot JSON NOT NULL,
    change_note VARCHAR(255) NULL,
    INDEX idx_cr_content (content_type, content_id, revision_number),
    CONSTRAINT fk_cr_user FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
