-- =====================================================
-- APCSNSC MEMBERSHIP PAYMENT SYSTEM - DATABASE MIGRATION
-- =====================================================
-- Generated: 2026-04-19
-- Purpose: Add membership payment management to existing system

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1) ALTER MEMBERS TABLE - Add Payment Columns
-- =====================================================

-- Add payment and membership status columns
ALTER TABLE members
ADD COLUMN IF NOT EXISTS membership_status VARCHAR(20) DEFAULT 'unpaid' AFTER status,
ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'unpaid' AFTER membership_status,
ADD COLUMN IF NOT EXISTS plan_name VARCHAR(100) DEFAULT NULL AFTER payment_status,
ADD COLUMN IF NOT EXISTS plan_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER plan_name,
ADD COLUMN IF NOT EXISTS membership_start_date DATE DEFAULT NULL AFTER plan_amount,
ADD COLUMN IF NOT EXISTS membership_expiry_date DATE DEFAULT NULL AFTER membership_start_date,
ADD COLUMN IF NOT EXISTS renewal_count INT DEFAULT 0 AFTER membership_expiry_date,
ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) DEFAULT NULL AFTER renewal_count,
ADD COLUMN IF NOT EXISTS last_payment_date DATETIME DEFAULT NULL AFTER transaction_id,
ADD COLUMN IF NOT EXISTS payment_mode VARCHAR(50) DEFAULT NULL AFTER last_payment_date,
ADD COLUMN IF NOT EXISTS payment_remarks TEXT DEFAULT NULL AFTER payment_mode;

-- Add indexes for common payment queries
CREATE INDEX IF NOT EXISTS idx_payment_status ON members(payment_status);
CREATE INDEX IF NOT EXISTS idx_membership_status ON members(membership_status);
CREATE INDEX IF NOT EXISTS idx_membership_expiry ON members(membership_expiry_date);
CREATE INDEX IF NOT EXISTS idx_last_payment_date ON members(last_payment_date);

-- =====================================================
-- 2) CREATE MEMBERSHIP PLANS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS membership_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(100) NOT NULL UNIQUE,
    plan_description TEXT DEFAULT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    validity_months INT NOT NULL COMMENT 'Plan validity in months (use 1200 for lifetime)',
    is_active TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 3) CREATE PAYMENT TRANSACTIONS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(100) NOT NULL UNIQUE,
    member_id INT NOT NULL,
    plan_id INT DEFAULT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, approved, failed, unpaid',
    payment_mode VARCHAR(50) DEFAULT NULL COMMENT 'UPI, Cash, Online, Bank Transfer',
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_date DATETIME DEFAULT NULL,
    approved_by INT DEFAULT NULL COMMENT 'Admin user ID',
    remarks TEXT DEFAULT NULL,
    is_renewal TINYINT DEFAULT 0,
    previous_expiry_date DATE DEFAULT NULL COMMENT 'If renewal, what was expiry before',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES membership_plans(id) ON DELETE SET NULL,
    INDEX idx_member_id (member_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_is_renewal (is_renewal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 4) INSERT DEFAULT MEMBERSHIP PLANS
-- =====================================================

INSERT IGNORE INTO membership_plans (plan_name, plan_description, amount, validity_months)
VALUES
    ('Basic Plan', '1 Year valid membership with ID card and member benefits', 100.00, 12),
    ('Premium Plan', '3 Years valid membership with premium benefits', 300.00, 36),
    ('Lifetime Plan', 'Lifetime valid membership without renewal', 1000.00, 1200);

-- =====================================================
-- 5) CREATE RENEWAL ALERTS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS renewal_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    alert_type VARCHAR(50) DEFAULT 'renewal_due' COMMENT 'renewal_due, expiry_urgent, expired',
    alert_date DATE NOT NULL,
    is_sent TINYINT DEFAULT 0,
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_member_alert (member_id, alert_type),
    INDEX idx_is_sent (is_sent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 6) PAYMENT SETTINGS IN EXISTING SETTINGS TABLE
-- =====================================================

INSERT IGNORE INTO settings (setting_key, setting_value)
VALUES
    ('payment_gateway_enabled', '1'),
    ('payment_auto_expiry_check', '1'),
    ('renewal_alert_days_30', '30'),
    ('renewal_alert_days_7', '7'),
    ('currency', '₹'),
    ('currency_code', 'INR');

-- =====================================================
-- 7) END OF MIGRATION
-- =====================================================

SET FOREIGN_KEY_CHECKS = 1;

-- NOTE: After running this migration, run the PHP initialization script:
-- UPDATE members SET membership_status = 'unpaid', payment_status = 'unpaid' 
-- WHERE membership_status IS NULL OR membership_status = '';

