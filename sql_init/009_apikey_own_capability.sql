-- Self-service API key capability, granted to roles that may create/revoke
-- their own API keys. Administrators manage all keys via apikey.manage.
INSERT INTO capabilities (name, description) VALUES
    ('apikey.own', 'Create and revoke own API keys')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
INNER JOIN capabilities c ON c.name = 'apikey.own'
WHERE r.name IN ('administrator', 'editor')
ON DUPLICATE KEY UPDATE role_id = role_id;
