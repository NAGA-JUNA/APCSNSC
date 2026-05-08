<?php
require_once __DIR__ . '/../db.php';
require_admin();
if (!is_super_admin()) {
    set_flash('error', 'Access denied. Super Administrator only.');
    redirect_to('admin/dashboard.php');
}

$ensureTemplateTables = static function (): void {
    execute_query('CREATE TABLE IF NOT EXISTS card_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        theme_name VARCHAR(80) NOT NULL DEFAULT "default",
        front_template VARCHAR(255) DEFAULT NULL,
        back_template VARCHAR(255) DEFAULT NULL,
        watermark_image VARCHAR(255) DEFAULT NULL,
        hologram_overlay VARCHAR(255) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_card_template_theme (theme_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    execute_query('CREATE TABLE IF NOT EXISTS card_template_positions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        theme_name VARCHAR(80) NOT NULL DEFAULT "default",
        field_name VARCHAR(80) NOT NULL,
        side ENUM("front","back") NOT NULL,
        pos_x INT NOT NULL DEFAULT 20,
        pos_y INT NOT NULL DEFAULT 20,
        font_size INT NOT NULL DEFAULT 12,
        color VARCHAR(20) NOT NULL DEFAULT "#1b2f44",
        align ENUM("left","center","right") NOT NULL DEFAULT "left",
        width INT NOT NULL DEFAULT 120,
        height INT NOT NULL DEFAULT 24,
        font_weight VARCHAR(10) NOT NULL DEFAULT "600",
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_template_field (theme_name, field_name),
        KEY idx_template_side (theme_name, side)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $enabledColumn = fetch_one(
        'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "card_template_positions" AND COLUMN_NAME = "is_enabled"'
    );
    if ((int)($enabledColumn['total'] ?? 0) === 0) {
        execute_query('ALTER TABLE card_template_positions ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER font_weight');
    }
};

$defaultFields = [
    ['name' => 'photo', 'side' => 'front', 'x' => 118, 'y' => 126, 'size' => 12, 'color' => '#1b2f44', 'align' => 'center', 'width' => 102, 'height' => 126, 'weight' => '600'],
    ['name' => 'full_name', 'side' => 'front', 'x' => 55, 'y' => 258, 'size' => 20, 'color' => '#1f4f42', 'align' => 'center', 'width' => 230, 'height' => 38, 'weight' => '800'],
    ['name' => 'member_id', 'side' => 'front', 'x' => 82, 'y' => 300, 'size' => 12, 'color' => '#ffffff', 'align' => 'center', 'width' => 176, 'height' => 28, 'weight' => '700'],
    ['name' => 'district', 'side' => 'front', 'x' => 26, 'y' => 350, 'size' => 11, 'color' => '#1f2932', 'align' => 'left', 'width' => 136, 'height' => 28, 'weight' => '600'],
    ['name' => 'hospital', 'side' => 'front', 'x' => 178, 'y' => 350, 'size' => 11, 'color' => '#1f2932', 'align' => 'left', 'width' => 136, 'height' => 28, 'weight' => '600'],
    ['name' => 'role', 'side' => 'front', 'x' => 26, 'y' => 386, 'size' => 10, 'color' => '#1f2932', 'align' => 'left', 'width' => 136, 'height' => 28, 'weight' => '600'],
    ['name' => 'join_date', 'side' => 'front', 'x' => 178, 'y' => 386, 'size' => 10, 'color' => '#1f2932', 'align' => 'left', 'width' => 136, 'height' => 28, 'weight' => '600'],
    ['name' => 'valid_till', 'side' => 'front', 'x' => 178, 'y' => 422, 'size' => 10, 'color' => '#1f2932', 'align' => 'left', 'width' => 136, 'height' => 28, 'weight' => '600'],
    ['name' => 'qr_code', 'side' => 'back', 'x' => 104, 'y' => 135, 'size' => 12, 'color' => '#1b2f44', 'align' => 'center', 'width' => 130, 'height' => 130, 'weight' => '600'],
    ['name' => 'website', 'side' => 'back', 'x' => 44, 'y' => 290, 'size' => 10, 'color' => '#22323d', 'align' => 'left', 'width' => 250, 'height' => 20, 'weight' => '600'],
    ['name' => 'helpline', 'side' => 'back', 'x' => 44, 'y' => 315, 'size' => 10, 'color' => '#22323d', 'align' => 'left', 'width' => 250, 'height' => 20, 'weight' => '600'],
    ['name' => 'email', 'side' => 'back', 'x' => 44, 'y' => 340, 'size' => 10, 'color' => '#22323d', 'align' => 'left', 'width' => 250, 'height' => 20, 'weight' => '600'],
    ['name' => 'signature', 'side' => 'back', 'x' => 112, 'y' => 438, 'size' => 12, 'color' => '#1b2f44', 'align' => 'center', 'width' => 120, 'height' => 26, 'weight' => '600'],
    ['name' => 'serial_number', 'side' => 'back', 'x' => 104, 'y' => 515, 'size' => 10, 'color' => '#f2e6c4', 'align' => 'center', 'width' => 130, 'height' => 20, 'weight' => '700'],
];

$ensureTemplateTables();

$ensureDefaultTheme = static function (array $defaultFields): void {
    $theme = fetch_one('SELECT id, theme_name FROM card_templates WHERE theme_name = :name LIMIT 1', [':name' => 'default']);
    if (!$theme) {
        execute_query('INSERT INTO card_templates (theme_name, front_template, back_template, is_active) VALUES (:theme, :front, :back, 1)', [
            ':theme' => 'default',
            ':front' => 'uploads/templates/front-template.png',
            ':back' => 'uploads/templates/back-template.png',
        ]);
    }

    foreach ($defaultFields as $field) {
        execute_query('INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight, is_enabled)
            VALUES (:theme, :field, :side, :x, :y, :size, :color, :align, :width, :height, :weight, 1)
            ON DUPLICATE KEY UPDATE side = VALUES(side)', [
            ':theme' => 'default',
            ':field' => $field['name'],
            ':side' => $field['side'],
            ':x' => $field['x'],
            ':y' => $field['y'],
            ':size' => $field['size'],
            ':color' => $field['color'],
            ':align' => $field['align'],
            ':width' => $field['width'],
            ':height' => $field['height'],
            ':weight' => $field['weight'],
        ]);
    }
};

$ensureDefaultTheme($defaultFields);

$uploadTemplateFile = static function (array $file, string $themeName, string $slot): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }

    if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        return null;
    }

    $ext = $allowed[$mime];
    $safeTheme = preg_replace('/[^A-Za-z0-9_-]/', '-', strtolower($themeName)) ?: 'default';
    $fileName = $safeTheme . '-' . $slot . '-' . date('YmdHis') . '.' . $ext;

    $dir = __DIR__ . '/../uploads/templates';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $target = $dir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return null;
    }

    return 'uploads/templates/' . $fileName;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid request token.');
        redirect_to('admin/card_templates.php');
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $themeName = clean((string)($_POST['theme_name'] ?? 'default'));
    if ($themeName === '') {
        $themeName = 'default';
    }

    if ($action === 'save_templates') {
        execute_query('INSERT INTO card_templates (theme_name, is_active) VALUES (:theme, 0) ON DUPLICATE KEY UPDATE theme_name = VALUES(theme_name)', [':theme' => $themeName]);

        $existing = fetch_one('SELECT * FROM card_templates WHERE theme_name = :theme LIMIT 1', [':theme' => $themeName]);
        $front = $existing['front_template'] ?? null;
        $back = $existing['back_template'] ?? null;
        $watermark = $existing['watermark_image'] ?? null;
        $hologram = $existing['hologram_overlay'] ?? null;

        $uploadedFront = $uploadTemplateFile($_FILES['front_template'] ?? [], $themeName, 'front-template');
        $uploadedBack = $uploadTemplateFile($_FILES['back_template'] ?? [], $themeName, 'back-template');
        $uploadedWatermark = $uploadTemplateFile($_FILES['watermark_image'] ?? [], $themeName, 'watermark');
        $uploadedHologram = $uploadTemplateFile($_FILES['hologram_overlay'] ?? [], $themeName, 'hologram');

        if ($uploadedFront !== null) {
            $front = $uploadedFront;
        }
        if ($uploadedBack !== null) {
            $back = $uploadedBack;
        }
        if ($uploadedWatermark !== null) {
            $watermark = $uploadedWatermark;
        }
        if ($uploadedHologram !== null) {
            $hologram = $uploadedHologram;
        }

        execute_query('UPDATE card_templates
            SET front_template = :front,
                back_template = :back,
                watermark_image = :watermark,
                hologram_overlay = :hologram,
                is_active = :active
            WHERE theme_name = :theme', [
            ':front' => $front,
            ':back' => $back,
            ':watermark' => $watermark,
            ':hologram' => $hologram,
            ':active' => isset($_POST['is_active']) ? 1 : 0,
            ':theme' => $themeName,
        ]);

        if (isset($_POST['is_active'])) {
            execute_query('UPDATE card_templates SET is_active = CASE WHEN theme_name = :theme THEN 1 ELSE 0 END', [':theme' => $themeName]);
        }

        set_flash('success', 'Template images saved successfully.');
        redirect_to('admin/card_templates.php?theme=' . urlencode($themeName));
    }

    if ($action === 'save_positions') {
        $fieldNames = $_POST['field_name'] ?? [];
        $sides = $_POST['side'] ?? [];
        $posX = $_POST['pos_x'] ?? [];
        $posY = $_POST['pos_y'] ?? [];
        $fontSizes = $_POST['font_size'] ?? [];
        $colors = $_POST['color'] ?? [];
        $aligns = $_POST['align'] ?? [];
        $widths = $_POST['width'] ?? [];
        $heights = $_POST['height'] ?? [];
        $weights = $_POST['font_weight'] ?? [];
        $enabledFlags = $_POST['is_enabled'] ?? [];

        $count = is_array($fieldNames) ? count($fieldNames) : 0;
        for ($i = 0; $i < $count; $i++) {
            $field = clean((string)($fieldNames[$i] ?? ''));
            $side = clean((string)($sides[$i] ?? 'front'));
            if ($field === '' || !in_array($side, ['front', 'back'], true)) {
                continue;
            }

            $align = clean((string)($aligns[$i] ?? 'left'));
            if (!in_array($align, ['left', 'center', 'right'], true)) {
                $align = 'left';
            }

            $weight = clean((string)($weights[$i] ?? '600'));
            if (!in_array($weight, ['500', '600', '700', '800'], true)) {
                $weight = '600';
            }

            $enabled = (int)($enabledFlags[$i] ?? 1) === 0 ? 0 : 1;

            $color = clean((string)($colors[$i] ?? '#1b2f44'));
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $color = '#1b2f44';
            }

            execute_query('INSERT INTO card_template_positions
                (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight, is_enabled)
                VALUES
                (:theme, :field, :side, :x, :y, :size, :color, :align, :width, :height, :weight, :enabled)
                ON DUPLICATE KEY UPDATE
                side = VALUES(side),
                pos_x = VALUES(pos_x),
                pos_y = VALUES(pos_y),
                font_size = VALUES(font_size),
                color = VALUES(color),
                align = VALUES(align),
                width = VALUES(width),
                height = VALUES(height),
                font_weight = VALUES(font_weight),
                is_enabled = VALUES(is_enabled)', [
                ':theme' => $themeName,
                ':field' => $field,
                ':side' => $side,
                ':x' => max(0, (int)($posX[$i] ?? 0)),
                ':y' => max(0, (int)($posY[$i] ?? 0)),
                ':size' => max(8, (int)($fontSizes[$i] ?? 12)),
                ':color' => $color,
                ':align' => $align,
                ':width' => max(10, (int)($widths[$i] ?? 120)),
                ':height' => max(10, (int)($heights[$i] ?? 24)),
                ':weight' => $weight,
                ':enabled' => $enabled,
            ]);
        }

        set_flash('success', 'Field positions saved successfully.');
        redirect_to('admin/card_templates.php?theme=' . urlencode($themeName));
    }
}

