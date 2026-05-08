-- APCSNSC Latest Full Schema
-- Generated: 2026-04-18
-- Purpose: Single import file covering all recent admin/public changes.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- 1) ADMIN TABLES
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) DEFAULT NULL,
    email VARCHAR(180) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- 2) MEMBERS / COMPLAINTS CORE
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(60) NOT NULL UNIQUE,

    -- Legacy + new naming compatibility
    name VARCHAR(180) DEFAULT NULL,
    full_name VARCHAR(150) DEFAULT NULL,

    father_name VARCHAR(180) DEFAULT NULL,
    dob DATE DEFAULT NULL,
    gender VARCHAR(20) DEFAULT NULL,
    blood_group VARCHAR(10) DEFAULT NULL,
    aadhaar VARCHAR(20) DEFAULT NULL,

    phone VARCHAR(20) DEFAULT NULL,
    mobile_alt VARCHAR(20) DEFAULT NULL,
    email VARCHAR(180) DEFAULT NULL,
    address TEXT DEFAULT NULL,

    qualification VARCHAR(180) DEFAULT NULL,
    designation VARCHAR(180) DEFAULT NULL,
    registration_number VARCHAR(120) DEFAULT NULL,
    experience VARCHAR(50) DEFAULT NULL,
    employee_id VARCHAR(120) DEFAULT NULL,

    district VARCHAR(150) DEFAULT NULL,
    mandal VARCHAR(150) DEFAULT NULL,
    village VARCHAR(150) DEFAULT NULL,

    -- Legacy + new workplace compatibility
    hospital VARCHAR(200) DEFAULT NULL,
    working_place VARCHAR(150) DEFAULT NULL,
    department VARCHAR(150) DEFAULT NULL,
    shift_type VARCHAR(50) DEFAULT NULL,

    joining_date DATE DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,

    status VARCHAR(20) DEFAULT 'pending',
    role VARCHAR(120) DEFAULT NULL,

    photo VARCHAR(255) DEFAULT NULL,
    signature VARCHAR(255) DEFAULT NULL,
    documents VARCHAR(255) DEFAULT NULL,
    id_card_path VARCHAR(255) DEFAULT NULL,
    id_card_generated_at DATETIME DEFAULT NULL,
    id_card_path VARCHAR(255) DEFAULT NULL,
    id_card_generated_at DATETIME DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_members_district (district),
    INDEX idx_members_status (status),
    INDEX idx_members_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) DEFAULT 'Anonymous',
    district VARCHAR(150) NOT NULL,
    issue VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'pending',
    priority VARCHAR(20) DEFAULT 'Medium',
    reply_text TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_complaints_status (status),
    INDEX idx_complaints_district (district),
    INDEX idx_complaints_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- 3) HERO / HOMEPAGE CMS TABLES
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS hero_section (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subtitle TEXT NOT NULL,
    badge_text VARCHAR(255) DEFAULT NULL,
    heading_line VARCHAR(255) DEFAULT NULL,
    btn1_text VARCHAR(120) DEFAULT NULL,
    btn1_link VARCHAR(255) DEFAULT NULL,
    btn2_text VARCHAR(120) DEFAULT NULL,
    btn2_link VARCHAR(255) DEFAULT NULL,
    joined_label VARCHAR(160) DEFAULT NULL,
    growth_text VARCHAR(160) DEFAULT NULL,
    district_label VARCHAR(100) DEFAULT NULL,
    issues_label VARCHAR(100) DEFAULT NULL,
    cards_label VARCHAR(100) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    animation_type VARCHAR(40) DEFAULT 'fade',
    overlay_color VARCHAR(10) DEFAULT '#0f1b2e',
    background_image VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_hero_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_hero (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subtitle TEXT NOT NULL,
    bg_image VARCHAR(255) DEFAULT NULL,
    btn1_text VARCHAR(120) DEFAULT NULL,
    btn1_link VARCHAR(255) DEFAULT NULL,
    btn2_text VARCHAR(120) DEFAULT NULL,
    btn2_link VARCHAR(255) DEFAULT NULL,
    status TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(120) NOT NULL,
    value VARCHAR(80) NOT NULL,
    icon VARCHAR(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    images_json LONGTEXT DEFAULT NULL,
    short_description VARCHAR(255) DEFAULT NULL,
    full_description LONGTEXT DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'Notice',
    status VARCHAR(20) DEFAULT 'published',
    publish_at DATETIME DEFAULT NULL,
    views INT NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    image VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    youtube_link VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- 4) NEW ADMIN MODULE TABLES
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    district_name VARCHAR(160) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS media_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_type VARCHAR(40) NOT NULL,
    title VARCHAR(180) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_media_type (media_type),
    INDEX idx_media_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- 5) SAFE ALTER PATCHES (for existing databases)
-- -----------------------------------------------------

ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS badge_text VARCHAR(255) DEFAULT NULL;
ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS heading_line VARCHAR(255) DEFAULT NULL;
ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS joined_label VARCHAR(160) DEFAULT NULL;
ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS growth_text VARCHAR(160) DEFAULT NULL;
ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS district_label VARCHAR(100) DEFAULT NULL;
ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS issues_label VARCHAR(100) DEFAULT NULL;
ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS cards_label VARCHAR(100) DEFAULT NULL;
ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;
ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS animation_type VARCHAR(40) DEFAULT 'fade';
ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS overlay_color VARCHAR(10) DEFAULT '#0f1b2e';
ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;

ALTER TABLE complaints ADD COLUMN IF NOT EXISTS priority VARCHAR(20) DEFAULT 'Medium';
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS reply_text TEXT DEFAULT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE members ADD COLUMN IF NOT EXISTS full_name VARCHAR(150) DEFAULT NULL;
ALTER TABLE members ADD COLUMN IF NOT EXISTS designation VARCHAR(180) DEFAULT NULL;
ALTER TABLE members ADD COLUMN IF NOT EXISTS working_place VARCHAR(150) DEFAULT NULL;
ALTER TABLE members ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE members ADD COLUMN IF NOT EXISTS id_card_path VARCHAR(255) DEFAULT NULL;
ALTER TABLE members ADD COLUMN IF NOT EXISTS id_card_generated_at DATETIME DEFAULT NULL;
ALTER TABLE members ADD COLUMN IF NOT EXISTS id_card_path VARCHAR(255) DEFAULT NULL;
ALTER TABLE members ADD COLUMN IF NOT EXISTS id_card_generated_at DATETIME DEFAULT NULL;

-- -----------------------------------------------------
-- 6) DATA NORMALIZATION HELPERS
-- -----------------------------------------------------

