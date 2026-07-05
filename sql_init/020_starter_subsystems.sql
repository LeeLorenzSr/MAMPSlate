-- Starter subsystem primitives: listings, contact submissions, operations capabilities.
-- Fills the previously missing 020 slot. Idempotent; safe to run after 021 on
-- existing installs because it does not depend on 021 data.

INSERT INTO capabilities (name, description) VALUES
    ('system.view',      'View system status diagnostics'),
    ('backup.manage',   'Trigger and download guarded backups'),
    ('export.manage',   'Export site data as JSON or CSV'),
    ('listing.manage',  'Create, edit, publish, and delete directory listings'),
    ('contact.manage',  'Manage public contact forms and submissions'),
    ('demo.manage',     'Seed optional demo content')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
CROSS JOIN capabilities c
WHERE r.name = 'administrator'
  AND c.name IN ('system.view', 'backup.manage', 'export.manage', 'listing.manage', 'contact.manage', 'demo.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

CREATE TABLE IF NOT EXISTS listings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    summary VARCHAR(500) NOT NULL DEFAULT '',
    body_markdown MEDIUMTEXT NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    image_media_id INT UNSIGNED NULL,
    links_json TEXT NULL,
    tags_json TEXT NULL,
    owner_user_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    meta_title VARCHAR(200) NOT NULL DEFAULT '',
    meta_description VARCHAR(320) NOT NULL DEFAULT '',
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_listings_status_published (status, published_at),
    INDEX idx_listings_owner (owner_user_id),
    INDEX idx_listings_image (image_media_id),
    CONSTRAINT fk_listings_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_listings_image FOREIGN KEY (image_media_id) REFERENCES media(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_forms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description VARCHAR(500) NOT NULL DEFAULT '',
    recipient_email VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notify_on_submit TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL DEFAULT '',
    subject VARCHAR(200) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    ip_hash CHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contact_submissions_form_status (form_id, status, created_at),
    CONSTRAINT fk_contact_submissions_form FOREIGN KEY (form_id) REFERENCES contact_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO contact_forms (name, slug, description, recipient_email, is_active, notify_on_submit)
VALUES ('Contact', 'contact', 'Default public contact form.', '', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO settings (`key`, `value`) VALUES
    ('features.listings', '1'),
    ('features.contact_forms', '1'),
    ('contact_require_moderation', '1')
ON DUPLICATE KEY UPDATE `value` = `value`;
