-- Editable site settings (non-secret). Secrets stay in config files.
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(64) NOT NULL PRIMARY KEY,
    `value` TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings management capability.
INSERT INTO capabilities (name, description) VALUES
    ('settings.manage', 'Manage non-secret site settings')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
INNER JOIN capabilities c ON c.name = 'settings.manage'
WHERE r.name = 'administrator'
ON DUPLICATE KEY UPDATE role_id = role_id;