UPDATE members SET full_name = name WHERE (full_name IS NULL OR full_name = '') AND name IS NOT NULL;
UPDATE members SET name = full_name WHERE (name IS NULL OR name = '') AND full_name IS NOT NULL;
UPDATE members SET working_place = hospital WHERE (working_place IS NULL OR working_place = '') AND hospital IS NOT NULL;
UPDATE members SET hospital = working_place WHERE (hospital IS NULL OR hospital = '') AND working_place IS NOT NULL;
UPDATE members SET role = designation WHERE (role IS NULL OR role = '') AND designation IS NOT NULL;

-- -----------------------------------------------------
-- 7) SEED DATA
-- -----------------------------------------------------

INSERT INTO admin_users (username, password)
SELECT 'admin', 'admin123'
WHERE NOT EXISTS (SELECT 1 FROM admin_users WHERE username = 'admin');

INSERT INTO admins (username, password, full_name, email)
SELECT 'admin', 'admin123', 'APCSNSC Administrator', 'admin@APCSNSC.local'
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE username = 'admin');

INSERT INTO hero_section (
    title, subtitle, badge_text, heading_line,
    btn1_text, btn1_link, btn2_text, btn2_link,
    joined_label, growth_text, district_label, issues_label, cards_label,
    sort_order, animation_type, overlay_color, is_active
)
SELECT
    'Voice of Contract Staff Nurses',
    'Join our union and stand together for a better future for contract staff nurses across Andhra Pradesh.',
    'APCSNSC - Strength - Unity - Justice',
    'Fighting for Equality, Job Security & Dignity',
    'Join the Union', '/register.php', 'Submit Issue', '/pages/contact.php',
    'Nurses Already Joined', 'Growing Strong Every Day', 'Districts', 'Active Issues', 'ID Cards Issued',
    1, 'fade', '#0f1b2e', 1
WHERE NOT EXISTS (SELECT 1 FROM hero_section);

INSERT INTO homepage_stats (label, value, icon)
SELECT 'Total Members', '1200+', 'M'
WHERE NOT EXISTS (SELECT 1 FROM homepage_stats WHERE label = 'Total Members');

INSERT INTO homepage_stats (label, value, icon)
SELECT 'Districts', '26', 'D'
WHERE NOT EXISTS (SELECT 1 FROM homepage_stats WHERE label = 'Districts');

INSERT INTO homepage_stats (label, value, icon)
SELECT 'Issues Raised', '450+', 'I'
WHERE NOT EXISTS (SELECT 1 FROM homepage_stats WHERE label = 'Issues Raised');

INSERT INTO homepage_stats (label, value, icon)
SELECT 'Cards Issued', '980+', 'C'
WHERE NOT EXISTS (SELECT 1 FROM homepage_stats WHERE label = 'Cards Issued');

SET FOREIGN_KEY_CHECKS = 1;
