-- Generic extension primitives for vertical CMS customizations.
-- All polymorphic references are application-owned so a module may introduce
-- a new entity type without requiring another core schema migration.

INSERT INTO capabilities (name, description) VALUES
    ('content.model.manage', 'Manage custom fields and reusable content models'),
    ('taxonomy.manage', 'Manage reusable taxonomies and terms'),
    ('collection.manage', 'Manage curated content collections'),
    ('webhook.manage', 'Manage outbound webhook endpoints'),
    ('notification.view', 'View operational notifications'),
    ('accessibility.view', 'Run the content accessibility checker')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
CROSS JOIN capabilities c
WHERE r.name = 'administrator'
  AND c.name IN ('content.model.manage', 'taxonomy.manage', 'collection.manage', 'webhook.manage', 'notification.view', 'accessibility.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

CREATE TABLE IF NOT EXISTS content_field_definitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(40) NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    label VARCHAR(120) NOT NULL,
    value_type VARCHAR(20) NOT NULL DEFAULT 'text',
    options_json TEXT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_content_field_definition (entity_type, field_key),
    INDEX idx_content_field_definitions_type (entity_type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_field_values (
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    value_json MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (entity_type, entity_id, field_key),
    INDEX idx_content_field_values_lookup (entity_type, field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entity_relationships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_type VARCHAR(40) NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    relationship_type VARCHAR(80) NOT NULL,
    label VARCHAR(120) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity_relationship_source (source_type, source_id, sort_order),
    INDEX idx_entity_relationship_target (target_type, target_id),
    CONSTRAINT fk_entity_relationship_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS taxonomies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description VARCHAR(500) NOT NULL DEFAULT '',
    is_hierarchical TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS taxonomy_terms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    taxonomy_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    parent_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_taxonomy_term_slug (taxonomy_id, slug),
    INDEX idx_taxonomy_terms_parent (taxonomy_id, parent_id),
    CONSTRAINT fk_taxonomy_terms_taxonomy FOREIGN KEY (taxonomy_id) REFERENCES taxonomies(id) ON DELETE CASCADE,
    CONSTRAINT fk_taxonomy_terms_parent FOREIGN KEY (parent_id) REFERENCES taxonomy_terms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entity_terms (
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (entity_type, entity_id, term_id),
    INDEX idx_entity_terms_term (term_id),
    CONSTRAINT fk_entity_terms_term FOREIGN KEY (term_id) REFERENCES taxonomy_terms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    label VARCHAR(120) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    service_type VARCHAR(60) NOT NULL DEFAULT 'website',
    rel_attributes VARCHAR(120) NOT NULL DEFAULT 'noopener noreferrer',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_external_links_entity (entity_type, entity_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_embeds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    provider VARCHAR(60) NOT NULL,
    source_url VARCHAR(2048) NOT NULL,
    title VARCHAR(160) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_content_embeds_entity (entity_type, entity_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_collections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description VARCHAR(500) NOT NULL DEFAULT '',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_collection_items (
    collection_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (collection_id, entity_type, entity_id),
    INDEX idx_content_collection_items_entity (entity_type, entity_id),
    CONSTRAINT fk_content_collection_items_collection FOREIGN KEY (collection_id) REFERENCES content_collections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_endpoints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    event_name VARCHAR(80) NOT NULL,
    target_url VARCHAR(2048) NOT NULL,
    signing_secret VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    last_status_code SMALLINT UNSIGNED NULL,
    last_error VARCHAR(500) NOT NULL DEFAULT '',
    last_delivered_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_webhook_endpoints_event (event_name, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint_id INT UNSIGNED NOT NULL,
    event_name VARCHAR(80) NOT NULL,
    response_code SMALLINT UNSIGNED NULL,
    response_summary VARCHAR(500) NOT NULL DEFAULT '',
    delivered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webhook_deliveries_endpoint (endpoint_id, delivered_at),
    CONSTRAINT fk_webhook_deliveries_endpoint FOREIGN KEY (endpoint_id) REFERENCES webhook_endpoints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    event_name VARCHAR(80) NOT NULL,
    title VARCHAR(160) NOT NULL,
    body VARCHAR(500) NOT NULL DEFAULT '',
    url VARCHAR(500) NOT NULL DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_read (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NOT NULL DEFAULT '',
    entity_id INT UNSIGNED NULL,
    link_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_analytics_events_name_date (event_name, created_at),
    INDEX idx_analytics_events_entity (entity_type, entity_id),
    CONSTRAINT fk_analytics_events_link FOREIGN KEY (link_id) REFERENCES external_links(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (`key`, `value`) VALUES
    ('features.custom_fields', '1'),
    ('features.relationships', '1'),
    ('features.taxonomies', '1'),
    ('features.link_manager', '1'),
    ('features.embeds', '1'),
    ('features.collections', '1'),
    ('features.webhooks', '0'),
    ('features.analytics', '1'),
    ('features.accessibility_checker', '1'),
    ('features.media_documents', '0'),
    ('features.media_audio', '0'),
    ('features.media_video', '0'),
    ('theme.accent_color', '#2f6fec'),
    ('theme.font_family', 'montserrat'),
    ('theme.footer_text', ''),
    ('theme.social_links', '[]')
ON DUPLICATE KEY UPDATE `value` = `value`;

-- Public profile improvements: creator/organization identity, visibility,
-- additional social links, and an administrator-reviewed claim queue.
SET @has_profile_type := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'profile_type'
);
SET @sql := IF(@has_profile_type = 0,
    'ALTER TABLE users
        ADD COLUMN profile_type VARCHAR(20) NOT NULL DEFAULT ''creator'' AFTER hide_email,
        ADD COLUMN profile_visibility VARCHAR(20) NOT NULL DEFAULT ''public'' AFTER profile_type,
        ADD COLUMN is_claimable TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_visibility,
        ADD COLUMN claimed_by_user_id INT UNSIGNED NULL AFTER is_claimable,
        ADD COLUMN profile_social_json TEXT NULL AFTER claimed_by_user_id,
        ADD INDEX idx_users_profile_visibility (profile_visibility),
        ADD CONSTRAINT fk_users_claimed_by FOREIGN KEY (claimed_by_user_id) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS profile_claim_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_user_id INT UNSIGNED NOT NULL,
    claimant_user_id INT UNSIGNED NOT NULL,
    message VARCHAR(500) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    reviewed_by_user_id INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_profile_claim_request (profile_user_id, claimant_user_id),
    INDEX idx_profile_claim_status (status, created_at),
    CONSTRAINT fk_profile_claim_profile FOREIGN KEY (profile_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_profile_claim_claimant FOREIGN KEY (claimant_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_profile_claim_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
