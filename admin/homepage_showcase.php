<?php
require_once __DIR__ . '/../db.php';
require_admin();

$ensureTables = [
    'CREATE TABLE IF NOT EXISTS homepage_showcase_benefits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        icon VARCHAR(120) NOT NULL DEFAULT "fa-solid fa-star",
        title VARCHAR(180) NOT NULL,
        link VARCHAR(255) DEFAULT "#",
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'CREATE TABLE IF NOT EXISTS homepage_showcase_videos (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'CREATE TABLE IF NOT EXISTS homepage_showcase_gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        title VARCHAR(180) DEFAULT NULL,
        category VARCHAR(80) DEFAULT NULL,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'CREATE TABLE IF NOT EXISTS homepage_showcase_news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notice_text VARCHAR(255) NOT NULL,
        full_text TEXT DEFAULT NULL,
        notice_priority VARCHAR(20) NOT NULL DEFAULT "normal",
        notice_link VARCHAR(255) DEFAULT "#",
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'CREATE TABLE IF NOT EXISTS homepage_showcase_settings (
        setting_key VARCHAR(120) PRIMARY KEY,
        setting_value VARCHAR(255) DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
];

foreach ($ensureTables as $ddl) {
    execute_query($ddl);
}

execute_query('ALTER TABLE homepage_showcase_videos ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL AFTER title');
execute_query('ALTER TABLE homepage_showcase_videos ADD COLUMN IF NOT EXISTS duration_text VARCHAR(20) DEFAULT NULL AFTER description');
execute_query('ALTER TABLE homepage_showcase_videos ADD COLUMN IF NOT EXISTS published_on DATE DEFAULT NULL AFTER duration_text');
execute_query('ALTER TABLE homepage_showcase_gallery ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER category');
execute_query('ALTER TABLE homepage_showcase_news ADD COLUMN IF NOT EXISTS full_text TEXT DEFAULT NULL AFTER notice_text');
execute_query('ALTER TABLE homepage_showcase_news ADD COLUMN IF NOT EXISTS notice_priority VARCHAR(20) NOT NULL DEFAULT "normal" AFTER full_text');

$defaultSettings = [
    'counter_total_members_mode' => 'auto',
    'counter_total_members_label' => 'Total Members',
    'counter_total_members_value' => '0',
    'counter_complaints_solved_mode' => 'auto',
    'counter_complaints_solved_label' => 'Complaints Solved',
    'counter_complaints_solved_value' => '0',
    'counter_districts_active_mode' => 'auto',
    'counter_districts_active_label' => 'Districts Active',
    'counter_districts_active_value' => '0',
    'counter_id_cards_issued_mode' => 'auto',
    'counter_id_cards_issued_label' => 'ID Cards Issued',
    'counter_id_cards_issued_value' => '0',
    'news_ticker_speed_seconds' => '28',
];

foreach ($defaultSettings as $k => $v) {
    execute_query('INSERT IGNORE INTO homepage_showcase_settings (setting_key, setting_value) VALUES (:k, :v)', [
        ':k' => $k,
        ':v' => $v,
    ]);
}

