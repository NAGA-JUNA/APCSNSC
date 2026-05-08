-- ============================================
-- Showcase Sections Configuration
-- Allows admin to manage multiple sections (Videos, Media, epapers, Posters, etc)
-- ============================================

-- Main sections table
CREATE TABLE IF NOT EXISTS `showcase_sections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `section_type` VARCHAR(50) NOT NULL COMMENT 'videos, gallery, news, media, epaper, poster, etc',
  `section_name` VARCHAR(255) NOT NULL COMMENT 'Display name (e.g., Latest Videos)',
  `section_icon` VARCHAR(100) DEFAULT 'fa-film' COMMENT 'Font Awesome icon class',
  `section_order` INT DEFAULT 0 COMMENT 'Display order on frontend',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (section_type),
  INDEX (is_active),
  INDEX (section_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Section items (content for each section)
CREATE TABLE IF NOT EXISTS `showcase_section_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `section_id` INT NOT NULL COMMENT 'FK to showcase_sections',
  `item_title` VARCHAR(255) NOT NULL,
  `item_description` TEXT,
  `item_file` VARCHAR(255) COMMENT 'File path or URL',
  `item_thumbnail` VARCHAR(255) COMMENT 'Thumbnail image',
  `item_category` VARCHAR(100) COMMENT 'Category/tag',
  `item_date` DATE,
  `item_order` INT DEFAULT 0,
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES showcase_sections(id) ON DELETE CASCADE,
  INDEX (section_id),
  INDEX (is_active),
  INDEX (item_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default sections
INSERT INTO `showcase_sections` (`section_type`, `section_name`, `section_icon`, `section_order`, `is_active`) VALUES
('videos', 'Latest Videos', 'fa-video', 1, 1),
('media', 'Media Library', 'fa-images', 2, 1),
('epaper', 'ePublications', 'fa-newspaper', 3, 1),
('poster', 'Posters & Banners', 'fa-image', 4, 1)
ON DUPLICATE KEY UPDATE section_order=VALUES(section_order);
