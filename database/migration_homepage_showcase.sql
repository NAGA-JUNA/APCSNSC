-- =====================================================
-- APCSNSC HOMEPAGE SHOWCASE MIGRATION
-- =====================================================
-- Creates isolated tables for Member Dashboard Highlights section.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS homepage_showcase_benefits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    icon VARCHAR(120) NOT NULL DEFAULT 'fa-solid fa-star',
    title VARCHAR(180) NOT NULL,
    link VARCHAR(255) DEFAULT '#',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_showcase_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_url VARCHAR(255) NOT NULL,
    video_id VARCHAR(32) DEFAULT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT DEFAULT NULL,
    duration_text VARCHAR(20) DEFAULT NULL,
    published_on DATE DEFAULT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_showcase_gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    title VARCHAR(180) DEFAULT NULL,
    category VARCHAR(80) DEFAULT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_showcase_news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notice_text VARCHAR(255) NOT NULL,
    full_text TEXT DEFAULT NULL,
    notice_priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    notice_link VARCHAR(255) DEFAULT '#',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE homepage_showcase_videos
ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL AFTER title,
ADD COLUMN IF NOT EXISTS duration_text VARCHAR(20) DEFAULT NULL AFTER description,
ADD COLUMN IF NOT EXISTS published_on DATE DEFAULT NULL AFTER duration_text;

ALTER TABLE homepage_showcase_gallery
ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER category;

ALTER TABLE homepage_showcase_news
ADD COLUMN IF NOT EXISTS full_text TEXT DEFAULT NULL AFTER notice_text,
ADD COLUMN IF NOT EXISTS notice_priority VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER full_text;

CREATE TABLE IF NOT EXISTS homepage_showcase_settings (
    setting_key VARCHAR(120) PRIMARY KEY,
    setting_value VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO homepage_showcase_settings (setting_key, setting_value) VALUES
('counter_total_members_mode', 'auto'),
('counter_total_members_label', 'Total Members'),
('counter_total_members_value', '0'),
('counter_complaints_solved_mode', 'auto'),
('counter_complaints_solved_label', 'Complaints Solved'),
('counter_complaints_solved_value', '0'),
('counter_districts_active_mode', 'auto'),
('counter_districts_active_label', 'Districts Active'),
('counter_districts_active_value', '0'),
('counter_id_cards_issued_mode', 'auto'),
('counter_id_cards_issued_label', 'ID Cards Issued'),
('counter_id_cards_issued_value', '0'),
('news_ticker_speed_seconds', '28');

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'homepage_showcase migration complete' AS message;
