-- Capability-based authorization: many-to-many roles <-> capabilities.
CREATE TABLE IF NOT EXISTS capabilities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_capabilities (
    role_id INT UNSIGNED NOT NULL,
    capability_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, capability_id),
    CONSTRAINT fk_rc_role FOREIGN KEY (role_id) REFERENCES user_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rc_cap FOREIGN KEY (capability_id) REFERENCES capabilities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Capability catalog.
INSERT INTO capabilities (name, description) VALUES
    ('user.manage',          'Create, edit, and deactivate user accounts'),
    ('role.manage',          'Map capabilities to roles'),
    ('apikey.manage',        'Manage all API keys'),
    ('article.create',       'Create new articles'),
    ('article.edit.own',     'Edit own articles'),
    ('article.edit.any',     'Edit any article'),
    ('article.publish',      'Publish or unpublish articles'),
    ('article.delete.own',   'Delete own articles'),
    ('article.delete.any',   'Delete any article'),
    ('media.upload',         'Upload and manage media'),
    ('comment.create',       'Post comments'),
    ('comment.edit.own',     'Edit own comments'),
    ('comment.delete.own',   'Delete own comments'),
    ('comment.moderate',     'Approve, reject, or delete any comment')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Default role -> capability grants.
-- administrator: everything.
INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
CROSS JOIN capabilities c
WHERE r.name = 'administrator'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- editor: content + media + comment moderation + commenting.
INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
INNER JOIN capabilities c ON c.name IN (
    'article.create', 'article.edit.own', 'article.edit.any',
    'article.publish', 'article.delete.own',
    'media.upload', 'comment.moderate', 'comment.create'
)
WHERE r.name = 'editor'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- viewer + user: commenting only.
INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
INNER JOIN capabilities c ON c.name IN (
    'comment.create', 'comment.edit.own', 'comment.delete.own'
)
WHERE r.name IN ('viewer', 'user')
ON DUPLICATE KEY UPDATE role_id = role_id;
