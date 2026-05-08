-- =====================================================
-- APCSNSC MASTER MEMBERSHIP PLAN MIGRATION
-- =====================================================
-- Convert from multiple plans to single editable master plan

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. CREATE MASTER PLAN SETTINGS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS membership_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(150) NOT NULL DEFAULT 'APCSNSC Membership Plan',
    plan_price DECIMAL(10, 2) NOT NULL DEFAULT 100.00,
    renewal_price DECIMAL(10, 2) NOT NULL DEFAULT 100.00,
    validity_months INT NOT NULL DEFAULT 12,
    description TEXT DEFAULT NULL,
    late_fee DECIMAL(10, 2) DEFAULT 0.00,
    auto_reminder_days INT DEFAULT 30,
    allow_id_card_generation TINYINT DEFAULT 1,
    status VARCHAR(20) DEFAULT 'active' COMMENT 'active or inactive',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_active_plan (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Master membership plan settings - single active plan';

-- =====================================================
-- 2. INSERT DEFAULT MASTER PLAN
-- =====================================================

INSERT IGNORE INTO membership_settings 
(plan_name, plan_price, renewal_price, validity_months, description, status)
VALUES 
('APCSNSC Membership Plan', 100.00, 100.00, 12, 'Valid membership with ID Card and union benefits', 'active');

-- =====================================================
-- 3. CREATE PAYMENT RECEIPTS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS payment_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    transaction_id VARCHAR(100) DEFAULT NULL,
    member_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_mode VARCHAR(50) DEFAULT NULL,
    plan_name VARCHAR(150) DEFAULT NULL,
    validity_months INT DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_member_id (member_id),
    INDEX idx_issued_at (issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Payment receipts for audit and member records';

-- =====================================================
-- 4. ADD RECEIPT SUPPORT TO PAYMENT_TRANSACTIONS
-- =====================================================

ALTER TABLE payment_transactions
ADD COLUMN IF NOT EXISTS receipt_number VARCHAR(50) DEFAULT NULL AFTER remarks,
ADD COLUMN IF NOT EXISTS receipt_generated_at DATETIME DEFAULT NULL AFTER receipt_number;

-- =====================================================
-- 5. UPDATE PAYMENT TRANSACTION STATUS ENUM
-- =====================================================

-- Ensure payment_status includes all valid values
-- (No change needed - already has pending, approved, failed, unpaid)

-- =====================================================
-- 6. UPDATE RENEWAL ALERTS WITH MASTER PLAN REFERENCE
-- =====================================================

ALTER TABLE renewal_alerts
ADD COLUMN IF NOT EXISTS plan_name VARCHAR(150) DEFAULT NULL AFTER alert_date,
ADD COLUMN IF NOT EXISTS renewal_amount DECIMAL(10, 2) DEFAULT NULL AFTER plan_name;

-- =====================================================
-- 7. END OF MIGRATION
-- =====================================================

SET FOREIGN_KEY_CHECKS = 1;

-- FINAL NOTES:
-- - Only ONE row should be active in membership_settings table
-- - Old membership_plans table is now deprecated (keep for data archival)
-- - All new payments use the current active master plan from membership_settings
-- - Receipts generated for every payment (payment_receipts table)
