<?php
/**
 * Showcase Activation Script
 * Run this to ensure showcase tables and default sections are created
 */

require_once __DIR__ . '/db.php';

echo "=== Media Showcase Activation ===\n\n";

try {
    // Create tables
    echo "1. Creating tables...\n";
    
    execute_query('CREATE TABLE IF NOT EXISTS `showcase_sections` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `section_type` VARCHAR(50) NOT NULL,
      `section_name` VARCHAR(255) NOT NULL,
      `section_icon` VARCHAR(100) DEFAULT "fa-film",
      `section_order` INT DEFAULT 0,
      `is_active` TINYINT(1) DEFAULT 1,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (section_type),
      INDEX (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    
    execute_query('CREATE TABLE IF NOT EXISTS `showcase_section_items` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `section_id` INT NOT NULL,
      `item_title` VARCHAR(255) NOT NULL,
      `item_description` TEXT,
      `item_file` VARCHAR(255),
      `item_thumbnail` VARCHAR(255),
      `item_category` VARCHAR(100),
      `item_date` DATE,
      `item_order` INT DEFAULT 0,
      `is_featured` TINYINT(1) DEFAULT 0,
      `is_active` TINYINT(1) DEFAULT 1,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (section_id) REFERENCES showcase_sections(id) ON DELETE CASCADE,
      INDEX (section_id),
      INDEX (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    
    echo "   ✓ Tables created/verified\n\n";
    
    // Create default sections
    echo "2. Creating default sections...\n";
    
    $defaults = [
        ['videos', 'Latest Videos', 'fa-video', 1],
        ['media', 'Media Library', 'fa-images', 2],
        ['epaper', 'ePublications', 'fa-newspaper', 3],
        ['poster', 'Posters & Banners', 'fa-image', 4],
    ];
    
    foreach ($defaults as [$type, $name, $icon, $order]) {
        $exists = fetch_one('SELECT id FROM showcase_sections WHERE section_type=?', [$type]);
        if (!$exists) {
            execute_query(
                'INSERT INTO showcase_sections (section_type, section_name, section_icon, section_order, is_active) VALUES (?, ?, ?, ?, 1)',
                [$type, $name, $icon, $order]
            );
            echo "   ✓ Created: $name\n";
        } else {
            echo "   ✓ Already exists: $name\n";
        }
    }
    
    echo "\n✅ Activation Complete!\n\n";
    
    // Show current status
    $sections = fetch_all('SELECT id, section_name, section_type, is_active FROM showcase_sections ORDER BY section_order ASC');
    echo "Current Sections:\n";
    foreach ($sections as $sec) {
        $status = $sec['is_active'] ? '✓ Active' : '✗ Inactive';
        echo "  - {$sec['section_name']} ({$sec['section_type']}) [$status]\n";
    }
    
    echo "\n📝 Next Steps:\n";
    echo "  1. Go to: admin/showcase_sections.php\n";
    echo "  2. Add content items to sections\n";
    echo "  3. Visit homepage to see the showcase\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
