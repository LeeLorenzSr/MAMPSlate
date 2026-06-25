-- Navigation menus and items.
CREATE TABLE IF NOT EXISTS menus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    location VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_menus_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id INT UNSIGNED NOT NULL,
    label VARCHAR(120) NOT NULL,
    url VARCHAR(255) NOT NULL DEFAULT '',
    linked_type VARCHAR(32) NULL,
    linked_id INT UNSIGNED NULL,
    parent_id INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_menu_items_menu (menu_id, sort_order),
    INDEX idx_menu_items_parent (parent_id),
    CONSTRAINT fk_mi_menu FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    CONSTRAINT fk_mi_parent FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menu management capability.
INSERT INTO capabilities (name, description) VALUES
    ('menu.manage', 'Manage navigation menus')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM user_roles r
INNER JOIN capabilities c ON c.name = 'menu.manage'
WHERE r.name = 'administrator'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Seed the two standard menu locations.
INSERT INTO menus (name, location) VALUES
    ('Header', 'header'),
    ('Footer', 'footer')
ON DUPLICATE KEY UPDATE name = VALUES(name);
