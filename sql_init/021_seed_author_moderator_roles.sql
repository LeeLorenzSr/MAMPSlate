-- Add "author" and "moderator" roles with sensible default capabilities.
-- Idempotent: re-running is safe.

INSERT INTO user_roles (name, description) VALUES
    ('author',    'Content author: create and publish own articles and upload media'),
    ('moderator', 'Moderator: moderate comments and review activity')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- author: create / edit / publish / delete own articles + upload media + comment.
INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
INNER JOIN capabilities c ON c.name IN (
    'article.create', 'article.edit.own', 'article.publish',
    'article.delete.own', 'media.upload', 'comment.create'
)
WHERE r.name = 'author'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- moderator: moderate comments + edit any article + view the audit log.
INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
INNER JOIN capabilities c ON c.name IN (
    'comment.moderate', 'comment.create', 'comment.edit.own',
    'comment.delete.own', 'article.edit.any', 'audit.view'
)
WHERE r.name = 'moderator'
ON DUPLICATE KEY UPDATE role_id = role_id;
