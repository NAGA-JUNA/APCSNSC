<?php
/**
 * Admin: Showcase Sections Manager
 * Manage configurable showcase sections (Videos, Media, epapers, Posters, etc)
 */

require_once __DIR__ . '/../db.php';
require_admin();

$translations = get_translations();

// ============ CREATE TABLES IF NOT EXIST ============
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

// ============ HANDLE ACTIONS ============

$action = $_GET['action'] ?? '';
$section_id = (int)($_GET['section_id'] ?? 0);
$item_id = (int)($_GET['item_id'] ?? 0);

// Add/Update Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    
    if ($_POST['action'] === 'save_section') {
        $id = (int)($_POST['section_id'] ?? 0);
        $type = trim($_POST['section_type'] ?? '');
        $name = trim($_POST['section_name'] ?? '');
        $icon = trim($_POST['section_icon'] ?? 'fa-film');
        $order = (int)($_POST['section_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($type) || empty($name)) {
            $error = 'Section type and name required';
        } else {
            if ($id > 0) {
                // Update
                execute_query(
                    'UPDATE showcase_sections SET section_type=?, section_name=?, section_icon=?, section_order=?, is_active=? WHERE id=?',
                    [$type, $name, $icon, $order, $active, $id]
                );
                $success = 'Section updated successfully';
            } else {
                // Insert
                execute_query(
                    'INSERT INTO showcase_sections (section_type, section_name, section_icon, section_order, is_active) VALUES (?, ?, ?, ?, ?)',
                    [$type, $name, $icon, $order, $active]
                );
                $success = 'Section created successfully';
            }
        }
    }
    
    // Add/Update Item
    if ($_POST['action'] === 'save_item') {
        $id = (int)($_POST['item_id'] ?? 0);
        $sec_id = (int)($_POST['section_id'] ?? 0);
        $title = trim($_POST['item_title'] ?? '');
        $desc = trim($_POST['item_description'] ?? '');
        $file = trim($_POST['item_file'] ?? '');
        $thumb = trim($_POST['item_thumbnail'] ?? '');
        $cat = trim($_POST['item_category'] ?? '');
        $date = $_POST['item_date'] ?? date('Y-m-d');
        $order = (int)($_POST['item_order'] ?? 0);
        $featured = isset($_POST['is_featured']) ? 1 : 0;
        $active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($sec_id) || empty($title)) {
            $error = 'Section and item title required';
        } else {
            if ($id > 0) {
                // Update
                execute_query(
                    'UPDATE showcase_section_items SET item_title=?, item_description=?, item_file=?, item_thumbnail=?, item_category=?, item_date=?, item_order=?, is_featured=?, is_active=? WHERE id=?',
                    [$title, $desc, $file, $thumb, $cat, $date, $order, $featured, $active, $id]
                );
                $success = 'Item updated successfully';
            } else {
                // Insert
                execute_query(
                    'INSERT INTO showcase_section_items (section_id, item_title, item_description, item_file, item_thumbnail, item_category, item_date, item_order, is_featured, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$sec_id, $title, $desc, $file, $thumb, $cat, $date, $order, $featured, $active]
                );
                $success = 'Item created successfully';
            }
        }
    }
    
    // Delete Section
    if ($_POST['action'] === 'delete_section') {
        $id = (int)($_POST['section_id'] ?? 0);
        if ($id > 0) {
            execute_query('DELETE FROM showcase_sections WHERE id=?', [$id]);
            $success = 'Section deleted successfully';
        }
    }
    
    // Delete Item
    if ($_POST['action'] === 'delete_item') {
        $id = (int)($_POST['item_id'] ?? 0);
        if ($id > 0) {
            execute_query('DELETE FROM showcase_section_items WHERE id=?', [$id]);
            $success = 'Item deleted successfully';
        }
    }
}

// ============ FETCH DATA ============

$sections = fetch_all('SELECT * FROM showcase_sections ORDER BY section_order ASC, id DESC');
$section_types = ['videos', 'media', 'epaper', 'poster', 'gallery', 'news', 'custom'];
$fa_icons = ['fa-video', 'fa-images', 'fa-newspaper', 'fa-image', 'fa-photos', 'fa-newspaper-o', 'fa-file'];

$edit_section = null;
$edit_item = null;

if ($section_id > 0) {
    $edit_section = fetch_one('SELECT * FROM showcase_sections WHERE id=?', [$section_id]);
}

if ($item_id > 0) {
    $edit_item = fetch_one('SELECT * FROM showcase_section_items WHERE id=?', [$item_id]);
}

// Page settings for admin header/sidebar
$pageTitle = 'Media & Updates Section';
$activeMenu = 'showcase-sections';