$parseYouTubeId = static function (string $input): string {
    $value = trim($input);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $value) === 1) {
        return $value;
    }

    $parts = parse_url($value);
    if (!is_array($parts)) {
        return '';
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    if (strpos($host, 'youtu.be') !== false) {
        return trim((string)($parts['path'] ?? ''), '/');
    }

    if (!empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
        if (!empty($query['v']) && preg_match('/^[a-zA-Z0-9_-]{11}$/', (string)$query['v']) === 1) {
            return (string)$query['v'];
        }
    }

    if (!empty($parts['path']) && strpos((string)$parts['path'], '/embed/') !== false) {
        $chunks = explode('/embed/', (string)$parts['path']);
        return trim((string)($chunks[1] ?? ''), '/');
    }

    return '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security validation failed. Please try again.');
        redirect_to('admin/homepage_showcase.php');
    }

    $action = clean($_POST['action'] ?? '');

    if ($action === 'save_benefit') {
        $id = (int)($_POST['id'] ?? 0);
        $title = clean($_POST['title'] ?? '');
        $icon = clean($_POST['icon'] ?? 'fa-solid fa-star');
        $link = trim((string)($_POST['link'] ?? '#'));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title !== '') {
            if ($id > 0) {
                execute_query('UPDATE homepage_showcase_benefits SET icon = :icon, title = :title, link = :link, sort_order = :sort_order, is_active = :is_active WHERE id = :id', [
                    ':icon' => $icon,
                    ':title' => $title,
                    ':link' => $link === '' ? '#' : $link,
                    ':sort_order' => $sortOrder,
                    ':is_active' => $isActive,
                    ':id' => $id,
                ]);
            } else {
                execute_query('INSERT INTO homepage_showcase_benefits (icon, title, link, sort_order, is_active) VALUES (:icon, :title, :link, :sort_order, :is_active)', [
                    ':icon' => $icon,
                    ':title' => $title,
                    ':link' => $link === '' ? '#' : $link,
                    ':sort_order' => $sortOrder,
                    ':is_active' => $isActive,
                ]);
            }
            set_flash('success', 'Benefit saved successfully.');
        }

        redirect_to('admin/homepage_showcase.php');
    }

    if ($action === 'save_video') {
        $id = (int)($_POST['id'] ?? 0);
        $title = clean($_POST['title'] ?? '');
        $videoUrl = trim((string)($_POST['video_url'] ?? ''));
        $videoId = $parseYouTubeId($videoUrl);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title !== '' && $videoId !== '') {
            if ($isFeatured === 1) {
                execute_query('UPDATE homepage_showcase_videos SET is_featured = 0');
            }

            if ($id > 0) {
                execute_query('UPDATE homepage_showcase_videos SET title = :title, video_url = :video_url, video_id = :video_id, sort_order = :sort_order, is_featured = :is_featured, is_active = :is_active WHERE id = :id', [
                    ':title' => $title,
                    ':video_url' => $videoUrl,
                    ':video_id' => $videoId,
                    ':sort_order' => $sortOrder,
                    ':is_featured' => $isFeatured,
                    ':is_active' => $isActive,
                    ':id' => $id,
                ]);
            } else {
                execute_query('INSERT INTO homepage_showcase_videos (title, video_url, video_id, sort_order, is_featured, is_active) VALUES (:title, :video_url, :video_id, :sort_order, :is_featured, :is_active)', [
                    ':title' => $title,
                    ':video_url' => $videoUrl,
                    ':video_id' => $videoId,
                    ':sort_order' => $sortOrder,
                    ':is_featured' => $isFeatured,
                    ':is_active' => $isActive,
                ]);
            }
            set_flash('success', 'Video saved successfully.');
        } else {
            set_flash('error', 'Provide a valid YouTube URL and title.');
        }

        redirect_to('admin/homepage_showcase.php');
    }

    if ($action === 'save_gallery') {
        $id = (int)($_POST['id'] ?? 0);
        $title = clean($_POST['title'] ?? '');
        $category = clean($_POST['category'] ?? 'General');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $imagePath = upload_image($_FILES['image_file'] ?? [], 'uploads/showcase/gallery');

        if ($id > 0) {
            $existing = fetch_one('SELECT image_path FROM homepage_showcase_gallery WHERE id = :id', [':id' => $id]);
            execute_query('UPDATE homepage_showcase_gallery SET image_path = :image_path, title = :title, category = :category, sort_order = :sort_order, is_active = :is_active WHERE id = :id', [
                ':image_path' => $imagePath ?: (string)($existing['image_path'] ?? ''),
                ':title' => $title,
                ':category' => $category,
                ':sort_order' => $sortOrder,
                ':is_active' => $isActive,
                ':id' => $id,
            ]);
            set_flash('success', 'Gallery item updated successfully.');
        } else {
            if ($imagePath !== null) {
                execute_query('INSERT INTO homepage_showcase_gallery (image_path, title, category, sort_order, is_active) VALUES (:image_path, :title, :category, :sort_order, :is_active)', [
                    ':image_path' => $imagePath,
                    ':title' => $title,
                    ':category' => $category,
                    ':sort_order' => $sortOrder,
                    ':is_active' => $isActive,
                ]);
                set_flash('success', 'Gallery item added successfully.');
            } else {
                set_flash('error', 'Upload a valid JPG/PNG/WEBP image for new gallery item.');
            }
        }

        redirect_to('admin/homepage_showcase.php');
    }

    if ($action === 'save_news') {
        $id = (int)($_POST['id'] ?? 0);
        $noticeText = clean($_POST['notice_text'] ?? '');
        $noticeLink = trim((string)($_POST['notice_link'] ?? '#'));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($noticeText !== '') {
            if ($id > 0) {
                execute_query('UPDATE homepage_showcase_news SET notice_text = :notice_text, notice_link = :notice_link, sort_order = :sort_order, is_active = :is_active WHERE id = :id', [
                    ':notice_text' => $noticeText,
                    ':notice_link' => $noticeLink === '' ? '#' : $noticeLink,
                    ':sort_order' => $sortOrder,
                    ':is_active' => $isActive,
                    ':id' => $id,
                ]);
            } else {
                execute_query('INSERT INTO homepage_showcase_news (notice_text, notice_link, sort_order, is_active) VALUES (:notice_text, :notice_link, :sort_order, :is_active)', [
                    ':notice_text' => $noticeText,
                    ':notice_link' => $noticeLink === '' ? '#' : $noticeLink,
                    ':sort_order' => $sortOrder,
                    ':is_active' => $isActive,
                ]);
            }
            set_flash('success', 'Breaking news item saved.');
        }

        redirect_to('admin/homepage_showcase.php');
    }

    if ($action === 'save_settings') {
        $settingPayload = [
            'news_ticker_speed_seconds' => (string)max(10, min(90, (int)($_POST['news_ticker_speed_seconds'] ?? 28))),
            'counter_total_members_mode' => ($_POST['counter_total_members_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto',
            'counter_total_members_label' => clean($_POST['counter_total_members_label'] ?? 'Total Members'),
            'counter_total_members_value' => (string)max(0, (int)($_POST['counter_total_members_value'] ?? 0)),
            'counter_complaints_solved_mode' => ($_POST['counter_complaints_solved_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto',
            'counter_complaints_solved_label' => clean($_POST['counter_complaints_solved_label'] ?? 'Complaints Solved'),
            'counter_complaints_solved_value' => (string)max(0, (int)($_POST['counter_complaints_solved_value'] ?? 0)),
            'counter_districts_active_mode' => ($_POST['counter_districts_active_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto',
            'counter_districts_active_label' => clean($_POST['counter_districts_active_label'] ?? 'Districts Active'),
            'counter_districts_active_value' => (string)max(0, (int)($_POST['counter_districts_active_value'] ?? 0)),
            'counter_id_cards_issued_mode' => ($_POST['counter_id_cards_issued_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto',
            'counter_id_cards_issued_label' => clean($_POST['counter_id_cards_issued_label'] ?? 'ID Cards Issued'),
            'counter_id_cards_issued_value' => (string)max(0, (int)($_POST['counter_id_cards_issued_value'] ?? 0)),
        ];

        foreach ($settingPayload as $key => $value) {
            execute_query('UPDATE homepage_showcase_settings SET setting_value = :value WHERE setting_key = :key', [
                ':value' => $value,
                ':key' => $key,
            ]);
        }

        set_flash('success', 'Showcase settings saved successfully.');
        redirect_to('admin/homepage_showcase.php');
    }
}

if (isset($_GET['delete']) && isset($_GET['type'])) {
    if (!verify_csrf($_GET['token'] ?? null)) {
        set_flash('error', 'Security validation failed.');
        redirect_to('admin/homepage_showcase.php');
    }

    $deleteId = (int)$_GET['delete'];
    $type = clean($_GET['type']);

    if ($deleteId > 0 && $type === 'benefit') {
        execute_query('DELETE FROM homepage_showcase_benefits WHERE id = :id', [':id' => $deleteId]);
    }
    if ($deleteId > 0 && $type === 'video') {
        execute_query('DELETE FROM homepage_showcase_videos WHERE id = :id', [':id' => $deleteId]);
    }
    if ($deleteId > 0 && $type === 'gallery') {
        execute_query('DELETE FROM homepage_showcase_gallery WHERE id = :id', [':id' => $deleteId]);
    }
    if ($deleteId > 0 && $type === 'news') {
        execute_query('DELETE FROM homepage_showcase_news WHERE id = :id', [':id' => $deleteId]);
    }

    set_flash('success', 'Item deleted successfully.');
    redirect_to('admin/homepage_showcase.php');
}

$settingsRows = fetch_all('SELECT setting_key, setting_value FROM homepage_showcase_settings');
$settings = [];
foreach ($settingsRows as $row) {
    $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
}

$getSetting = static function (string $key, string $fallback = '') use ($settings): string {
    if (!array_key_exists($key, $settings)) {
        return $fallback;
    }

    return (string)$settings[$key];
};

$benefits = fetch_all('SELECT * FROM homepage_showcase_benefits ORDER BY sort_order ASC, id DESC');
$videos = fetch_all('SELECT * FROM homepage_showcase_videos ORDER BY is_featured DESC, sort_order ASC, id DESC');
$gallery = fetch_all('SELECT * FROM homepage_showcase_gallery ORDER BY sort_order ASC, id DESC');
$newsRows = fetch_all('SELECT * FROM homepage_showcase_news ORDER BY sort_order ASC, id DESC');

$editBenefit = isset($_GET['edit_benefit']) ? fetch_one('SELECT * FROM homepage_showcase_benefits WHERE id = :id', [':id' => (int)$_GET['edit_benefit']]) : null;
$editVideo = isset($_GET['edit_video']) ? fetch_one('SELECT * FROM homepage_showcase_videos WHERE id = :id', [':id' => (int)$_GET['edit_video']]) : null;
$editGallery = isset($_GET['edit_gallery']) ? fetch_one('SELECT * FROM homepage_showcase_gallery WHERE id = :id', [':id' => (int)$_GET['edit_gallery']]) : null;
$editNews = isset($_GET['edit_news']) ? fetch_one('SELECT * FROM homepage_showcase_news WHERE id = :id', [':id' => (int)$_GET['edit_news']]) : null;

$pageTitle = 'Benefits, Counters & News';
$activeMenu = 'homepage-showcase';
require_once __DIR__ . '/_top.php';
?>

<div class="admin-card mb-3" style="border-left:4px solid var(--ap-primary,#1a73e8);padding:14px 20px;">
    <strong><i class="fa-solid fa-circle-info"></i> What this page controls:</strong>
    <span class="text-muted ms-2">Member Benefits bar, Homepage Counters, Hero Videos, Gallery Images, and the Breaking News Ticker displayed on the public homepage.</span>
    <a href="<?= esc(base_url('admin/showcase_sections.php')); ?>" class="btn btn-sm btn-outline-secondary ms-3"><i class="fa-solid fa-tv"></i> Also manage: Media &amp; Updates Section &rarr;</a>
</div>

<?php if ($success = get_flash('success')): ?>
    <div class="alert alert-success"><?= esc($success); ?></div>
<?php endif; ?>
<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-danger"><?= esc($error); ?></div>
<?php endif; ?>

<section class="admin-card mb-3">
    <h4>Member Benefits</h4>
    <form method="post" class="row g-3 mt-1">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="action" value="save_benefit">
        <input type="hidden" name="id" value="<?= esc((string)($editBenefit['id'] ?? 0)); ?>">
        <div class="col-md-3">
            <label class="form-label">Icon Class</label>
            <input class="form-control" type="text" name="icon" value="<?= esc((string)($editBenefit['icon'] ?? 'fa-solid fa-star')); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Title</label>
            <input class="form-control" type="text" name="title" required value="<?= esc((string)($editBenefit['title'] ?? '')); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Link</label>
            <input class="form-control" type="text" name="link" value="<?= esc((string)($editBenefit['link'] ?? '#')); ?>">
        </div>
        <div class="col-md-1">
            <label class="form-label">Order</label>
            <input class="form-control" type="number" name="sort_order" value="<?= esc((string)($editBenefit['sort_order'] ?? 0)); ?>">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="benefitActive" <?= !isset($editBenefit['is_active']) || (int)$editBenefit['is_active'] === 1 ? 'checked' : ''; ?>>
                <label class="form-check-label" for="benefitActive">Active</label>
            </div>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit"><?= $editBenefit ? 'Update' : 'Add'; ?></button>
        </div>
    </form>

    <div class="table-responsive mt-3">
        <table class="table table-hover align-middle" data-admin-datatable>
            <thead><tr><th>Order</th><th>Icon</th><th>Title</th><th>Link</th><th>Active</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($benefits as $item): ?>
                <tr>
                    <td><?= esc((string)$item['sort_order']); ?></td>
                    <td><i class="<?= esc((string)$item['icon']); ?>"></i></td>
                    <td><?= esc((string)$item['title']); ?></td>
                    <td><?= esc((string)$item['link']); ?></td>
                    <td><?= (int)$item['is_active'] === 1 ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-info" href="?edit_benefit=<?= (int)$item['id']; ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" data-confirm-delete href="?type=benefit&delete=<?= (int)$item['id']; ?>&token=<?= esc(csrf_token()); ?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card mb-3">
    <h4>Homepage Counters & Ticker Speed</h4>
    <form method="post" class="row g-3 mt-1">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="action" value="save_settings">

        <div class="col-md-3"><label class="form-label">Total Members Label</label><input class="form-control" type="text" name="counter_total_members_label" value="<?= esc($getSetting('counter_total_members_label', 'Total Members')); ?>"></div>
        <div class="col-md-2"><label class="form-label">Mode</label><select class="form-select" name="counter_total_members_mode"><option value="auto" <?= $getSetting('counter_total_members_mode', 'auto') === 'auto' ? 'selected' : ''; ?>>Auto DB</option><option value="manual" <?= $getSetting('counter_total_members_mode', 'auto') === 'manual' ? 'selected' : ''; ?>>Manual</option></select></div>
        <div class="col-md-2"><label class="form-label">Manual Value</label><input class="form-control" type="number" min="0" name="counter_total_members_value" value="<?= esc($getSetting('counter_total_members_value', '0')); ?>"></div>

        <div class="col-md-3"><label class="form-label">Complaints Solved Label</label><input class="form-control" type="text" name="counter_complaints_solved_label" value="<?= esc($getSetting('counter_complaints_solved_label', 'Complaints Solved')); ?>"></div>
        <div class="col-md-2"><label class="form-label">Mode</label><select class="form-select" name="counter_complaints_solved_mode"><option value="auto" <?= $getSetting('counter_complaints_solved_mode', 'auto') === 'auto' ? 'selected' : ''; ?>>Auto DB</option><option value="manual" <?= $getSetting('counter_complaints_solved_mode', 'auto') === 'manual' ? 'selected' : ''; ?>>Manual</option></select></div>
        <div class="col-md-2"><label class="form-label">Manual Value</label><input class="form-control" type="number" min="0" name="counter_complaints_solved_value" value="<?= esc($getSetting('counter_complaints_solved_value', '0')); ?>"></div>

        <div class="col-md-3"><label class="form-label">Districts Active Label</label><input class="form-control" type="text" name="counter_districts_active_label" value="<?= esc($getSetting('counter_districts_active_label', 'Districts Active')); ?>"></div>
        <div class="col-md-2"><label class="form-label">Mode</label><select class="form-select" name="counter_districts_active_mode"><option value="auto" <?= $getSetting('counter_districts_active_mode', 'auto') === 'auto' ? 'selected' : ''; ?>>Auto DB</option><option value="manual" <?= $getSetting('counter_districts_active_mode', 'auto') === 'manual' ? 'selected' : ''; ?>>Manual</option></select></div>
        <div class="col-md-2"><label class="form-label">Manual Value</label><input class="form-control" type="number" min="0" name="counter_districts_active_value" value="<?= esc($getSetting('counter_districts_active_value', '0')); ?>"></div>

        <div class="col-md-3"><label class="form-label">ID Cards Issued Label</label><input class="form-control" type="text" name="counter_id_cards_issued_label" value="<?= esc($getSetting('counter_id_cards_issued_label', 'ID Cards Issued')); ?>"></div>
        <div class="col-md-2"><label class="form-label">Mode</label><select class="form-select" name="counter_id_cards_issued_mode"><option value="auto" <?= $getSetting('counter_id_cards_issued_mode', 'auto') === 'auto' ? 'selected' : ''; ?>>Auto DB</option><option value="manual" <?= $getSetting('counter_id_cards_issued_mode', 'auto') === 'manual' ? 'selected' : ''; ?>>Manual</option></select></div>
        <div class="col-md-2"><label class="form-label">Manual Value</label><input class="form-control" type="number" min="0" name="counter_id_cards_issued_value" value="<?= esc($getSetting('counter_id_cards_issued_value', '0')); ?>"></div>

        <div class="col-md-3"><label class="form-label">News Ticker Speed (Seconds)</label><input class="form-control" type="number" min="10" max="90" name="news_ticker_speed_seconds" value="<?= esc($getSetting('news_ticker_speed_seconds', '28')); ?>"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Save Settings</button></div>
    </form>
</section>

<section class="admin-card mb-3">
    <h4>YouTube Videos</h4>
    <form method="post" class="row g-3 mt-1">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="action" value="save_video">
        <input type="hidden" name="id" value="<?= esc((string)($editVideo['id'] ?? 0)); ?>">
        <div class="col-md-4"><label class="form-label">Title</label><input class="form-control" type="text" name="title" required value="<?= esc((string)($editVideo['title'] ?? '')); ?>"></div>
        <div class="col-md-4"><label class="form-label">YouTube URL</label><input class="form-control" type="text" name="video_url" required value="<?= esc((string)($editVideo['video_url'] ?? '')); ?>"></div>
        <div class="col-md-1"><label class="form-label">Order</label><input class="form-control" type="number" name="sort_order" value="<?= esc((string)($editVideo['sort_order'] ?? 0)); ?>"></div>
        <div class="col-md-1 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" id="videoFeatured" <?= isset($editVideo['is_featured']) && (int)$editVideo['is_featured'] === 1 ? 'checked' : ''; ?>><label class="form-check-label" for="videoFeatured">Featured</label></div></div>
        <div class="col-md-1 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="videoActive" <?= !isset($editVideo['is_active']) || (int)$editVideo['is_active'] === 1 ? 'checked' : ''; ?>><label class="form-check-label" for="videoActive">Active</label></div></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><?= $editVideo ? 'Update' : 'Add'; ?></button></div>
    </form>

    <div class="table-responsive mt-3">
        <table class="table table-hover align-middle" data-admin-datatable>
            <thead><tr><th>Order</th><th>Thumb</th><th>Title</th><th>Featured</th><th>Active</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($videos as $item): ?>
                <tr>
                    <td><?= esc((string)$item['sort_order']); ?></td>
                    <td><img src="https://img.youtube.com/vi/<?= esc((string)$item['video_id']); ?>/mqdefault.jpg" alt="thumb" style="width:120px;height:auto;border-radius:8px;"></td>
                    <td><?= esc((string)$item['title']); ?></td>
                    <td><?= (int)$item['is_featured'] === 1 ? 'Yes' : 'No'; ?></td>
                    <td><?= (int)$item['is_active'] === 1 ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-info" href="?edit_video=<?= (int)$item['id']; ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" data-confirm-delete href="?type=video&delete=<?= (int)$item['id']; ?>&token=<?= esc(csrf_token()); ?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card mb-3">
    <h4>Gallery Images</h4>
    <form method="post" enctype="multipart/form-data" class="row g-3 mt-1">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="action" value="save_gallery">
        <input type="hidden" name="id" value="<?= esc((string)($editGallery['id'] ?? 0)); ?>">
        <div class="col-md-3"><label class="form-label">Title</label><input class="form-control" type="text" name="title" value="<?= esc((string)($editGallery['title'] ?? '')); ?>"></div>
        <div class="col-md-2"><label class="form-label">Category</label><input class="form-control" type="text" name="category" value="<?= esc((string)($editGallery['category'] ?? 'General')); ?>"></div>
        <div class="col-md-2"><label class="form-label">Sort Order</label><input class="form-control" type="number" name="sort_order" value="<?= esc((string)($editGallery['sort_order'] ?? 0)); ?>"></div>
        <div class="col-md-3"><label class="form-label">Image Upload</label><input class="form-control" type="file" name="image_file" accept="image/jpeg,image/png,image/webp" <?= $editGallery ? '' : 'required'; ?>></div>
        <div class="col-md-1 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="galleryActive" <?= !isset($editGallery['is_active']) || (int)$editGallery['is_active'] === 1 ? 'checked' : ''; ?>><label class="form-check-label" for="galleryActive">Active</label></div></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><?= $editGallery ? 'Update' : 'Add'; ?></button></div>
    </form>

    <div class="table-responsive mt-3">
        <table class="table table-hover align-middle" data-admin-datatable>
            <thead><tr><th>Preview</th><th>Title</th><th>Category</th><th>Order</th><th>Active</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($gallery as $item): ?>
                <tr>
                    <td><img src="<?= esc(base_url((string)$item['image_path'])); ?>" alt="gallery" style="width:110px;height:64px;object-fit:cover;border-radius:8px;"></td>
                    <td><?= esc((string)$item['title']); ?></td>
                    <td><?= esc((string)$item['category']); ?></td>
                    <td><?= esc((string)$item['sort_order']); ?></td>
                    <td><?= (int)$item['is_active'] === 1 ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-info" href="?edit_gallery=<?= (int)$item['id']; ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" data-confirm-delete href="?type=gallery&delete=<?= (int)$item['id']; ?>&token=<?= esc(csrf_token()); ?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card">
    <h4>Breaking News</h4>
    <form method="post" class="row g-3 mt-1">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="action" value="save_news">
        <input type="hidden" name="id" value="<?= esc((string)($editNews['id'] ?? 0)); ?>">
        <div class="col-md-5"><label class="form-label">Notice</label><input class="form-control" type="text" name="notice_text" required value="<?= esc((string)($editNews['notice_text'] ?? '')); ?>"></div>
        <div class="col-md-3"><label class="form-label">Link</label><input class="form-control" type="text" name="notice_link" value="<?= esc((string)($editNews['notice_link'] ?? '#')); ?>"></div>
        <div class="col-md-1"><label class="form-label">Order</label><input class="form-control" type="number" name="sort_order" value="<?= esc((string)($editNews['sort_order'] ?? 0)); ?>"></div>
        <div class="col-md-1 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="newsActive" <?= !isset($editNews['is_active']) || (int)$editNews['is_active'] === 1 ? 'checked' : ''; ?>><label class="form-check-label" for="newsActive">Active</label></div></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><?= $editNews ? 'Update' : 'Add'; ?></button></div>
    </form>

    <div class="table-responsive mt-3">
        <table class="table table-hover align-middle" data-admin-datatable>
            <thead><tr><th>Notice</th><th>Link</th><th>Order</th><th>Active</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($newsRows as $item): ?>
                <tr>
                    <td><?= esc((string)$item['notice_text']); ?></td>
                    <td><?= esc((string)$item['notice_link']); ?></td>
                    <td><?= esc((string)$item['sort_order']); ?></td>
                    <td><?= (int)$item['is_active'] === 1 ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-info" href="?edit_news=<?= (int)$item['id']; ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" data-confirm-delete href="?type=news&delete=<?= (int)$item['id']; ?>&token=<?= esc(csrf_token()); ?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/_bottom.php'; ?>