$themes = fetch_all('SELECT * FROM card_templates ORDER BY updated_at DESC, theme_name ASC');
$themeName = clean((string)($_GET['theme'] ?? ''));
if ($themeName === '' && !empty($themes)) {
    $themeName = (string)$themes[0]['theme_name'];
}
if ($themeName === '') {
    $themeName = 'default';
}

$currentTemplate = fetch_one('SELECT * FROM card_templates WHERE theme_name = :theme LIMIT 1', [':theme' => $themeName]);
if (!$currentTemplate) {
    $currentTemplate = fetch_one('SELECT * FROM card_templates WHERE theme_name = :theme LIMIT 1', [':theme' => 'default']) ?: [
        'theme_name' => 'default',
        'front_template' => 'uploads/templates/front-template.png',
        'back_template' => 'uploads/templates/back-template.png',
        'watermark_image' => null,
        'hologram_overlay' => null,
        'is_active' => 1,
    ];
}

$positionsRows = fetch_all('SELECT * FROM card_template_positions WHERE theme_name = :theme', [':theme' => (string)$currentTemplate['theme_name']]);
$positions = [];
foreach ($positionsRows as $row) {
    $positions[(string)$row['field_name']] = [
        'side' => (string)$row['side'],
        'pos_x' => (int)$row['pos_x'],
        'pos_y' => (int)$row['pos_y'],
        'font_size' => (int)$row['font_size'],
        'color' => (string)$row['color'],
        'align' => (string)$row['align'],
        'width' => (int)$row['width'],
        'height' => (int)$row['height'],
        'font_weight' => (string)$row['font_weight'],
        'is_enabled' => isset($row['is_enabled']) ? (int)$row['is_enabled'] : 1,
    ];
}

