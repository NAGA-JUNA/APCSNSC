-- =====================================================
-- APCSNSC ROLE & PERMISSION MANAGEMENT MODULE
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE admin_users
    ADD COLUMN IF NOT EXISTS role VARCHAR(40) DEFAULT 'super_admin' AFTER password,
    ADD COLUMN IF NOT EXISTS district VARCHAR(150) DEFAULT NULL AFTER role,
    ADD COLUMN IF NOT EXISTS state VARCHAR(120) DEFAULT 'Andhra Pradesh' AFTER district,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER state,
    ADD COLUMN IF NOT EXISTS can_approve_payments TINYINT(1) DEFAULT 1 AFTER is_active,
    ADD COLUMN IF NOT EXISTS can_approve_id_cards TINYINT(1) DEFAULT 1 AFTER can_approve_payments,
    ADD COLUMN IF NOT EXISTS mobile VARCHAR(30) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS last_login DATETIME DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_admin_users_role ON admin_users(role);
CREATE INDEX IF NOT EXISTS idx_admin_users_active ON admin_users(is_active);
CREATE INDEX IF NOT EXISTS idx_admin_users_state ON admin_users(state);
CREATE INDEX IF NOT EXISTS idx_admin_users_district ON admin_users(district);

CREATE TABLE IF NOT EXISTS admin_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(50) NOT NULL UNIQUE,
    role_name VARCHAR(120) NOT NULL,
    scope_level ENUM('global', 'state', 'district', 'module') NOT NULL DEFAULT 'global',
    is_system TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admin_roles (role_key, role_name, scope_level, is_system)
VALUES
('super_admin', 'Super Admin', 'global', 1),
('state_president', 'State President', 'state', 1),
('district_president', 'District President', 'district', 1),
('media_admin', 'Media Admin', 'module', 1),
('complaint_admin', 'Complaint Admin', 'module', 1)
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), scope_level = VALUES(scope_level);

CREATE TABLE IF NOT EXISTS admin_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    permission_key VARCHAR(80) NOT NULL,
    is_allowed TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_permission (admin_id, permission_key),
    INDEX idx_admin_permission_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_admin_id INT NOT NULL,
    target_admin_id INT DEFAULT NULL,
    action VARCHAR(120) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_logs_target (target_admin_id),
    INDEX idx_admin_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
