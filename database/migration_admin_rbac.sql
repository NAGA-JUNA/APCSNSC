-- =====================================================
-- APCSNSC ADMIN RBAC MIGRATION
-- =====================================================
-- Purpose: Add role-based access control for Super Admin,
--          State President, and District President.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE admin_users
    ADD COLUMN IF NOT EXISTS role VARCHAR(40) DEFAULT 'super_admin' AFTER password,
    ADD COLUMN IF NOT EXISTS district VARCHAR(150) DEFAULT NULL AFTER role,
    ADD COLUMN IF NOT EXISTS state VARCHAR(120) DEFAULT 'Andhra Pradesh' AFTER district,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER state,
    ADD COLUMN IF NOT EXISTS can_approve_payments TINYINT(1) DEFAULT 1 AFTER is_active,
    ADD COLUMN IF NOT EXISTS can_approve_id_cards TINYINT(1) DEFAULT 1 AFTER can_approve_payments;

CREATE INDEX IF NOT EXISTS idx_admin_role ON admin_users(role);
CREATE INDEX IF NOT EXISTS idx_admin_district ON admin_users(district);
CREATE INDEX IF NOT EXISTS idx_admin_is_active ON admin_users(is_active);

UPDATE admin_users
SET role = COALESCE(NULLIF(role, ''), 'super_admin'),
    state = COALESCE(NULLIF(state, ''), 'Andhra Pradesh'),
    is_active = COALESCE(is_active, 1),
    can_approve_payments = COALESCE(can_approve_payments, 1),
    can_approve_id_cards = COALESCE(can_approve_id_cards, 1);

-- Optional sample seed (customize district names as needed)
-- UPDATE admin_users SET role = 'state_president', district = NULL WHERE username = 'state_admin';
-- UPDATE admin_users SET role = 'district_president', district = 'Guntur' WHERE username = 'guntur_president';

SET FOREIGN_KEY_CHECKS = 1;
