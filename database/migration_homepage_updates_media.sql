-- =====================================================
-- APCSNSC HOMEPAGE UPDATES MEDIA MIGRATION
-- =====================================================
-- Adds multi-image support and content fields for homepage updates

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE homepage_updates
ADD COLUMN IF NOT EXISTS images_json LONGTEXT DEFAULT NULL AFTER image,
ADD COLUMN IF NOT EXISTS short_description VARCHAR(255) DEFAULT NULL AFTER images_json,
ADD COLUMN IF NOT EXISTS full_description LONGTEXT DEFAULT NULL AFTER short_description,
ADD COLUMN IF NOT EXISTS category VARCHAR(50) DEFAULT 'Notice' AFTER full_description,
ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'published' AFTER category,
ADD COLUMN IF NOT EXISTS publish_at DATETIME DEFAULT NULL AFTER status,
ADD COLUMN IF NOT EXISTS views INT NOT NULL DEFAULT 0 AFTER publish_at,
ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER views,
ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE homepage_updates
SET
    short_description = COALESCE(short_description, LEFT(description, 255)),
    full_description = COALESCE(full_description, description),
    category = COALESCE(NULLIF(category, ''), 'Notice'),
    status = COALESCE(NULLIF(status, ''), 'published'),
    views = COALESCE(views, 0),
    is_featured = COALESCE(is_featured, 0)
WHERE id > 0;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'homepage_updates media migration complete' AS message;