foreach ($defaultFields as $field) {
    if (!isset($positions[$field['name']])) {
        $positions[$field['name']] = [
            'side' => $field['side'],
            'pos_x' => $field['x'],
            'pos_y' => $field['y'],
            'font_size' => $field['size'],
            'color' => $field['color'],
            'align' => $field['align'],
            'width' => $field['width'],
            'height' => $field['height'],
            'font_weight' => $field['weight'],
            'is_enabled' => 1,
        ];
    }
}

$pageTitle = 'Card Templates';
$activeMenu = 'card-templates';
$hideAdminPageTitle = true;
require_once __DIR__ . '/_top.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/template-card.css?v=' . (string)filemtime(__DIR__ . '/../assets/css/template-card.css'))); ?>">
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<section class="template-page" id="templateCardManager">
    <div class="template-toolbar">
        <div>
            <div class="id-card-kicker">Template Management Studio</div>
            <h3>Card Template & Position Settings</h3>
            <p>Upload front/back templates and control exact print positions for each dynamic field.</p>
        </div>
    </div>

    <?php if ($msg = get_flash('success')): ?>
        <div class="alert alert-success"><?= esc($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = get_flash('error')): ?>
        <div class="alert alert-danger"><?= esc($msg); ?></div>
    <?php endif; ?>

    <div class="template-grid">
        <aside class="template-side-panel">
            <form class="template-box" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_templates">
                <h5>Template Upload</h5>

                <div class="mb-2">
                    <label class="form-label">Theme Name</label>
                    <input class="form-control" type="text" name="theme_name" value="<?= esc((string)$currentTemplate['theme_name']); ?>" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Front Template (PNG/JPG)</label>
                    <input class="form-control" type="file" name="front_template" accept="image/png,image/jpeg,image/webp">
                </div>

                <div class="mb-2">
                    <label class="form-label">Back Template (PNG/JPG)</label>
                    <input class="form-control" type="file" name="back_template" accept="image/png,image/jpeg,image/webp">
                </div>

                <div class="mb-2">
                    <label class="form-label">Watermark Overlay (optional)</label>
                    <input class="form-control" type="file" name="watermark_image" accept="image/png,image/jpeg,image/webp">
                </div>

                <div class="mb-2">
                    <label class="form-label">Hologram Overlay (optional)</label>
                    <input class="form-control" type="file" name="hologram_overlay" accept="image/png,image/jpeg,image/webp">
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" id="isActiveTheme" <?= (int)($currentTemplate['is_active'] ?? 0) === 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="isActiveTheme">Set as active template</label>
                </div>

                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save Template</button>
            </form>

            <div class="template-box">
                <h5>Available Themes</h5>
                <div class="list-group">
                    <?php foreach ($themes as $theme): ?>
                        <a class="list-group-item list-group-item-action <?= (string)$theme['theme_name'] === (string)$currentTemplate['theme_name'] ? 'active' : ''; ?>" href="<?= esc(base_url('admin/card_templates.php?theme=' . urlencode((string)$theme['theme_name']))); ?>">
                            <?= esc((string)$theme['theme_name']); ?>
                            <?php if ((int)$theme['is_active'] === 1): ?><span class="badge bg-success ms-2">Active</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <section class="template-preview-shell">
            <form id="positionsForm" method="post">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_positions">
                <input type="hidden" name="theme_name" value="<?= esc((string)$currentTemplate['theme_name']); ?>">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Live Position Studio (Drag & Drop)</h5>
                    <button type="submit" class="btn btn-primary" id="savePositionsBtn"><i class="fa-solid fa-save me-1"></i>Save Positions</button>
                </div>

                <div class="template-preview-grid mb-3">
                    <div class="id-template-card-wrap">
                        <div class="id-template-card" id="templateFrontCanvas">
                            <div class="id-template-bg"><img src="<?= esc(base_url((string)($currentTemplate['front_template'] ?? 'uploads/templates/front-template.png'))); ?>" alt="Front template"></div>
                            <?php if (!empty($currentTemplate['watermark_image'])): ?>
                                <div class="id-template-watermark"><img src="<?= esc(base_url((string)$currentTemplate['watermark_image'])); ?>" alt="Watermark"></div>
                            <?php endif; ?>
                            <?php if (!empty($currentTemplate['hologram_overlay'])): ?>
                                <div class="id-template-hologram"><img src="<?= esc(base_url((string)$currentTemplate['hologram_overlay'])); ?>" alt="Hologram"></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="id-template-card-wrap">
                        <div class="id-template-card" id="templateBackCanvas">
                            <div class="id-template-bg"><img src="<?= esc(base_url((string)($currentTemplate['back_template'] ?? 'uploads/templates/back-template.png'))); ?>" alt="Back template"></div>
                            <?php if (!empty($currentTemplate['watermark_image'])): ?>
                                <div class="id-template-watermark"><img src="<?= esc(base_url((string)$currentTemplate['watermark_image'])); ?>" alt="Watermark"></div>
                            <?php endif; ?>
                            <?php if (!empty($currentTemplate['hologram_overlay'])): ?>
                                <div class="id-template-hologram"><img src="<?= esc(base_url((string)$currentTemplate['hologram_overlay'])); ?>" alt="Hologram"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="max-height: 420px; overflow:auto; border: 1px solid #dbe5f1; border-radius: 12px;">
                    <table class="template-fields-table">
                        <thead>
                        <tr>
                            <th>Field</th>
                            <th>X</th>
                            <th>Y</th>
                            <th>W</th>
                            <th>H</th>
                            <th>Size</th>
                            <th>Color</th>
                            <th>Align</th>
                            <th>Weight</th>
                            <th>On/Off</th>
                        </tr>
                        </thead>
                        <tbody id="templateFieldsBody"></tbody>
                    </table>
                </div>
            </form>
        </section>
    </div>
</section>

<script>
window.APCSNSCTemplateManager = {
    csrf: <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
    positions: <?= json_encode($positions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="<?= esc(base_url('assets/js/template-card.js?v=' . (string)filemtime(__DIR__ . '/../assets/js/template-card.js'))); ?>"></script>

<?php require_once __DIR__ . '/_bottom.php'; ?>