require_once __DIR__ . '/_top.php';
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fa-solid fa-tv"></i> Media &amp; Updates Section</h2>
            <p class="text-muted">Controls the tabbed "Latest Media &amp; Updates" block on the homepage — manage tab sections (Videos, Media Library, ePublications, Posters &amp; Banners) and their items here.</p>
            <a href="<?= esc(base_url('admin/homepage_showcase.php')); ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-panorama"></i> Also manage: Benefits, Counters &amp; News &rarr;</a>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show"><strong>✓</strong> <?= esc($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show"><strong>✗</strong> <?= esc($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">
        <!-- Left: Sections List -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fa-solid fa-list"></i> Sections</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sections as $sec): ?>
                                <tr>
                                    <td><strong><?= esc($sec['section_name']); ?></strong></td>
                                    <td><span class="badge bg-info"><?= esc($sec['section_type']); ?></span></td>
                                    <td><?= (int)$sec['section_order']; ?></td>
                                    <td>
                                        <a href="?action=edit_section&section_id=<?= (int)$sec['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete section and all items?');">
                                            <input type="hidden" name="action" value="delete_section">
                                            <input type="hidden" name="section_id" value="<?= (int)$sec['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="?action=new_section" class="btn btn-success btn-sm"><i class="fa-solid fa-plus"></i> New Section</a>
                </div>
            </div>
        </div>

        <!-- Right: Edit/Add Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fa-solid fa-cog"></i> <?= $edit_section ? 'Edit Section' : 'New Section'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_section">
                        <input type="hidden" name="section_id" value="<?= $edit_section ? (int)$edit_section['id'] : 0; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Section Type *</label>
                            <select name="section_type" class="form-control" required>
                                <option value="">-- Select Type --</option>
                                <?php foreach ($section_types as $type): ?>
                                    <option value="<?= esc($type); ?>" <?= ($edit_section && $edit_section['section_type'] === $type) ? 'selected' : ''; ?>>
                                        <?= ucfirst(esc($type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Section Name *</label>
                            <input type="text" name="section_name" class="form-control" placeholder="e.g., Latest Videos" 
                                   value="<?= $edit_section ? esc($edit_section['section_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Icon Class</label>
                            <input type="text" name="section_icon" class="form-control" placeholder="e.g., fa-video" 
                                   value="<?= $edit_section ? esc($edit_section['section_icon']) : 'fa-film'; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" name="section_order" class="form-control" 
                                   value="<?= $edit_section ? (int)$edit_section['section_order'] : 0; ?>">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" 
                                   <?= (!$edit_section || $edit_section['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label">Active</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Section</button>
                        <?php if ($edit_section): ?>
                            <a href="?" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Items -->
    <?php if ($edit_section): ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fa-solid fa-layer-group"></i> Items for "<?= esc($edit_section['section_name']); ?>"</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Featured</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $items = fetch_all('SELECT * FROM showcase_section_items WHERE section_id=? ORDER BY item_order ASC, id DESC', [(int)$edit_section['id']]);
                                foreach ($items as $item): 
                                ?>
                                    <tr>
                                        <td><strong><?= esc($item['item_title']); ?></strong></td>
                                        <td><?= esc($item['item_category'] ?? '-'); ?></td>
                                        <td><?= esc($item['item_date'] ?? '-'); ?></td>
                                        <td><?= $item['is_featured'] ? '⭐' : ''; ?></td>
                                        <td>
                                            <a href="?action=edit_item&item_id=<?= (int)$item['id']; ?>&section_id=<?= (int)$edit_section['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete item?');">
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="item_id" value="<?= (int)$item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <a href="?action=new_item&section_id=<?= (int)$edit_section['id']; ?>" class="btn btn-success btn-sm"><i class="fa-solid fa-plus"></i> Add Item</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit/Add Item Form -->
        <div class="row mt-4">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fa-solid fa-file-alt"></i> <?= $edit_item ? 'Edit Item' : 'New Item'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="save_item">
                            <input type="hidden" name="item_id" value="<?= $edit_item ? (int)$edit_item['id'] : 0; ?>">
                            <input type="hidden" name="section_id" value="<?= (int)$edit_section['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Item Title *</label>
                                <input type="text" name="item_title" class="form-control" placeholder="e.g., Event Highlights" 
                                       value="<?= $edit_item ? esc($edit_item['item_title']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="item_description" class="form-control" rows="3" placeholder="Item description"><?= $edit_item ? esc($edit_item['item_description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">File/URL</label>
                                <input type="text" name="item_file" class="form-control" placeholder="e.g., YouTube ID or file path" 
                                       value="<?= $edit_item ? esc($edit_item['item_file']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Thumbnail URL</label>
                                <input type="text" name="item_thumbnail" class="form-control" placeholder="https://..." 
                                       value="<?= $edit_item ? esc($edit_item['item_thumbnail']) : ''; ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <input type="text" name="item_category" class="form-control" placeholder="e.g., Events" 
                                           value="<?= $edit_item ? esc($edit_item['item_category']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date</label>
                                    <input type="date" name="item_date" class="form-control" 
                                           value="<?= $edit_item ? esc($edit_item['item_date']) : date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Display Order</label>
                                    <input type="number" name="item_order" class="form-control" 
                                           value="<?= $edit_item ? (int)$edit_item['item_order'] : 0; ?>">
                                </div>
                                <div class="col-md-6 mb-3 form-check">
                                    <input type="checkbox" name="is_featured" class="form-check-input" 
                                           <?= ($edit_item && $edit_item['is_featured']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Featured</label>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" 
                                       <?= (!$edit_item || $edit_item['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label">Active</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Item</button>
                            <a href="?action=edit_section&section_id=<?= (int)$edit_section['id']; ?>" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/_bottom.php'; ?>