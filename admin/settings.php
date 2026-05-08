<?php
require_once __DIR__ . '/../db.php';
require_admin();

if (!is_super_admin()) {
    set_flash('error', 'Access denied. Super Administrator only.');
    redirect_to('admin/dashboard.php');
}

$isSuperAdmin = is_super_admin();

$ensureAdminManagementSchema = static function (): void {
    $columns = fetch_all('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "admin_users"');
    $columnSet = [];
    foreach ($columns as $col) {
        $columnSet[(string)$col['COLUMN_NAME']] = true;
    }

    $alterMap = [
        'full_name' => 'ALTER TABLE admin_users ADD COLUMN full_name VARCHAR(150) DEFAULT NULL AFTER username',
        'email' => 'ALTER TABLE admin_users ADD COLUMN email VARCHAR(180) DEFAULT NULL AFTER full_name',
        'role' => 'ALTER TABLE admin_users ADD COLUMN role VARCHAR(40) DEFAULT "super_admin" AFTER password',
        'district' => 'ALTER TABLE admin_users ADD COLUMN district VARCHAR(150) DEFAULT NULL AFTER role',
        'state' => 'ALTER TABLE admin_users ADD COLUMN state VARCHAR(120) DEFAULT "Andhra Pradesh" AFTER district',
        'is_active' => 'ALTER TABLE admin_users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER state',
        'can_approve_payments' => 'ALTER TABLE admin_users ADD COLUMN can_approve_payments TINYINT(1) DEFAULT 1 AFTER is_active',
        'can_approve_id_cards' => 'ALTER TABLE admin_users ADD COLUMN can_approve_id_cards TINYINT(1) DEFAULT 1 AFTER can_approve_payments',
        'mobile' => 'ALTER TABLE admin_users ADD COLUMN mobile VARCHAR(30) DEFAULT NULL',
        'last_login' => 'ALTER TABLE admin_users ADD COLUMN last_login DATETIME DEFAULT NULL',
    ];

    foreach ($alterMap as $columnName => $sql) {
        if (isset($columnSet[$columnName])) {
            continue;
        }

        try {
            execute_query($sql);
        } catch (Throwable $e) {
            // Keep settings usable even on restricted shared-host schemas.
        }
    }

    execute_query(
        'CREATE TABLE IF NOT EXISTS admin_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_key VARCHAR(50) NOT NULL UNIQUE,
            role_name VARCHAR(120) NOT NULL,
            scope_level ENUM("global","state","district","module") NOT NULL DEFAULT "global",
            is_system TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    execute_query(
        'CREATE TABLE IF NOT EXISTS admin_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            permission_key VARCHAR(80) NOT NULL,
            is_allowed TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_admin_permission (admin_id, permission_key),
            INDEX idx_admin_permission_admin (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    execute_query(
        'CREATE TABLE IF NOT EXISTS admin_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            actor_admin_id INT NOT NULL,
            target_admin_id INT DEFAULT NULL,
            action VARCHAR(120) NOT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_logs_target (target_admin_id),
            INDEX idx_admin_logs_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $seedRoles = [
        ['super_admin', 'Super Admin', 'global'],
        ['state_president', 'State President', 'state'],
        ['district_president', 'District President', 'district'],
        ['media_admin', 'Media Admin', 'module'],
        ['complaint_admin', 'Complaint Admin', 'module'],
    ];

    foreach ($seedRoles as $seed) {
        execute_query(
            'INSERT INTO admin_roles (role_key, role_name, scope_level, is_system)
             VALUES (:role_key, :role_name, :scope_level, 1)
             ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), scope_level = VALUES(scope_level)',
            [
                ':role_key' => $seed[0],
                ':role_name' => $seed[1],
                ':scope_level' => $seed[2],
            ]
        );
    }
};

$ensureAdminManagementSchema();

$isSuperAdmin = is_super_admin();

$pdo = db();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(150) DEFAULT NULL,
        email VARCHAR(180) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS homepage_showcase_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value VARCHAR(255) DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        redirect_to('admin/settings.php');
    }

    if (isset($_POST['save_profile'])) {
        $siteName = clean($_POST['site_name'] ?? 'APCSNSC');
        $contactInfo = clean($_POST['contact_info'] ?? '');
        $adminProfile = clean($_POST['admin_profile'] ?? '');
        $siteLogo = upload_image($_FILES['site_logo'] ?? [], 'uploads/settings');

        $saveSetting = static function (string $key, ?string $value): void {
            execute_query(
                'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [
                    ':setting_key' => $key,
                    ':setting_value' => $value,
                ]
            );
        };

        $saveSetting('site_name', $siteName);
        $saveSetting('contact_info', $contactInfo);
        $saveSetting('admin_profile', $adminProfile);

        if ($siteLogo !== null) {
            $saveSetting('site_logo', $siteLogo);
        }
    }

    if (isset($_POST['change_password'])) {
        $newPassword = (string)($_POST['new_password'] ?? '');
        if (strlen($newPassword) >= 6) {
            execute_query('UPDATE admin_users SET password = :password WHERE id = :id', [
                ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':id' => (int)($_SESSION['admin_user_id'] ?? 0),
            ]);
        }
    }

    if (isset($_POST['save_home_sections'])) {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            set_flash('error', 'Invalid request token.');
            redirect_to('admin/settings.php');
        }

        $sections = [
            'show_hero' => (isset($_POST['show_hero']) && (int)$_POST['show_hero'] === 1) ? '1' : '0',
            'show_union_cta' => (isset($_POST['show_union_cta']) && (int)$_POST['show_union_cta'] === 1) ? '1' : '0',
            'show_showcase_sections' => (isset($_POST['show_showcase_sections']) && (int)$_POST['show_showcase_sections'] === 1) ? '1' : '0',
            'show_updates' => (isset($_POST['show_updates']) && (int)$_POST['show_updates'] === 1) ? '1' : '0',
        ];

        try {
            foreach ($sections as $k => $v) {
                execute_query(
                    'INSERT INTO homepage_showcase_settings (setting_key, setting_value) VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                    [':k' => $k, ':v' => $v]
                );
            }
            set_flash('success', 'Homepage sections updated.');
        } catch (Throwable $e) {
            set_flash('error', 'Database error: ' . $e->getMessage());
        }

        redirect_to('admin/settings.php');
    }

    redirect_to('admin/settings.php');
}

