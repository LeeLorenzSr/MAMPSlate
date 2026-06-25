-- Audit log for security-relevant events. No secrets are ever stored here.
CREATE TABLE IF NOT EXISTS audit_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    event_type VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NULL,
    entity_id VARCHAR(64) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_event_type (event_type),
    INDEX idx_audit_actor (actor_user_id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_created (created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Capability to view the audit log.
INSERT INTO capabilities (name, description) VALUES
    ('audit.view', 'View the audit log')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
INNER JOIN capabilities c ON c.name = 'audit.view'
WHERE r.name = 'administrator'
ON DUPLICATE KEY UPDATE role_id = role_id;
