-- cPanel shared hosting: select your database (svaobtfy_APCSNSC) in phpMyAdmin before importing.

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
);

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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS homepage_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(120) NOT NULL,
    value VARCHAR(80) NOT NULL,
    icon VARCHAR(40) DEFAULT NULL
);

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
);

CREATE TABLE IF NOT EXISTS homepage_districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    image VARCHAR(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS homepage_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    youtube_link VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    district VARCHAR(150) NOT NULL,
    hospital VARCHAR(180) NOT NULL,
    role VARCHAR(120) DEFAULT 'Staff Nurse',
    experience INT DEFAULT 0,
    photo VARCHAR(255) DEFAULT NULL,
    member_id VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) DEFAULT 'Anonymous',
    district VARCHAR(150) NOT NULL,
    issue VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'resolved') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

INSERT INTO homepage_hero (title, subtitle, bg_image, btn1_text, btn1_link, btn2_text, btn2_link, status)
SELECT 'United for Nurses Rights', 'APCSNSC stands for fair recognition and welfare of contract staff nurses across Andhra Pradesh.', NULL, 'Join the Union', '/register.php', 'Submit Issue', '/pages/contact.php', 1
WHERE NOT EXISTS (SELECT 1 FROM homepage_hero);

INSERT INTO hero_section (title, subtitle, badge_text, heading_line, btn1_text, btn1_link, btn2_text, btn2_link, joined_label, growth_text, district_label, issues_label, cards_label, sort_order, animation_type, overlay_color, background_image, is_active)
SELECT 'United for Nurses Rights', 'APCSNSC stands for fair recognition and welfare of contract staff nurses across Andhra Pradesh.', 'APCSNSC - Strength • Unity • Justice', 'Fighting for Equality, Job Security & Dignity', 'Join the Union', '/register.php', 'Submit Issue', '/pages/contact.php', 'Nurses Already Joined', 'Growing Strong Every Day', 'Districts', 'Active Issues', 'ID Cards Issued', 1, 'fade', '#0f1b2e', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM hero_section);

INSERT INTO homepage_stats (label, value, icon)
SELECT 'Total Members', '1200+', 'M' WHERE NOT EXISTS (SELECT 1 FROM homepage_stats WHERE label = 'Total Members');
INSERT INTO homepage_stats (label, value, icon)
SELECT 'Districts', '26', 'D' WHERE NOT EXISTS (SELECT 1 FROM homepage_stats WHERE label = 'Districts');
INSERT INTO homepage_stats (label, value, icon)
SELECT 'Issues Raised', '450+', 'I' WHERE NOT EXISTS (SELECT 1 FROM homepage_stats WHERE label = 'Issues Raised');
INSERT INTO homepage_stats (label, value, icon)
SELECT 'Cards Issued', '980+', 'C' WHERE NOT EXISTS (SELECT 1 FROM homepage_stats WHERE label = 'Cards Issued');

INSERT INTO homepage_updates (title, description, image, short_description, full_description, category, status, views, is_featured)
SELECT 'State-level meeting concluded', 'District representatives discussed regularization demands and staffing policy priorities.', NULL, 'District representatives discussed regularization demands and staffing policy priorities.', 'District representatives discussed regularization demands and staffing policy priorities. More detailed article text can be added from admin.', 'Meeting', 'published', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM homepage_updates);

INSERT INTO homepage_districts (name, image)
SELECT 'Visakhapatnam', NULL WHERE NOT EXISTS (SELECT 1 FROM homepage_districts WHERE name = 'Visakhapatnam');
INSERT INTO homepage_districts (name, image)
SELECT 'Guntur', NULL WHERE NOT EXISTS (SELECT 1 FROM homepage_districts WHERE name = 'Guntur');
INSERT INTO homepage_districts (name, image)
SELECT 'Kurnool', NULL WHERE NOT EXISTS (SELECT 1 FROM homepage_districts WHERE name = 'Kurnool');

INSERT INTO homepage_media (youtube_link, title)
SELECT 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'APCSNSC Press Interaction'
WHERE NOT EXISTS (SELECT 1 FROM homepage_media);

INSERT INTO admin_users (username, password)
SELECT 'admin', 'admin123'
WHERE NOT EXISTS (SELECT 1 FROM admin_users WHERE username = 'admin');
