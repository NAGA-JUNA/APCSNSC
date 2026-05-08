-- =====================================================
-- APCSNSC MASTER MEMBERSHIP PLAN MIGRATION
-- =====================================================
-- Purpose: Move to one admin-controlled active membership plan

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS membership_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(150) NOT NULL,
    plan_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    renewal_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    validity_months INT NOT NULL DEFAULT 12,
    description TEXT DEFAULT NULL,
    late_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    auto_reminder_days INT NOT NULL DEFAULT 30,
    allow_id_card_generation TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_membership_settings_status (status),
    INDEX idx_membership_settings_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed one active row if table is empty.
INSERT INTO membership_settings (
    plan_name,
    plan_price,
    renewal_price,
    validity_months,
    description,
    late_fee,
    auto_reminder_days,
    allow_id_card_generation,
    status
)
SELECT
    'APCSNSC Membership Plan',
    100.00,
    100.00,
    12,
    'Valid membership with ID card and union benefits',
    0.00,
    30,
    1,
    'active'
WHERE NOT EXISTS (SELECT 1 FROM membership_settings);

-- If old multi-plan table exists, map the cheapest active plan to the master row once.
UPDATE membership_settings ms
JOIN (
    SELECT plan_name, amount, validity_months, COALESCE(plan_description, '') AS plan_description
    FROM membership_plans
    WHERE is_active = 1
    ORDER BY amount ASC, id ASC
    LIMIT 1
) p ON 1 = 1
SET
    ms.plan_name = CASE WHEN ms.plan_name = 'APCSNSC Membership Plan' THEN p.plan_name ELSE ms.plan_name END,
    ms.plan_price = CASE WHEN ms.plan_price = 100.00 THEN p.amount ELSE ms.plan_price END,
    ms.renewal_price = CASE WHEN ms.renewal_price = 100.00 THEN p.amount ELSE ms.renewal_price END,
    ms.validity_months = CASE WHEN ms.validity_months = 12 THEN p.validity_months ELSE ms.validity_months END,
    ms.description = CASE WHEN ms.description = 'Valid membership with ID card and union benefits' AND p.plan_description <> '' THEN p.plan_description ELSE ms.description END
WHERE ms.id = (SELECT id2 FROM (SELECT id AS id2 FROM membership_settings ORDER BY id ASC LIMIT 1) x);

SET FOREIGN_KEY_CHECKS = 1;
