INSERT INTO user_roles (name, description) VALUES
    ('administrator', 'Full system administration access'),
    ('editor', 'CMS content management access'),
    ('viewer', 'Read-only authenticated access'),
    ('user', 'Standard user profile access')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO users (email, display_name, role_id, password_hash, is_active)
SELECT
    'admin@example.test',
    'Site Administrator',
    user_roles.id,
    '$2y$10$al3JYVYxxQrdZAgGMwXFteMDmJI5ePsk9GCrOGu4.uGkDw2aYvuWK',
    1
FROM user_roles
WHERE user_roles.name = 'administrator'
ON DUPLICATE KEY UPDATE email = email;

-- Seed administrator credentials:
-- Email: admin@example.test
-- Password: change-me
-- Change this password immediately after first login.
