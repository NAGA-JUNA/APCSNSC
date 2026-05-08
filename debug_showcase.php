<?php
require_once __DIR__ . '/db.php';

echo "=== Database Check ===\n\n";

// Check tables
try {
    $tables = fetch_all("SHOW TABLES LIKE 'showcase_%'");
    echo "✓ Tables found: " . count($tables) . "\n";
    
    if ($tables) {
        foreach ($tables as $t) {
            $table = array_values($t)[0];
            echo "  - $table\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Error checking tables: " . $e->getMessage() . "\n";
}

echo "\n=== Sections Check ===\n\n";

// Check sections
try {
    $sections = fetch_all("SELECT id, section_name, section_type, is_active FROM showcase_sections");
    echo "✓ Sections in DB: " . count($sections) . "\n";
    
    if ($sections) {
        foreach ($sections as $s) {
            echo "  - {$s['section_name']} ({$s['section_type']}) - Active: {$s['is_active']}\n";
        }
    } else {
        echo "  ⚠ No sections found (should auto-create on homepage load)\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking sections: " . $e->getMessage() . "\n";
}

echo "\n=== Items Check ===\n\n";

// Check items
try {
    $items = fetch_all("SELECT COUNT(*) as cnt FROM showcase_section_items");
    $count = $items[0]['cnt'] ?? 0;
    echo "✓ Total items: $count\n";
} catch (Exception $e) {
    echo "✗ Error checking items: " . $e->getMessage() . "\n";
}

echo "\n✓ Check complete. Visit homepage to auto-create sections.\n";
?>
