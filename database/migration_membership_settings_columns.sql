-- =====================================================
-- APCSNSC MEMBERSHIP_SETTINGS COLUMN BACKFILL
-- =====================================================
-- Purpose:
--   Add missing columns to existing membership_settings table
--   for older deployments that were created before new fields.
-- Compatible with MySQL 5.7+ and 8+

SET NAMES utf8mb4;

-- Ensure table exists before altering.
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
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add late_fee if missing.
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'membership_settings'
              AND COLUMN_NAME = 'late_fee'
        ),
        'SELECT 1',
        'ALTER TABLE membership_settings ADD COLUMN late_fee DECIMAL(10,2) DEFAULT 0.00 AFTER description'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add auto_reminder_days if missing.
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'membership_settings'
              AND COLUMN_NAME = 'auto_reminder_days'
        ),
        'SELECT 1',
        'ALTER TABLE membership_settings ADD COLUMN auto_reminder_days INT DEFAULT 30 AFTER late_fee'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add allow_id_card_generation if missing.
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'membership_settings'
              AND COLUMN_NAME = 'allow_id_card_generation'
        ),
        'SELECT 1',
        'ALTER TABLE membership_settings ADD COLUMN allow_id_card_generation TINYINT DEFAULT 1 AFTER auto_reminder_days'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add/ensure timestamps if missing.
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'membership_settings'
              AND COLUMN_NAME = 'created_at'
        ),
        'SELECT 1',
        'ALTER TABLE membership_settings ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'membership_settings'
              AND COLUMN_NAME = 'updated_at'
        ),
        'SELECT 1',
        'ALTER TABLE membership_settings ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure one default row exists.
INSERT IGNORE INTO membership_settings
(plan_name, plan_price, renewal_price, validity_months, description, status)
VALUES
('APCSNSC Membership Plan', 100.00, 100.00, 12, 'Valid membership with ID Card and union benefits', 'active');

-- End migration.
SELECT 'membership_settings schema backfill complete' AS message;
