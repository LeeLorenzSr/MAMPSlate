-- Static (evergreen) pages.
CREATE TABLE IF NOT EXISTS pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    summary VARCHAR(500) NULL,
    body_markdown MEDIUMTEXT NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    author_user_id INT UNSIGNED NOT NULL,
    cover_media_id INT UNSIGNED NULL,
    meta_title VARCHAR(200) NOT NULL DEFAULT '',
    meta_description VARCHAR(320) NOT NULL DEFAULT '',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pages_status_published (status, published_at),
    INDEX idx_pages_slug (slug),
    INDEX idx_pages_author (author_user_id),
    CONSTRAINT fk_page_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_page_cover FOREIGN KEY (cover_media_id) REFERENCES media(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Page capabilities.
INSERT INTO capabilities (name, description) VALUES
    ('page.create',     'Create new static pages'),
    ('page.edit.own',   'Edit own pages'),
    ('page.edit.any',   'Edit any page'),
    ('page.publish',    'Publish or unpublish pages'),
    ('page.delete.own', 'Delete own pages'),
    ('page.delete.any', 'Delete any page')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- administrator: all page capabilities.
INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
CROSS JOIN capabilities c
WHERE r.name = 'administrator' AND c.name LIKE 'page.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- editor: create, edit own/any, publish, delete own (consistent with articles).
INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
INNER JOIN capabilities c ON c.name IN (
    'page.create', 'page.edit.own', 'page.edit.any',
    'page.publish', 'page.delete.own'
)
WHERE r.name = 'editor'
ON DUPLICATE KEY UPDATE role_id = role_id;
