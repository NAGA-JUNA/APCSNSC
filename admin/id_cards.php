<?php
require_once __DIR__ . '/../db.php';
require_admin();

$templateDbError = null;

$tableExists = static function (string $tableName): bool {
    try {
        $row = fetch_one('SHOW TABLES LIKE :table_name', [':table_name' => $tableName]);
        return (bool)$row;
    } catch (Throwable $e) {
        return false;
    }
};

$ensureTemplateTables = static function (): bool {
    try {
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

        return true;
    } catch (Throwable $e) {
        return false;
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

$defaultFieldMap = [];
foreach ($defaultFields as $field) {
    $defaultFieldMap[(string)$field['name']] = $field;
}

$templateDbReady = $tableExists('card_templates') && $tableExists('card_template_positions');
if (!$templateDbReady) {
    $templateDbReady = $ensureTemplateTables();
}
if ($templateDbReady) {
    try {
        $defaultTemplate = fetch_one('SELECT * FROM card_templates WHERE theme_name = :theme LIMIT 1', [':theme' => 'default']);
        if (!$defaultTemplate) {
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
    } catch (Throwable $e) {
        $templateDbReady = false;
        $templateDbError = 'Template DB initialization failed: ' . $e->getMessage();
    }
} else {
    $templateDbError = 'Template tables are unavailable or CREATE permission is denied.';
}

$settings = [];
if (function_exists('settings')) {
    $loadedSettings = settings();
    if (is_array($loadedSettings)) {
        $settings = $loadedSettings;
    }
} else {
    try {
        $settingsRows = fetch_all('SELECT setting_key, setting_value FROM settings');
        foreach ($settingsRows as $row) {
            $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
        }
    } catch (Throwable $e) {
        $settings = [];
    }
}
$siteUrl = trim((string)($settings['site_url'] ?? base_url('/')));
$supportEmail = trim((string)($settings['support_email'] ?? 'support@APCSNSC.in'));
$unionPhone = trim((string)($settings['contact_phone'] ?? ''));

$currentRole = admin_role();
$isSuperAdmin = $currentRole === 'super_admin';
$isStatePresident = $currentRole === 'state_president';
$isDistrictPresident = $currentRole === 'district_president';
$adminDistrict = trim(admin_district());
$idCardAccessNote = null;

$activeTemplate = [
    'theme_name' => 'default',
    'front_template' => 'uploads/templates/front-template.png',
    'back_template' => 'uploads/templates/back-template.png',
    'watermark_image' => null,
    'hologram_overlay' => null,
];

if ($templateDbReady) {
    try {
        $loadedTemplate = fetch_one('SELECT * FROM card_templates WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1');
        if (!$loadedTemplate) {
            $loadedTemplate = fetch_one('SELECT * FROM card_templates WHERE theme_name = :theme LIMIT 1', [':theme' => 'default']);
        }
        if ($loadedTemplate) {
            $activeTemplate = $loadedTemplate;
        }
    } catch (Throwable $e) {
        $templateDbReady = false;
        $templateDbError = 'Template read failed: ' . $e->getMessage();
    }
}

$positionsRows = [];
if ($templateDbReady) {
    try {
        $positionsRows = fetch_all('SELECT * FROM card_template_positions WHERE theme_name = :theme', [':theme' => (string)$activeTemplate['theme_name']]);
    } catch (Throwable $e) {
        $positionsRows = [];
        $templateDbError = 'Template positions read failed: ' . $e->getMessage();
    }
}
$positions = [];
foreach ($positionsRows as $row) {
    $fieldName = (string)$row['field_name'];
    $def = $defaultFieldMap[$fieldName] ?? null;

    $posX = (int)$row['pos_x'];
    $posY = (int)$row['pos_y'];
    $fontSize = (int)$row['font_size'];
    $width = (int)$row['width'];
    $height = (int)$row['height'];
    $isEnabled = isset($row['is_enabled']) ? (int)$row['is_enabled'] : 1;

    $validAlign = ['left', 'center', 'right'];
    $align = strtolower((string)$row['align']);
    if (!in_array($align, $validAlign, true)) {
        $align = (string)($def['align'] ?? 'left');
    }

    $side = (string)$row['side'];
    if ($side !== 'front' && $side !== 'back') {
        $side = (string)($def['side'] ?? 'front');
    }

    $color = (string)$row['color'];
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
        $color = (string)($def['color'] ?? '#1b2f44');
    }

    $fontWeight = (string)$row['font_weight'];
    if (!preg_match('/^(400|500|600|700|800|bold|normal)$/i', $fontWeight)) {
        $fontWeight = (string)($def['weight'] ?? '600');
    }

    if ($posX < 0 || $posX > 340) {
        $posX = (int)($def['x'] ?? 20);
    }
    if ($posY < 0 || $posY > 540) {
        $posY = (int)($def['y'] ?? 20);
    }
    if ($fontSize < 8 || $fontSize > 44) {
        $fontSize = (int)($def['size'] ?? 12);
    }
    if ($width < 20 || $width > 340) {
        $width = (int)($def['width'] ?? 120);
    }
    if ($height < 12 || $height > 320) {
        $height = (int)($def['height'] ?? 24);
    }
    if ($isEnabled !== 0 && $isEnabled !== 1) {
        $isEnabled = 1;
    }

    $positions[$fieldName] = [
        'side' => $side,
        'pos_x' => $posX,
        'pos_y' => $posY,
        'font_size' => $fontSize,
        'color' => $color,
        'align' => $align,
        'width' => $width,
        'height' => $height,
        'font_weight' => $fontWeight,
        'is_enabled' => $isEnabled,
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

$members = [];
$memberParams = [];
$memberWhereSql = '';
if ($isDistrictPresident) {
    if ($adminDistrict !== '') {
        $memberWhereSql = ' WHERE district = :district';
        $memberParams[':district'] = $adminDistrict;
        $idCardAccessNote = 'Showing cards for your district only: ' . $adminDistrict;
    } else {
        $memberWhereSql = ' WHERE 1 = 0';
        $idCardAccessNote = 'District mapping missing for your account. Contact Super Administrator.';
    }
} elseif (!$isSuperAdmin && !$isStatePresident) {
    $memberWhereSql = ' WHERE 1 = 0';
    $idCardAccessNote = 'Your role does not have permission to access ID card members list.';
}

try {
    $members = fetch_all(
        'SELECT id, member_id, full_name, district, working_place, designation, mobile, email, photo, signature, valid_till, status, created_at, blood_group
         FROM members' . $memberWhereSql . '
         ORDER BY id DESC',
        $memberParams
    );
} catch (Throwable $e) {
    // Fallback for older deployments where some optional member columns are missing.
    $members = fetch_all('SELECT * FROM members' . $memberWhereSql . ' ORDER BY id DESC', $memberParams);
}

$firstMember = $members[0] ?? null;
$cardState = [
    'id' => $firstMember['id'] ?? null,
    'member_id' => $firstMember['member_id'] ?? '',
    'full_name' => $firstMember['full_name'] ?? 'Member Name',
    'district' => $firstMember['district'] ?? 'District',
    'hospital' => $firstMember['working_place'] ?? 'Hospital',
    'role' => $firstMember['designation'] ?? 'Role',
    'designation' => $firstMember['designation'] ?? 'Role',
    'join_date' => !empty($firstMember['created_at']) ? date('d M Y', strtotime((string)$firstMember['created_at'])) : date('d M Y'),
    'valid_till' => !empty($firstMember['valid_till']) ? date('d M Y', strtotime((string)$firstMember['valid_till'])) : date('d M Y', strtotime('+1 year')),
    'blood_group' => $firstMember['blood_group'] ?? 'N/A',
    'phone' => $firstMember['mobile'] ?? $unionPhone,
    'email' => $firstMember['email'] ?? $supportEmail,
    'website' => $siteUrl,
    'status' => strtoupper((string)($firstMember['status'] ?? 'active')),
    'photo' => !empty($firstMember['photo']) ? base_url((string)$firstMember['photo']) : base_url('assets/images/default-avatar.png'),
    'signature' => !empty($firstMember['signature']) ? base_url((string)$firstMember['signature']) : base_url('assets/images/sign-placeholder.png'),
    'card_serial' => !empty($firstMember['member_id']) ? (string)$firstMember['member_id'] : 'APCSNSC-0000',
    'qr_url' => base_url('id_card.php?member_id=' . rawurlencode((string)($firstMember['member_id'] ?? ''))),
];

$pageTitle = 'ID Card Printing';
$activeMenu = 'id-cards';
$hideAdminPageTitle = true;
require_once __DIR__ . '/_top.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/template-card.css?v=' . (string)filemtime(__DIR__ . '/../assets/css/template-card.css'))); ?>">

<section class="template-page" id="templateCardGenerator">
    <div class="template-toolbar">
        <div>
            <div class="id-card-kicker">APCSNSC Admin ID Card Studio</div>
            <h3>Template-Based ID Card Printing System</h3>
            <p>Load member, preview front/back with active template, then export PNG/PDF, print, or bulk print.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($isSuperAdmin): ?>
            <a href="<?= esc(base_url('admin/card_templates.php')); ?>" class="btn btn-light"><i class="fa-solid fa-layer-group me-1"></i>Template Settings</a>
            <?php endif; ?>
            <button type="button" class="btn btn-success" id="downloadPngBtn"><i class="fa-solid fa-image me-1"></i>PNG</button>
            <button type="button" class="btn btn-danger" id="downloadPdfBtn"><i class="fa-solid fa-file-pdf me-1"></i>PDF</button>
            <button type="button" class="btn btn-info text-white" id="printBtn"><i class="fa-solid fa-print me-1"></i>Print</button>
        </div>
    </div>

    <?php if ($msg = get_flash('success')): ?>
        <div class="alert alert-success"><?= esc($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = get_flash('error')): ?>
        <div class="alert alert-danger"><?= esc($msg); ?></div>
    <?php endif; ?>
    <?php if ($templateDbError): ?>
        <div class="alert alert-warning">Template DB warning: <?= esc($templateDbError); ?> Using safe defaults for preview.</div>
    <?php endif; ?>
    <?php if ($idCardAccessNote): ?>
        <div class="alert alert-info"><?= esc($idCardAccessNote); ?></div>
    <?php endif; ?>

    <div class="template-grid">
        <aside class="template-side-panel">
            <div class="template-box">
                <h5>Member Selection</h5>
                <input id="memberSearchInput" type="text" class="form-control mb-2" placeholder="Search by ID, name, district...">
                <select id="memberSelect" class="form-select" size="10">
                    <option value="">Select a member</option>
                    <?php foreach ($members as $member):
                        $payload = [
                            'id' => (int)$member['id'],
                            'member_id' => (string)($member['member_id'] ?? ''),
                            'full_name' => (string)($member['full_name'] ?? ''),
                            'district' => (string)($member['district'] ?? ''),
                            'working_place' => (string)($member['working_place'] ?? ''),
                            'hospital' => (string)($member['working_place'] ?? ''),
                            'designation' => (string)($member['designation'] ?? ''),
                            'role' => (string)($member['designation'] ?? ''),
                            'mobile' => (string)($member['mobile'] ?? ''),
                            'phone' => (string)($member['mobile'] ?? ''),
                            'email' => (string)($member['email'] ?? ''),
                            'photo' => !empty($member['photo']) ? base_url((string)$member['photo']) : base_url('assets/images/default-avatar.png'),
                            'signature' => !empty($member['signature']) ? base_url((string)$member['signature']) : base_url('assets/images/sign-placeholder.png'),
                            'status' => strtoupper((string)($member['status'] ?? 'ACTIVE')),
                            'join_date' => !empty($member['created_at']) ? date('d M Y', strtotime((string)$member['created_at'])) : date('d M Y'),
                            'valid_till' => !empty($member['valid_till']) ? date('d M Y', strtotime((string)$member['valid_till'])) : date('d M Y', strtotime('+1 year')),
                            'blood_group' => (string)($member['blood_group'] ?? 'N/A'),
                            'website' => $siteUrl,
                            'card_serial' => (string)($member['member_id'] ?? ''),
                            'qr_url' => base_url('id_card.php?member_id=' . rawurlencode((string)($member['member_id'] ?? ''))),
                        ];
                        ?>
                        <option value="<?= (int)$member['id']; ?>" data-member='<?= esc(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>'>
                            <?= esc((string)$member['member_id'] . ' - ' . (string)$member['full_name'] . ' - ' . (string)$member['district']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="d-grid gap-2 mt-2">
                    <button type="button" class="btn btn-primary" id="loadMemberBtn"><i class="fa-solid fa-user-check me-1"></i>Load Member</button>
                    <button type="button" class="btn btn-secondary" id="generateCardBtn"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Refresh Preview</button>
                </div>
            </div>

            <div class="template-box">
                <h5>Bulk Print Members</h5>
                <div class="bulk-print-list">
                    <?php foreach ($members as $member): ?>
                        <label class="bulk-print-item">
                            <input type="checkbox" value="<?= (int)$member['id']; ?>">
                            <span><?= esc((string)$member['member_id'] . ' - ' . (string)$member['full_name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-outline-primary w-100 mt-2" id="bulkPrintBtn"><i class="fa-solid fa-copy me-1"></i>Bulk Print Selected</button>
            </div>

            <div class="template-box">
                <h5>Current Card Summary</h5>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <img id="summaryPhoto" src="<?= esc($cardState['photo']); ?>" alt="Member" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                    <div>
                        <div id="summaryName" style="font-weight:700;"><?= esc($cardState['full_name']); ?></div>
                        <small id="summaryMemberId" class="text-muted"><?= esc($cardState['member_id']); ?></small>
                    </div>
                </div>
                <div class="small">District: <span id="summaryDistrict"><?= esc($cardState['district']); ?></span></div>
                <div class="small">Role: <span id="summaryRole"><?= esc($cardState['role']); ?></span></div>
                <div class="small">Join Date: <span id="summaryJoinDate"><?= esc($cardState['join_date']); ?></span></div>
                <div class="small mt-2">Generated at: <strong id="generatedTimestamp"><?= esc(date('d M Y, h:i:s A')); ?></strong></div>
            </div>
        </aside>

        <section class="template-preview-shell">
            <div id="idCardExportArea" class="template-preview-grid">
                <div class="id-template-card-wrap">
                    <div class="id-template-card" id="frontCard">
                        <div class="id-template-bg"><img src="<?= esc(base_url((string)($activeTemplate['front_template'] ?? 'uploads/templates/front-template.png'))); ?>" alt="Front side"></div>
                        <?php if (!empty($activeTemplate['watermark_image'])): ?>
                            <div class="id-template-watermark"><img src="<?= esc(base_url((string)$activeTemplate['watermark_image'])); ?>" alt="Watermark"></div>
                        <?php endif; ?>
                        <?php if (!empty($activeTemplate['hologram_overlay'])): ?>
                            <div class="id-template-hologram"><img src="<?= esc(base_url((string)$activeTemplate['hologram_overlay'])); ?>" alt="Hologram"></div>
                        <?php endif; ?>

                        <div class="id-template-overlay" id="frontOverlay">
                            <div class="field-node is-photo" id="previewPhotoWrap"><img id="previewPhoto" src="<?= esc($cardState['photo']); ?>" alt="Photo"></div>
                            <div class="field-node" id="previewName"></div>
                            <div class="field-node badge" id="previewMemberId"></div>
                            <div class="field-node" id="previewDistrict"></div>
                            <div class="field-node" id="previewHospital"></div>
                            <div class="field-node" id="previewRole"></div>
                            <div class="field-node" id="previewJoinDate"></div>
                            <div class="field-node" id="previewValidTill"></div>
                            <div class="field-node" id="previewBloodGroup"></div>
                        </div>
                    </div>
                </div>

                <div class="id-template-card-wrap">
                    <div class="id-template-card" id="backCard">
                        <div class="id-template-bg"><img src="<?= esc(base_url((string)($activeTemplate['back_template'] ?? 'uploads/templates/back-template.png'))); ?>" alt="Back side"></div>
                        <?php if (!empty($activeTemplate['watermark_image'])): ?>
                            <div class="id-template-watermark"><img src="<?= esc(base_url((string)$activeTemplate['watermark_image'])); ?>" alt="Watermark"></div>
                        <?php endif; ?>
                        <?php if (!empty($activeTemplate['hologram_overlay'])): ?>
                            <div class="id-template-hologram"><img src="<?= esc(base_url((string)$activeTemplate['hologram_overlay'])); ?>" alt="Hologram"></div>
                        <?php endif; ?>

                        <div class="id-template-overlay" id="backOverlay">
                            <div class="field-node is-qr" id="previewQrWrap"><img id="previewQr" src="" alt="QR"></div>
                            <div class="field-node" id="previewSiteUrl"></div>
                            <div class="field-node" id="previewPhone" data-default="<?= esc($unionPhone); ?>"></div>
                            <div class="field-node" id="previewEmail"></div>
                            <div class="field-node signature" id="previewSignatureWrap"><img id="previewSignature" src="<?= esc($cardState['signature']); ?>" alt="Signature"></div>
                            <div class="field-node" id="previewCardSerialBack"></div>
                            <div class="field-node" id="previewCardSerial"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="templatePrintArea" class="bulk-print-stage"></div>
        </section>
    </div>
</section>

<input type="hidden" id="idCardCsrfToken" value="<?= esc(csrf_token()); ?>">
<input type="hidden" id="idCardStoreEndpoint" value="<?= esc(base_url('admin/save_id_card.php')); ?>">
<input type="hidden" id="idCardPathInput" value="">
<input type="hidden" id="idCardMemberViewBase" value="<?= esc(base_url('id_card.php?member_id=')); ?>">
<input type="hidden" id="idCardDefaultPhoto" value="<?= esc(base_url('assets/images/default-avatar.png')); ?>">
<div class="small text-muted mt-2">Stored path: <span id="storedPathText">Not saved yet</span></div>

<script>
window.APCSNSCTemplateState = <?= json_encode($cardState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.APCSNSCTemplateConfig = {
    templates: {
        front: <?= json_encode(base_url((string)($activeTemplate['front_template'] ?? 'uploads/templates/front-template.png')), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
        back: <?= json_encode(base_url((string)($activeTemplate['back_template'] ?? 'uploads/templates/back-template.png')), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
        watermark: <?= json_encode(!empty($activeTemplate['watermark_image']) ? base_url((string)$activeTemplate['watermark_image']) : '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
        hologram: <?= json_encode(!empty($activeTemplate['hologram_overlay']) ? base_url((string)$activeTemplate['hologram_overlay']) : '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    },
    positions: <?= json_encode($positions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="<?= esc(base_url('assets/js/template-card.js?v=' . (string)filemtime(__DIR__ . '/../assets/js/template-card.js'))); ?>"></script>

<?php require_once __DIR__ . '/_bottom.php'; ?>
