<?php
require_once __DIR__ . '/../db.php';
require_admin();

if (!is_super_admin()) {
    set_flash('error', 'Access denied. Super Administrator only.');
    redirect_to('admin/dashboard.php');
}

execute_query('CREATE TABLE IF NOT EXISTS hero_section (
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
    animation_type VARCHAR(40) DEFAULT "fade",
    overlay_color VARCHAR(10) DEFAULT "#0f1b2e",
    background_image VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

execute_query('ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS badge_text VARCHAR(255) DEFAULT NULL');
execute_query('ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS heading_line VARCHAR(255) DEFAULT NULL');
execute_query('ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS joined_label VARCHAR(160) DEFAULT NULL');
execute_query('ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS growth_text VARCHAR(160) DEFAULT NULL');
execute_query('ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS district_label VARCHAR(100) DEFAULT NULL');
execute_query('ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS issues_label VARCHAR(100) DEFAULT NULL');
execute_query('ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS cards_label VARCHAR(100) DEFAULT NULL');
execute_query('ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0');
execute_query('ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS animation_type VARCHAR(40) DEFAULT "fade"');
execute_query('ALTER TABLE hero_section ADD COLUMN IF NOT EXISTS overlay_color VARCHAR(10) DEFAULT "#0f1b2e"');

$isValidImageUpload = static function (array $file): bool {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return true;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name'] ?? '');
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    return in_array($mime, $allowedMimes, true);
};

$editing = null;

if (isset($_GET['edit'])) {
    $editing = fetch_one('SELECT * FROM hero_section WHERE id = :id', [':id' => (int)$_GET['edit']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        redirect_to('admin/hero.php');
    }

    $action = (string)($_POST['action'] ?? 'save_hero');

    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $targetState = isset($_POST['set_active']) ? 1 : 0;

        if ($id > 0 && $targetState === 1) {
            $activeCount = (int)((fetch_one('SELECT COUNT(*) AS total FROM hero_section WHERE is_active = 1 AND id <> :id', [':id' => $id]) ?? ['total' => 0])['total'] ?? 0);
            if ($activeCount >= 3) {
                set_flash('error', 'Only 3 sliders can be active at the same time.');
                redirect_to('admin/hero.php');
            }
        }

        if ($id > 0) {
            execute_query('UPDATE hero_section SET is_active = :is_active WHERE id = :id', [
                ':is_active' => $targetState,
                ':id' => $id,
            ]);
            set_flash('success', 'Slider status updated.');
        }

        redirect_to('admin/hero.php');
    }

    if ($action === 'update_order') {
        $id = (int)($_POST['id'] ?? 0);
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
        if ($id > 0) {
            execute_query('UPDATE hero_section SET sort_order = :sort_order WHERE id = :id', [
                ':sort_order' => $sortOrder,
                ':id' => $id,
            ]);
            set_flash('success', 'Slider order updated.');
        }
        redirect_to('admin/hero.php');
    }

    if ($action === 'bulk_reorder') {
        $orderedIdsRaw = trim((string)($_POST['ordered_ids'] ?? ''));
        if ($orderedIdsRaw !== '') {
            $orderedIds = array_values(array_filter(array_map(static function ($value) {
                return (int)$value;
            }, explode(',', $orderedIdsRaw)), static function ($id) {
                return $id > 0;
            }));

            if (!empty($orderedIds)) {
                $seen = [];
                $position = 1;
                foreach ($orderedIds as $id) {
                    if (isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                    execute_query('UPDATE hero_section SET sort_order = :sort_order WHERE id = :id', [
                        ':sort_order' => $position,
                        ':id' => $id,
                    ]);
                    $position++;
                }
                set_flash('success', 'Slider order updated successfully.');
            }
        }

        redirect_to('admin/hero.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $title = clean($_POST['title'] ?? '');
    $subtitle = clean($_POST['subtitle'] ?? '');
    $badgeText = clean($_POST['badge_text'] ?? 'APCSNSC - Strength • Unity • Justice');
    $headingLine = clean($_POST['heading_line'] ?? 'Fighting for Equality, Job Security & Dignity');
    $btn1Text = clean($_POST['btn1_text'] ?? 'Join the Union');
    $btn1Link = clean($_POST['btn1_link'] ?? base_url('register.php'));
    $btn2Text = clean($_POST['btn2_text'] ?? 'Submit Issue');
    $btn2Link = clean($_POST['btn2_link'] ?? base_url('pages/contact.php'));
    $joinedLabel = clean($_POST['joined_label'] ?? 'Nurses Already Joined');
    $growthText = clean($_POST['growth_text'] ?? 'Growing Strong Every Day');
    $districtLabel = clean($_POST['district_label'] ?? 'Districts');
    $issuesLabel = clean($_POST['issues_label'] ?? 'Active Issues');
    $cardsLabel = clean($_POST['cards_label'] ?? 'ID Cards Issued');
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
    $animationType = clean($_POST['animation_type'] ?? 'fade');
    $overlayColor = clean($_POST['overlay_color'] ?? '#0f1b2e');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (!in_array($animationType, ['fade', 'slide-up', 'zoom-in'], true)) {
        $animationType = 'fade';
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $overlayColor)) {
        $overlayColor = '#0f1b2e';
    }

    if ($isActive === 1) {
        $params = [];
        $sql = 'SELECT COUNT(*) AS total FROM hero_section WHERE is_active = 1';
        if ($id > 0) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $id;
        }

        $activeCount = (int)((fetch_one($sql, $params) ?? ['total' => 0])['total'] ?? 0);
        if ($activeCount >= 3) {
            set_flash('error', 'Only 3 sliders can be active at the same time.');
            $target = $id > 0 ? 'admin/hero.php?edit=' . $id : 'admin/hero.php';
            redirect_to($target);
        }
    }

    if (!$isValidImageUpload($_FILES['background_image'] ?? [])) {
        set_flash('error', 'Please upload a valid JPG, PNG, or WEBP image.');
        $target = $id > 0 ? 'admin/hero.php?edit=' . $id : 'admin/hero.php';
        redirect_to($target);
    }

    $uploaded = upload_image($_FILES['background_image'] ?? [], 'uploads');

    // If a file was submitted but upload_image returned null, surface an error
    $fileSubmitted = isset($_FILES['background_image']) && (($_FILES['background_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
    if ($fileSubmitted && $uploaded === null) {
        set_flash('error', 'Image upload failed. Ensure file is JPG/PNG/WEBP, under 2MB, and uploads/ is writable on the server.');
        $target = $id > 0 ? 'admin/hero.php?edit=' . $id : 'admin/hero.php';
        redirect_to($target);
    }
    if ($id > 0) {
        $old = fetch_one('SELECT background_image FROM hero_section WHERE id = :id', [':id' => $id]);
        $bg = $uploaded ?: ($old['background_image'] ?? null);

        execute_query('UPDATE hero_section SET title = :title, subtitle = :subtitle, badge_text = :badge_text, heading_line = :heading_line, background_image = :background_image, btn1_text = :btn1_text, btn1_link = :btn1_link, btn2_text = :btn2_text, btn2_link = :btn2_link, joined_label = :joined_label, growth_text = :growth_text, district_label = :district_label, issues_label = :issues_label, cards_label = :cards_label, sort_order = :sort_order, animation_type = :animation_type, overlay_color = :overlay_color, is_active = :is_active WHERE id = :id', [
            ':title' => $title,
            ':subtitle' => $subtitle,
            ':badge_text' => $badgeText,
            ':heading_line' => $headingLine,
            ':background_image' => $bg,
            ':btn1_text' => $btn1Text,
            ':btn1_link' => $btn1Link,
            ':btn2_text' => $btn2Text,
            ':btn2_link' => $btn2Link,
            ':joined_label' => $joinedLabel,
            ':growth_text' => $growthText,
            ':district_label' => $districtLabel,
            ':issues_label' => $issuesLabel,
            ':cards_label' => $cardsLabel,
            ':sort_order' => $sortOrder,
            ':animation_type' => $animationType,
            ':overlay_color' => $overlayColor,
            ':is_active' => $isActive,
            ':id' => $id,
        ]);
    } else {
        execute_query('INSERT INTO hero_section (title, subtitle, badge_text, heading_line, background_image, btn1_text, btn1_link, btn2_text, btn2_link, joined_label, growth_text, district_label, issues_label, cards_label, sort_order, animation_type, overlay_color, is_active) VALUES (:title, :subtitle, :badge_text, :heading_line, :background_image, :btn1_text, :btn1_link, :btn2_text, :btn2_link, :joined_label, :growth_text, :district_label, :issues_label, :cards_label, :sort_order, :animation_type, :overlay_color, :is_active)', [
            ':title' => $title,
            ':subtitle' => $subtitle,
            ':badge_text' => $badgeText,
            ':heading_line' => $headingLine,
            ':background_image' => $uploaded,
            ':btn1_text' => $btn1Text,
            ':btn1_link' => $btn1Link,
            ':btn2_text' => $btn2Text,
            ':btn2_link' => $btn2Link,
            ':joined_label' => $joinedLabel,
            ':growth_text' => $growthText,
            ':district_label' => $districtLabel,
            ':issues_label' => $issuesLabel,
            ':cards_label' => $cardsLabel,
            ':sort_order' => $sortOrder,
            ':animation_type' => $animationType,
            ':overlay_color' => $overlayColor,
            ':is_active' => $isActive,
        ]);
    }

    set_flash('success', 'Hero content saved successfully.');

    redirect_to('admin/hero.php');
}

if (isset($_GET['delete'])) {
    if (!verify_csrf($_GET['token'] ?? null)) {
        redirect_to('admin/hero.php');
    }

    execute_query('DELETE FROM hero_section WHERE id = :id', [':id' => (int)$_GET['delete']]);
    redirect_to('admin/hero.php');
}

$records = fetch_all('SELECT * FROM hero_section ORDER BY is_active DESC, sort_order ASC, created_at DESC, id DESC');
$currentActiveHero = fetch_one('SELECT * FROM hero_section WHERE is_active = 1 LIMIT 1');
$activeCount = (int)((fetch_one('SELECT COUNT(*) AS total FROM hero_section WHERE is_active = 1') ?? ['total' => 0])['total'] ?? 0);

// When there is only one hero record, open it in edit mode by default.
if ($editing === null && count($records) === 1) {
    $editing = $records[0];
}

$singleHero = count($records) === 1;

$success = get_flash('success');
$error = get_flash('error');

$pageTitle = 'Hero Management';
$activeMenu = 'hero';
require_once __DIR__ . '/_top.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= esc($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= esc($error); ?></div>
<?php endif; ?>

<section class="admin-page-head">
    <div class="admin-page-head-copy">
        <h2>Hero Sliders</h2>
        <p>Manage homepage banners with card library controls, order priority, and up to 3 active sliders.</p>
    </div>
    <div class="admin-page-head-actions">
        <button type="button" class="btn btn-primary" id="openSliderModalBtn"><i class="fa-solid fa-plus"></i> Add New Slider</button>
        <span class="admin-count-pill"><?= esc((string)$activeCount); ?>/3 Active</span>
    </div>
</section>

<div class="admin-table-card" style="margin-top: 18px; padding: 16px;">
    <div class="admin-hero-library-head">
        <h3>Slider Library</h3>
        <p>Drag cards to reorder slides. New order is saved automatically.</p>
    </div>

    <div class="admin-slider-card-grid" id="adminSliderGrid">
        <?php foreach ($records as $row): ?>
            <?php
                $image = '';
                $image_url = '';
                if (!empty($row['background_image'])) {
                    $relPath = (string)$row['background_image'];
                    $diskPath = __DIR__ . '/../' . $relPath;
                    $qs = '';
                    if (is_file($diskPath)) {
                        $qs = '?v=' . filemtime($diskPath);
                    } else {
                        $qs = '?v=' . time();
                    }
                    $image = base_url($relPath) . $qs;
                    $image_url = base_url($relPath) . $qs;
                }
                $isActiveRow = (int)$row['is_active'] === 1;
                ?>
            <article class="admin-slider-card" data-slider-id="<?= (int)$row['id']; ?>">
                <div class="admin-slider-card-image-wrap">
                    <span class="admin-slider-drag-handle" title="Drag to reorder" aria-label="Drag slider"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>
                    <?php if ($image !== ''): ?>
                        <img src="<?= esc($image); ?>" alt="<?= esc((string)$row['title']); ?>">
                    <?php else: ?>
                        <div class="admin-slider-card-image-fallback">No Image</div>
                    <?php endif; ?>
                </div>

                <div class="admin-slider-card-body">
                    <div class="admin-slider-card-top">
                        <h4><?= esc((string)$row['title']); ?></h4>
                        <span class="admin-slider-order-pill">Sort #<?= esc((string)($row['sort_order'] ?? 0)); ?></span>
                    </div>
                    <p><?= esc((string)($row['subtitle'] ?? '')); ?></p>

                    <div class="admin-slider-card-meta">
                        <span><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><?= esc((string)($row['animation_type'] ?? 'fade')); ?></span>
                        <span><i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i><?= esc((string)($row['overlay_color'] ?? '#0f1b2e')); ?></span>
                    </div>

                    <div class="admin-slider-card-actions">
                        <button type="button" class="btn btn-outline btn-slider-edit" data-slider='<?= esc(json_encode([
                            'id' => (int)$row['id'],
                            'title' => (string)($row['title'] ?? ''),
                            'subtitle' => (string)($row['subtitle'] ?? ''),
                            'badge_text' => (string)($row['badge_text'] ?? 'APCSNSC - Strength • Unity • Justice'),
                            'heading_line' => (string)($row['heading_line'] ?? 'Fighting for Equality, Job Security & Dignity'),
                            'btn1_text' => (string)($row['btn1_text'] ?? 'Join the Union'),
                            'btn1_link' => (string)($row['btn1_link'] ?? base_url('register.php')),
                            'btn2_text' => (string)($row['btn2_text'] ?? 'Submit Issue'),
                            'btn2_link' => (string)($row['btn2_link'] ?? base_url('pages/contact.php')),
                            'joined_label' => (string)($row['joined_label'] ?? 'Nurses Already Joined'),
                            'growth_text' => (string)($row['growth_text'] ?? 'Growing Strong Every Day'),
                            'district_label' => (string)($row['district_label'] ?? 'Districts'),
                            'issues_label' => (string)($row['issues_label'] ?? 'Active Issues'),
                            'cards_label' => (string)($row['cards_label'] ?? 'ID Cards Issued'),
                            'sort_order' => (int)($row['sort_order'] ?? 0),
                            'animation_type' => (string)($row['animation_type'] ?? 'fade'),
                            'overlay_color' => (string)($row['overlay_color'] ?? '#0f1b2e'),
                            'background_image' => (string)($row['background_image'] ?? ''),
                            'image_url' => $image_url,
                            'is_active' => (int)$row['is_active'],
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>'><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Edit</button>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?= (int)$row['id']; ?>">
                            <?php if ($isActiveRow): ?>
                                <button class="btn btn-outline" type="submit"><i class="fa-regular fa-circle-xmark" aria-hidden="true"></i>Deactivate</button>
                            <?php else: ?>
                                <input type="hidden" name="set_active" value="1">
                                <button class="btn btn-primary" type="submit"><i class="fa-regular fa-circle-check" aria-hidden="true"></i>Activate</button>
                            <?php endif; ?>
                        </form>

                        <a class="btn btn-outline" href="?delete=<?= (int)$row['id']; ?>&token=<?= esc(csrf_token()); ?>" onclick="return confirm('Delete this slider?')"><i class="fa-regular fa-trash-can" aria-hidden="true"></i>Delete</a>
                    </div>

                    <span class="admin-slider-status-badge <?= $isActiveRow ? 'is-active' : 'is-inactive'; ?>"><?= $isActiveRow ? 'Active' : 'Inactive'; ?></span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <form id="sliderReorderForm" method="post" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="action" value="bulk_reorder">
        <input type="hidden" name="ordered_ids" id="orderedSliderIds" value="">
    </form>
</div>

<div class="admin-slider-modal" id="sliderModal" aria-hidden="true">
    <div class="admin-slider-modal-backdrop" data-modal-close></div>
    <div class="admin-slider-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="sliderModalTitle">
        <button type="button" class="admin-slider-modal-close" data-modal-close aria-label="Close modal">&times;</button>
        <div class="admin-slider-modal-head">
            <div>
                <p class="admin-slider-modal-kicker">Slider Editor</p>
                <h3 id="sliderModalTitle">Add New Slider</h3>
                <p>Use the popup to create or edit hero content without leaving the library view.</p>
            </div>
            <span class="admin-hero-chip admin-hero-chip-soft">Awayindia style</span>
        </div>

        <form method="post" enctype="multipart/form-data" class="admin-hero-form admin-slider-modal-form" id="sliderModalForm">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
            <input type="hidden" name="action" value="save_hero">
            <input type="hidden" name="id" id="sliderModalId" value="0">

            <div class="admin-hero-section">
                <h4>Headline</h4>
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Title</label>
                        <input type="text" name="title" id="sliderModalTitleField" required>
                    </div>
                    <div class="form-group full">
                        <label>Badge Text</label>
                        <input type="text" name="badge_text" id="sliderModalBadgeText" value="APCSNSC - Strength • Unity • Justice">
                    </div>
                    <div class="form-group full">
                        <label>Subtitle</label>
                        <textarea name="subtitle" id="sliderModalSubtitle" required></textarea>
                    </div>
                    <div class="form-group full">
                        <label>Second Heading Line</label>
                        <input type="text" name="heading_line" id="sliderModalHeadingLine" value="Fighting for Equality, Job Security & Dignity">
                    </div>
                </div>
            </div>

            <div class="admin-hero-section">
                <h4>Actions</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Button 1 Text</label>
                        <input type="text" name="btn1_text" id="sliderModalBtn1Text" value="Join the Union">
                    </div>
                    <div class="form-group">
                        <label>Button 1 Link</label>
                        <input type="text" name="btn1_link" id="sliderModalBtn1Link" value="<?= esc(base_url('register.php')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Button 2 Text</label>
                        <input type="text" name="btn2_text" id="sliderModalBtn2Text" value="Submit Issue">
                    </div>
                    <div class="form-group">
                        <label>Button 2 Link</label>
                        <input type="text" name="btn2_link" id="sliderModalBtn2Link" value="<?= esc(base_url('pages/contact.php')); ?>">
                    </div>
                </div>
            </div>

            <div class="admin-hero-section">
                <h4>Slider Behavior</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" min="0" name="sort_order" id="sliderModalSortOrder" value="0">
                    </div>
                    <div class="form-group">
                        <label>Animation Type</label>
                        <select name="animation_type" id="sliderModalAnimationType">
                            <option value="fade">Fade</option>
                            <option value="slide-up">Slide Up</option>
                            <option value="zoom-in">Zoom In</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Overlay Color</label>
                        <input type="color" name="overlay_color" id="sliderModalOverlayColor" value="#0f1b2e">
                    </div>
                </div>
            </div>

            <div class="admin-hero-section">
                <h4>Hero Image</h4>
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Background Image</label>
                        <input type="file" name="background_image" id="sliderModalImageInput" accept="image/jpeg,image/png,image/webp">
                        <small class="muted">Allowed formats: JPG, PNG, WEBP</small>
                    </div>
                    <div class="form-group full">
                        <img class="hero-preview-image" id="sliderModalImagePreview" src="" alt="Selected image preview" style="display:none;">
                    </div>
                </div>
            </div>

            <div class="admin-hero-section">
                <h4>Hero Labels</h4>
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Trust Joined Label</label>
                        <input type="text" name="joined_label" id="sliderModalJoinedLabel" value="Nurses Already Joined">
                    </div>
                    <div class="form-group full">
                        <label>Trust Growth Text</label>
                        <input type="text" name="growth_text" id="sliderModalGrowthText" value="Growing Strong Every Day">
                    </div>
                    <div class="form-group">
                        <label>Right Card: District Label</label>
                        <input type="text" name="district_label" id="sliderModalDistrictLabel" value="Districts">
                    </div>
                    <div class="form-group">
                        <label>Right Card: Active Issues Label</label>
                        <input type="text" name="issues_label" id="sliderModalIssuesLabel" value="Active Issues">
                    </div>
                    <div class="form-group full">
                        <label>Right Card: ID Cards Label</label>
                        <input type="text" name="cards_label" id="sliderModalCardsLabel" value="ID Cards Issued">
                    </div>
                </div>
            </div>

            <div class="admin-hero-section admin-hero-section-actions">
                <div class="form-group full">
                    <label><input type="checkbox" name="is_active" id="sliderModalIsActive"> Keep this slider active (max 3)</label>
                </div>
                <div class="form-group full">
                    <div class="admin-hero-actions">
                        <button class="btn btn-primary" type="submit" id="sliderModalSubmitBtn">Save Slider</button>
                        <button class="btn btn-outline" type="button" data-modal-close>Cancel</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
    (function () {
        const imageInput = document.getElementById('heroImageInput');
        const preview = document.getElementById('heroImagePreview');
        const form = document.getElementById('heroForm');
        const sliderGrid = document.getElementById('adminSliderGrid');
        const reorderForm = document.getElementById('sliderReorderForm');
        const orderedSliderIds = document.getElementById('orderedSliderIds');
        const openSliderModalBtn = document.getElementById('openSliderModalBtn');
        const sliderModal = document.getElementById('sliderModal');
        const sliderModalForm = document.getElementById('sliderModalForm');
        const sliderModalTitle = document.getElementById('sliderModalTitle');
        const sliderModalId = document.getElementById('sliderModalId');
        const sliderModalSubmitBtn = document.getElementById('sliderModalSubmitBtn');
        const sliderModalImageInput = document.getElementById('sliderModalImageInput');
        const sliderModalImagePreview = document.getElementById('sliderModalImagePreview');
        const sliderModalFields = {
            title: document.getElementById('sliderModalTitleField'),
            badge_text: document.getElementById('sliderModalBadgeText'),
            subtitle: document.getElementById('sliderModalSubtitle'),
            heading_line: document.getElementById('sliderModalHeadingLine'),
            btn1_text: document.getElementById('sliderModalBtn1Text'),
            btn1_link: document.getElementById('sliderModalBtn1Link'),
            btn2_text: document.getElementById('sliderModalBtn2Text'),
            btn2_link: document.getElementById('sliderModalBtn2Link'),
            sort_order: document.getElementById('sliderModalSortOrder'),
            animation_type: document.getElementById('sliderModalAnimationType'),
            overlay_color: document.getElementById('sliderModalOverlayColor'),
            joined_label: document.getElementById('sliderModalJoinedLabel'),
            growth_text: document.getElementById('sliderModalGrowthText'),
            district_label: document.getElementById('sliderModalDistrictLabel'),
            issues_label: document.getElementById('sliderModalIssuesLabel'),
            cards_label: document.getElementById('sliderModalCardsLabel'),
            is_active: document.getElementById('sliderModalIsActive')
        };
        const previewMap = {
            title: document.getElementById('previewTitle'),
            badge_text: document.getElementById('previewBadgeText'),
            subtitle: document.getElementById('previewSubtitle'),
            heading_line: document.getElementById('previewHeading'),
            btn1_text: document.getElementById('previewBtn1'),
            btn2_text: document.getElementById('previewBtn2'),
            joined_label: document.getElementById('previewJoinedLabel'),
            growth_text: document.getElementById('previewGrowthText'),
            district_label: document.getElementById('previewDistrictLabel'),
            issues_label: document.getElementById('previewIssuesLabel'),
            cards_label: document.getElementById('previewCardsLabel')
        };

        if (sliderGrid && reorderForm && orderedSliderIds && typeof Sortable !== 'undefined') {
            Sortable.create(sliderGrid, {
                animation: 180,
                handle: '.admin-slider-drag-handle',
                draggable: '.admin-slider-card',
                onEnd: function () {
                    const ids = Array.from(sliderGrid.querySelectorAll('.admin-slider-card'))
                        .map(function (card) {
                            return card.getAttribute('data-slider-id') || '';
                        })
                        .filter(function (id) {
                            return id !== '';
                        });

                    if (!ids.length) {
                        return;
                    }

                    orderedSliderIds.value = ids.join(',');
                    reorderForm.submit();
                }
            });
        }

        const showSliderModal = function () {
            if (!sliderModal) {
                return;
            }
            sliderModal.classList.add('is-open');
            sliderModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        };

        const closeSliderModal = function () {
            if (!sliderModal) {
                return;
            }
            sliderModal.classList.remove('is-open');
            sliderModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        };

        const resetSliderModal = function () {
            if (!sliderModalForm) {
                return;
            }

            sliderModalId.value = '0';
            sliderModalTitle.textContent = 'Add New Slider';
            sliderModalSubmitBtn.textContent = 'Save Slider';
            sliderModalForm.reset();
            sliderModalId.value = '0';
            if (sliderModalFields.overlay_color) {
                sliderModalFields.overlay_color.value = '#0f1b2e';
            }
            if (sliderModalFields.animation_type) {
                sliderModalFields.animation_type.value = 'fade';
            }
            if (sliderModalFields.is_active) {
                sliderModalFields.is_active.checked = true;
            }
            if (sliderModalImagePreview) {
                sliderModalImagePreview.src = '';
                sliderModalImagePreview.style.display = 'none';
            }
        };

        const fillSliderModal = function (slider) {
            if (!slider) {
                return;
            }

            sliderModalId.value = String(slider.id || '0');
            sliderModalTitle.textContent = 'Edit Slider';
            sliderModalSubmitBtn.textContent = 'Update Slider';

            Object.keys(sliderModalFields).forEach(function (key) {
                const field = sliderModalFields[key];
                if (!field) {
                    return;
                }
                if (key === 'is_active') {
                    field.checked = String(slider[key] || '0') === '1';
                    return;
                }
                if (key === 'overlay_color') {
                    field.value = slider[key] || '#0f1b2e';
                    return;
                }
                if (key === 'animation_type') {
                    field.value = slider[key] || 'fade';
                    return;
                }
                field.value = slider[key] || '';
            });

            if ((slider.image_url || slider.background_image) && sliderModalImagePreview) {
                sliderModalImagePreview.src = slider.image_url || slider.background_image;
                sliderModalImagePreview.style.display = sliderModalImagePreview.src ? 'block' : 'none';
            } else if (sliderModalImagePreview) {
                sliderModalImagePreview.src = '';
                sliderModalImagePreview.style.display = 'none';
            }
        };

        if (openSliderModalBtn && sliderModal) {
            openSliderModalBtn.addEventListener('click', function () {
                resetSliderModal();
                showSliderModal();
            });
        }

        document.querySelectorAll('[data-modal-close]').forEach(function (el) {
            el.addEventListener('click', closeSliderModal);
        });

        document.querySelectorAll('.btn-slider-edit').forEach(function (button) {
            button.addEventListener('click', function () {
                const raw = button.getAttribute('data-slider') || '{}';
                let slider = {};
                try {
                    slider = JSON.parse(raw);
                } catch (error) {
                    slider = {};
                }

                fillSliderModal(slider);
                showSliderModal();
            });
        });

        if (sliderModalImageInput && sliderModalImagePreview) {
            sliderModalImageInput.addEventListener('change', function () {
                const file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) {
                    sliderModalImagePreview.src = '';
                    sliderModalImagePreview.style.display = 'none';
                    return;
                }

                const allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if (!allowed.includes(file.type)) {
                    alert('Please choose a JPG, PNG, or WEBP image.');
                    this.value = '';
                    sliderModalImagePreview.src = '';
                    sliderModalImagePreview.style.display = 'none';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    sliderModalImagePreview.src = event.target && event.target.result ? String(event.target.result) : '';
                    sliderModalImagePreview.style.display = sliderModalImagePreview.src ? 'block' : 'none';
                };
                reader.readAsDataURL(file);
            });
        }

        if (sliderModal) {
            sliderModal.addEventListener('click', function (event) {
                if (event.target === sliderModal) {
                    closeSliderModal();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSliderModal();
            }
        });

        if (!imageInput || !preview || !form) {
            return;
        }

        const updatePreview = function () {
            Object.keys(previewMap).forEach(function (name) {
                const el = previewMap[name];
                const field = form.querySelector('[name="' + name + '"]');
                if (!el || !field) {
                    return;
                }

                const value = field.value.trim() || el.textContent;
                el.textContent = value;
            });
        };

        form.addEventListener('input', updatePreview);
        updatePreview();

        imageInput.addEventListener('change', function () {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) {
                preview.src = '';
                preview.style.display = 'none';
                return;
            }

            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                alert('Please choose a JPG, PNG, or WEBP image.');
                this.value = '';
                preview.src = '';
                preview.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                preview.src = event.target && event.target.result ? String(event.target.result) : '';
                preview.style.display = preview.src ? 'block' : 'none';
            };
            reader.readAsDataURL(file);
        });
    })();
</script>

<?php require_once __DIR__ . '/_bottom.php'; ?>