$settingsRows = fetch_all('SELECT setting_key, setting_value FROM settings');
$settings = [];
foreach ($settingsRows as $row) {
    $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
}

$moduleCssFile = __DIR__ . '/../assets/css/admin-role-management.css';
$moduleJsFile = __DIR__ . '/../assets/js/admin-role-management.js';
$pageStyles = [base_url('assets/css/admin-role-management.css?v=' . (file_exists($moduleCssFile) ? (string)filemtime($moduleCssFile) : (string)time()))];
$pageScripts = [base_url('assets/js/admin-role-management.js?v=' . (file_exists($moduleJsFile) ? (string)filemtime($moduleJsFile) : (string)time()))];

$pageTitle = 'Settings';
$activeMenu = 'settings';
require_once __DIR__ . '/_top.php';
?>

<section class="admin-layout-2">
    <article class="admin-card">
        <h4>Admin Profile & Site Settings</h4>
        <form method="post" enctype="multipart/form-data" class="row g-3 mt-1">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
            <div class="col-md-6">
                <label class="form-label">Site Name</label>
                <input class="form-control" name="site_name" value="<?= esc((string)($settings['site_name'] ?? 'APCSNSC')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Contact Info</label>
                <input class="form-control" name="contact_info" value="<?= esc((string)($settings['contact_info'] ?? '')); ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Admin Profile</label>
                <textarea class="form-control" rows="3" name="admin_profile"><?= esc((string)($settings['admin_profile'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-8">
                <label class="form-label">Site Logo Upload</label>
                <input class="form-control" type="file" name="site_logo" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit" name="save_profile" value="1">Save Settings</button>
            </div>
        </form>
    </article>

    <article class="admin-card">
        <h4>Change Password</h4>
        <form method="post" class="row g-3 mt-1">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
            <div class="col-12">
                <label class="form-label">New Password</label>
                <input class="form-control" type="password" name="new_password" minlength="6" required>
            </div>
            <div class="col-12">
                <button class="btn btn-danger" type="submit" name="change_password" value="1">Update Password</button>
            </div>
        </form>

        <?php if (!empty($settings['site_logo'])): ?>
            <hr>
            <h6>Current Site Logo</h6>
            <img src="<?= esc(base_url((string)$settings['site_logo'])); ?>" alt="Site logo" style="width: 100px; height: 100px; object-fit: contain; border: 1px solid #dce6f0; border-radius: 8px; padding: 6px;">
        <?php endif; ?>
    </article>

        <article class="admin-card" style="border: 2px solid #10a860; border-radius: 12px;">
            <div style="display: flex; align-items: flex-start; gap: 16px;">
                <div style="font-size: 32px; color: #10a860;">🏗️</div>
                <div style="flex: 1;">
                    <h4 style="margin-bottom: 4px;">Home Sections Control</h4>
                    <p class="text-secondary" style="margin-bottom: 16px;">Toggle visibility of homepage sections for quick layout control.</p>
                </div>
            </div>

            <?php
            $homeRows = fetch_all('SELECT setting_key, setting_value FROM homepage_showcase_settings');
            $homeSettings = [];
            foreach ($homeRows as $r) {
                $homeSettings[(string)$r['setting_key']] = (string)$r['setting_value'];
            }
            $hs = function ($k, $def = '1') use ($homeSettings) {
                return isset($homeSettings[$k]) ? $homeSettings[$k] : $def;
            };
            
            $sections = [
                'show_hero' => ['label' => 'Hero Section', 'desc' => 'Main banner & homepage hero', 'icon' => '⭐'],
                'show_union_cta' => ['label' => 'Union CTA', 'desc' => 'Union join section call to action', 'icon' => '👥'],
                'show_showcase_sections' => ['label' => 'Showcase Sections', 'desc' => 'About, benefits & features sections', 'icon' => '🎯'],
                'show_updates' => ['label' => 'Updates / News', 'desc' => 'Latest news & announcements', 'icon' => '📢'],
            ];
            ?>

            <form method="post" style="margin-top: 24px;">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <?php foreach ($sections as $key => $section): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #dce6f0;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span style="font-size: 20px;"><?= $section['icon']; ?></span>
                                    <strong style="color: #0f172a;"><?= $section['label']; ?></strong>
                                </div>
                                <small style="color: #64748b;"><?= $section['desc']; ?></small>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-left: 12px;">
                                <label style="display: flex; align-items: center; cursor: pointer; gap: 8px;">
                                    <input type="checkbox" name="<?= $key; ?>" value="1" <?= $hs($key) === '1' ? 'checked' : ''; ?> style="width: 24px; height: 24px; cursor: pointer; accent-color: #10a860;">
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button class="btn btn-primary" type="submit" name="save_home_sections" value="1" style="background-color: #10a860; border: none; padding: 8px 20px;">
                        <i class="fa-solid fa-check" style="margin-right: 6px;"></i>Save Home Sections
                    </button>
                </div>
            </form>
        </article>
</section>

<?php if ($isSuperAdmin): ?>
<section class="admin-card role-management-shell" style="margin-top: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-1">Role & Permission Management</h4>
            <p class="mb-0 text-secondary">Modern control center for administrators, role hierarchy, module permissions, and audit logs.</p>
        </div>
        <button type="button" class="btn btn-primary" id="addAdminBtn">
            <i class="fa-solid fa-user-plus me-1"></i>Add New Admin
        </button>
    </div>

    <section class="role-summary-grid" id="roleSummaryGrid">
        <article class="role-summary-card"><p>Total Admins</p><h3 id="sumTotalAdmins">0</h3></article>
        <article class="role-summary-card"><p>Super Admins</p><h3 id="sumSuperAdmins">0</h3></article>
        <article class="role-summary-card"><p>State Presidents</p><h3 id="sumStatePresidents">0</h3></article>
        <article class="role-summary-card"><p>District Presidents</p><h3 id="sumDistrictPresidents">0</h3></article>
        <article class="role-summary-card"><p>Active Admins</p><h3 id="sumActiveAdmins">0</h3></article>
    </section>

    <section class="role-filter-bar mt-3">
        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label mb-1">Search</label>
                <input type="text" class="form-control" id="filterSearch" placeholder="Name or username">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label mb-1">Role</label>
                <select class="form-select" id="filterRole">
                    <option value="">All Roles</option>
                    <option value="super_admin">Super Admin</option>
                    <option value="state_president">State President</option>
                    <option value="district_president">District President</option>
                    <option value="media_admin">Media Admin</option>
                    <option value="complaint_admin">Complaint Admin</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label mb-1">State</label>
                <input type="text" class="form-control" id="filterState" placeholder="Andhra Pradesh">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label mb-1">District</label>
                <input type="text" class="form-control" id="filterDistrict" placeholder="Guntur">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label mb-1">Status</label>
                <select class="form-select" id="filterStatus">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-6 d-grid">
                <button type="button" class="btn btn-outline-secondary" id="clearFiltersBtn">Clear</button>
            </div>
        </div>
    </section>

    <section class="bulk-action-bar mt-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <select class="form-select" id="bulkAction" style="max-width: 220px;">
                <option value="">Bulk Actions</option>
                <option value="activate">Activate Selected</option>
                <option value="disable">Disable Selected</option>
                <option value="delete">Delete Selected</option>
                <option value="change_role">Change Role</option>
            </select>
            <select class="form-select" id="bulkRole" style="max-width: 220px; display:none;">
                <option value="">Select New Role</option>
                <option value="super_admin">Super Admin</option>
                <option value="state_president">State President</option>
                <option value="district_president">District President</option>
                <option value="media_admin">Media Admin</option>
                <option value="complaint_admin">Complaint Admin</option>
            </select>
            <button type="button" class="btn btn-primary" id="applyBulkBtn">Apply</button>
            <span class="text-secondary small" id="selectedCount">0 selected</span>
        </div>
    </section>

    <div class="table-responsive mt-3">
        <table class="table table-hover align-middle mb-0 role-table" id="adminRoleTable">
            <thead>
                <tr>
                    <th><input type="checkbox" id="checkAllAdmins"></th>
                    <th>Admin Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Role</th>
                    <th>State</th>
                    <th>District</th>
                    <th>Permissions</th>
                    <th>Last Login</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="adminRoleTableBody">
                <tr>
                    <td colspan="12" class="text-center py-4 text-secondary">Loading administrators...</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<div class="modal fade" id="adminFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminFormTitle">Add New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="adminForm">
                <div class="modal-body">
                    <input type="hidden" name="admin_id" id="adminIdField" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input class="form-control" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input class="form-control" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="text" name="email" placeholder="Optional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile</label>
                            <input class="form-control" name="mobile">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input class="form-control" type="password" name="password" minlength="6" placeholder="Required for new admin">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="formRole" required>
                                <option value="super_admin">Super Admin</option>
                                <option value="state_president">State President</option>
                                <option value="district_president">District President</option>
                                <option value="media_admin">Media Admin</option>
                                <option value="complaint_admin">Complaint Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">State</label>
                            <input class="form-control" name="state" value="Andhra Pradesh">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">District</label>
                            <input class="form-control" name="district" id="formDistrict" placeholder="Required for district president">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="disabled">Disabled</option>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <h6 class="mb-2">Permissions</h6>
                    <div class="permission-grid">
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="dashboard"><span class="form-check-label">Dashboard</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="members"><span class="form-check-label">Members</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="complaints"><span class="form-check-label">Complaints</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="media"><span class="form-check-label">Media</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="reports"><span class="form-check-label">Reports</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="id_cards"><span class="form-check-label">ID Cards</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="settings"><span class="form-check-label">Settings</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="updates"><span class="form-check-label">Updates</span></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="activityLogsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Activity Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="activityLogsBody" class="log-list-wrap">
                    <p class="text-secondary mb-0">Loading logs...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.AdminRoleConfig = {
    csrfToken: <?= json_encode(csrf_token()); ?>,
    endpoint: <?= json_encode(base_url('admin/ajax_role_management.php')); ?>,
    currentAdminId: <?= (int)($_SESSION['admin_user_id'] ?? 0); ?>
};
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/_bottom.php'; ?>
