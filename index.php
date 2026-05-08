<?php
require_once __DIR__ . '/db.php';
$translations = get_translations();

function image_or_fallback(string $relativePath, string $fallbackUrl): string
{
    $fullPath = __DIR__ . '/' . ltrim($relativePath, '/');
    if (file_exists($fullPath)) {
        return base_url($relativePath);
    }

    return $fallbackUrl;
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

$heroSlides = fetch_all('SELECT * FROM hero_section WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC, id DESC LIMIT 3');
if (!$heroSlides) {
    $heroSlides = fetch_all('SELECT * FROM hero_section ORDER BY is_active DESC, sort_order ASC, created_at DESC, id DESC LIMIT 3');
}

if (!$heroSlides) {
    $heroSlides = [[
        'title' => 'Voice of Contract Staff Nurses',
        'subtitle' => 'Join our union and stand together for a better future for contract staff nurses across Andhra Pradesh.',
        'badge_text' => 'APCSNSC - Strength • Unity • Justice',
        'heading_line' => 'Fighting for Equality, Job Security & Dignity',
        'btn1_text' => 'Join the Union',
        'btn1_link' => base_url('register.php'),
        'btn2_text' => 'Submit Issue',
        'btn2_link' => base_url('pages/contact.php'),
        'joined_label' => 'Nurses Already Joined',
        'growth_text' => 'Growing Strong Every Day',
        'district_label' => 'Districts',
        'issues_label' => 'Active Issues',
        'cards_label' => 'ID Cards Issued',
        'background_image' => null,
        'animation_type' => 'fade',
        'overlay_color' => '#0f1b2e',
    ]];
}
$updates = array_values(array_filter(fetch_all('SELECT * FROM homepage_updates ORDER BY created_at DESC'), 'is_public_update'));
try {
    $districts = fetch_all('SELECT * FROM homepage_districts WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
} catch (Throwable) {
    $districts = fetch_all('SELECT * FROM homepage_districts ORDER BY id ASC');
}
$media = fetch_one('SELECT * FROM homepage_media ORDER BY id DESC LIMIT 1');

$safeFetchOne = static function (string $sql, array $params = []): ?array {
    try {
        return fetch_one($sql, $params);
    } catch (Throwable $e) {
        return null;
    }
};

$hasColumn = static function (string $table, string $column) use ($safeFetchOne): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }

    $row = $safeFetchOne("SHOW COLUMNS FROM `{$table}` LIKE ?", [$column]);
    return !empty($row);
};

$totalMembersCount = (int)(($safeFetchOne('SELECT COUNT(*) AS total FROM members') ?? ['total' => 0])['total'] ?? 0);
$totalComplaintsCount = (int)(($safeFetchOne('SELECT COUNT(*) AS total FROM complaints') ?? ['total' => 0])['total'] ?? 0);
$pendingComplaintsCount = $hasColumn('complaints', 'status')
    ? (int)(($safeFetchOne("SELECT COUNT(*) AS total FROM complaints WHERE status = 'pending'") ?? ['total' => 0])['total'] ?? 0)
    : 0;
$districtCount = count($districts);
$idCardsIssuedCount = $hasColumn('members', 'status')
    ? (int)(($safeFetchOne("SELECT COUNT(*) AS total FROM members WHERE status = 'approved'") ?? ['total' => 0])['total'] ?? 0)
    : 0;

$newMembersTodayCount = $hasColumn('members', 'created_at')
    ? (int)(($safeFetchOne('SELECT COUNT(*) AS total FROM members WHERE DATE(created_at) = CURDATE()') ?? ['total' => 0])['total'] ?? 0)
    : 0;
$hasUpdateStatusColumn = $hasColumn('homepage_updates', 'status');
$draftUpdatesCount = 0;
if ($hasUpdateStatusColumn) {
    $draftUpdatesCount = (int)(($safeFetchOne("SELECT COUNT(*) AS total FROM homepage_updates WHERE status = 'draft'") ?? ['total' => 0])['total'] ?? 0);
}

$statsCards = [
    [
        'label' => t('total_members', $translations),
        'value' => $totalMembersCount,
        'icon' => 'fa-solid fa-users',
        'note' => t('registered_with_apcsnsc', $translations),
        'suffix' => '',
    ],
    [
        'label' => t('total_complaints', $translations),
        'value' => $totalComplaintsCount,
        'icon' => 'fa-solid fa-triangle-exclamation',
        'note' => t('raised_by_members', $translations),
        'suffix' => '',
    ],
    [
        'label' => t('districts', $translations),
        'value' => $districtCount,
        'icon' => 'fa-solid fa-location-dot',
        'note' => t('all_across_andhra', $translations),
        'suffix' => '',
    ],
    [
        'label' => t('id_cards_issued', $translations),
        'value' => $idCardsIssuedCount,
        'icon' => 'fa-regular fa-id-card',
        'note' => t('approved_members', $translations),
        'suffix' => '',
    ],
];

$defaultHeroBg = image_or_fallback('uploads/hero-banner.jpg', 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1f?auto=format&fit=crop&w=1600&q=80');

$updateFallbackImages = [
    image_or_fallback('uploads/update-protest.jpg', 'https://images.unsplash.com/photo-1593115057322-e94b77572f20?auto=format&fit=crop&w=900&q=80'),
    image_or_fallback('uploads/update-meeting.jpg', 'https://images.unsplash.com/photo-1578496781985-452d4a934d50?auto=format&fit=crop&w=900&q=80'),
    image_or_fallback('uploads/update-demand.jpg', 'https://images.unsplash.com/photo-1526256262350-7da7584cf5eb?auto=format&fit=crop&w=900&q=80'),
];

$districtFallbackImages = [
    image_or_fallback('uploads/district-1.jpg', 'https://images.unsplash.com/photo-1469571486292-b53601020f90?auto=format&fit=crop&w=900&q=80'),
    image_or_fallback('uploads/district-2.jpg', 'https://images.unsplash.com/photo-1515169067868-5387ec356754?auto=format&fit=crop&w=900&q=80'),
    image_or_fallback('uploads/district-3.jpg', 'https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&w=900&q=80'),
    image_or_fallback('uploads/district-4.jpg', 'https://images.unsplash.com/photo-1519014816548-bf5fe059798b?auto=format&fit=crop&w=900&q=80'),
];

$memberPreviewImage = image_or_fallback('uploads/member-preview.jpg', 'https://i.pravatar.cc/100?img=25');
$galleryItems = array_slice($updates, 0, 4);

$safeFetchAll = static function (string $sql, array $params = []): array {
    try {
        return fetch_all($sql, $params);
    } catch (Throwable $e) {
        return [];
    }
};

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

$settingsRows = $safeFetchAll('SELECT setting_key, setting_value FROM homepage_showcase_settings');
$showcaseSettings = [];
foreach ($settingsRows as $settingRow) {
    $showcaseSettings[(string)$settingRow['setting_key']] = (string)($settingRow['setting_value'] ?? '');
}

$getSetting = static function (string $key, string $fallback = '') use ($showcaseSettings): string {
    if (!array_key_exists($key, $showcaseSettings)) {
        return $fallback;
    }

    return (string)$showcaseSettings[$key];
};

$benefits = $safeFetchAll('SELECT * FROM homepage_showcase_benefits WHERE is_active = 1 ORDER BY sort_order ASC, id DESC');
if (!$benefits) {
    $benefits = [
        ['icon' => 'fa-solid fa-id-card', 'title' => 'Digital ID Card', 'link' => base_url('id_card.php')],
        ['icon' => 'fa-solid fa-list-check', 'title' => 'Complaint Tracking', 'link' => base_url('member_dashboard.php')],
        ['icon' => 'fa-solid fa-map-location-dot', 'title' => 'District Updates', 'link' => base_url('pages/districts.php')],
        ['icon' => 'fa-regular fa-newspaper', 'title' => 'News Alerts', 'link' => base_url('pages/news.php')],
        ['icon' => 'fa-solid fa-headset', 'title' => 'Member Support', 'link' => base_url('pages/contact.php')],
        ['icon' => 'fa-solid fa-file-arrow-down', 'title' => 'Download Certificates', 'link' => base_url('member_dashboard.php')],
    ];
}

$videoRows = $safeFetchAll('SELECT * FROM homepage_showcase_videos WHERE is_active = 1 ORDER BY is_featured DESC, sort_order ASC, id DESC');
foreach ($videoRows as &$videoRow) {
    $videoId = !empty($videoRow['video_id']) ? (string)$videoRow['video_id'] : $parseYouTubeId((string)($videoRow['video_url'] ?? ''));
    $videoRow['video_id'] = $videoId;
    $videoRow['description'] = trim((string)($videoRow['description'] ?? ''));
    $videoRow['duration_text'] = trim((string)($videoRow['duration_text'] ?? ''));
    $videoRow['published_on'] = (string)($videoRow['published_on'] ?? '');
    $videoRow['video_url'] = (string)($videoRow['video_url'] ?? ('https://www.youtube.com/watch?v=' . $videoId));
}
unset($videoRow);
$videoRows = array_values(array_filter($videoRows, static function (array $row): bool {
    return !empty($row['video_id']);
}));

if (!$videoRows) {
    $videoRows = [
        [
            'id' => 0,
            'video_id' => 'dQw4w9WgXcQ',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'title' => 'Member Information Video',
            'description' => 'Learn the key services available for APCSNSC members and district teams.',
            'duration_text' => '04:15',
            'published_on' => date('Y-m-d'),
            'is_featured' => 1,
        ],
    ];
}

$featuredVideo = $videoRows[0];
foreach ($videoRows as $row) {
    if ((int)($row['is_featured'] ?? 0) === 1) {
        $featuredVideo = $row;
        break;
    }
}

$videoPlaylist = [];
foreach ($videoRows as $row) {
    $videoPlaylist[] = $row;
    if (count($videoPlaylist) >= 12) {
        break;
    }
}

$formatDateLabel = static function (string $value): string {
    if ($value === '') {
        return '';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return '';
    }

    return date('d M Y', $ts);
};

$showcaseGallery = $safeFetchAll('SELECT * FROM homepage_showcase_gallery WHERE is_active = 1 ORDER BY sort_order ASC, id DESC LIMIT 24');
if (!$showcaseGallery) {
    $showcaseGallery = [
        ['image_path' => image_or_fallback('uploads/update-protest.jpg', 'https://images.unsplash.com/photo-1593115057322-e94b77572f20?auto=format&fit=crop&w=900&q=80'), 'title' => 'Nurse Protest', 'category' => 'Protests'],
        ['image_path' => image_or_fallback('uploads/update-meeting.jpg', 'https://images.unsplash.com/photo-1578496781985-452d4a934d50?auto=format&fit=crop&w=900&q=80'), 'title' => 'Union Meeting', 'category' => 'Meetings'],
        ['image_path' => image_or_fallback('uploads/update-demand.jpg', 'https://images.unsplash.com/photo-1526256262350-7da7584cf5eb?auto=format&fit=crop&w=900&q=80'), 'title' => 'Member Campaign', 'category' => 'Events'],
    ];
}

$galleryCategories = ['All', 'Meetings', 'Protests', 'Events', 'Leaders', 'Certificates'];
foreach ($showcaseGallery as $item) {
    $category = trim((string)($item['category'] ?? ''));
    if ($category !== '' && !in_array($category, $galleryCategories, true)) {
        $galleryCategories[] = $category;
    }
}

$showcaseNews = $safeFetchAll('SELECT * FROM homepage_showcase_news WHERE is_active = 1 ORDER BY sort_order ASC, id DESC LIMIT 20');
if (!$showcaseNews) {
    $showcaseNews = [
        ['notice_text' => 'State-level grievance follow-up meeting scheduled this week.', 'notice_link' => base_url('pages/news.php'), 'notice_priority' => 'normal', 'created_at' => date('Y-m-d')],
        ['notice_text' => 'District membership drive registrations are now open.', 'notice_link' => base_url('register.php'), 'notice_priority' => 'urgent', 'created_at' => date('Y-m-d')],
        ['notice_text' => 'Members can download updated ID cards from dashboard.', 'notice_link' => base_url('member_dashboard.php'), 'notice_priority' => 'normal', 'created_at' => date('Y-m-d')],
    ];
}

foreach ($showcaseNews as &$noticeRow) {
    $priority = strtolower(trim((string)($noticeRow['notice_priority'] ?? 'normal')));
    if (!in_array($priority, ['urgent', 'normal'], true)) {
        $priority = 'normal';
    }

    $noticeRow['notice_priority'] = $priority;
    $noticeRow['full_text'] = trim((string)($noticeRow['full_text'] ?? ''));
    $noticeRow['date_badge'] = $formatDateLabel((string)($noticeRow['created_at'] ?? ''));
}
unset($noticeRow);

$complaintsSolvedAuto = $totalComplaintsCount;
if ($hasColumn('complaints', 'status')) {
    $complaintsSolvedAuto = (int)(($safeFetchOne("SELECT COUNT(*) AS total FROM complaints WHERE LOWER(status) IN ('solved', 'resolved', 'closed', 'approved')") ?? ['total' => 0])['total'] ?? 0);
}

$buildCounter = static function (string $slug, int $autoValue, string $autoLabel) use ($getSetting): array {
    $mode = strtolower($getSetting('counter_' . $slug . '_mode', 'auto'));
    $label = $getSetting('counter_' . $slug . '_label', $autoLabel);
    $manualValue = (int)$getSetting('counter_' . $slug . '_value', (string)$autoValue);

    return [
        'label' => $label,
        'value' => $mode === 'manual' ? $manualValue : $autoValue,
    ];
};

$showcaseCounters = [
    $buildCounter('total_members', $totalMembersCount, 'Total Members'),
    $buildCounter('complaints_solved', $complaintsSolvedAuto, 'Complaints Solved'),
    $buildCounter('districts_active', $districtCount, 'Districts Active'),
    $buildCounter('id_cards_issued', $idCardsIssuedCount, 'ID Cards Issued'),
];

$tickerSpeedSeconds = (int)$getSetting('news_ticker_speed_seconds', '28');
if ($tickerSpeedSeconds < 10) {
    $tickerSpeedSeconds = 10;
}
if ($tickerSpeedSeconds > 90) {
    $tickerSpeedSeconds = 90;
}

$showcaseCss = __DIR__ . '/assets/css/showcase-section.css';
$showcaseCssVer = file_exists($showcaseCss) ? (string)filemtime($showcaseCss) : (string)time();
$pageStyles = array_merge($pageStyles ?? [], [base_url('assets/css/showcase-section.css?v=' . $showcaseCssVer)]);

// Add media showcase CSS to page styles (will be loaded in header)
$showcaseOptBCss = __DIR__ . '/assets/css/media-showcase-optionb.css';
$showcaseOptBCssVer = file_exists($showcaseOptBCss) ? (string)filemtime($showcaseOptBCss) : (string)time();
$pageStyles = array_merge($pageStyles ?? [], [base_url('assets/css/media-showcase-optionb.css?v=' . $showcaseOptBCssVer)]);


require_once __DIR__ . '/header.php';
?>
<section class="section section-hero">
    <div class="container home-shell">
        <div class="home-hero-slider" data-home-hero-slider>
            <?php foreach ($heroSlides as $index => $slide): ?>
                <?php
                $heroBg = !empty($slide['background_image']) ? base_url((string)$slide['background_image']) : $defaultHeroBg;
                $animationType = (string)($slide['animation_type'] ?? 'fade');
                ?>
                <article class="home-hero home-hero-slide <?= $index === 0 ? 'is-active' : ''; ?>" data-home-hero-slide data-animation="<?= esc($animationType); ?>">
                    <div class="home-hero-left">
                        <span class="home-badge"><?= esc((string)($slide['badge_text'] ?? t('apcsnsc_strength_unity_justice', $translations))); ?></span>
                        <h1><?= esc((string)($slide['title'] ?? t('voice_of_nurses', $translations))); ?></h1>
                        <h3><?= esc((string)($slide['heading_line'] ?? t('fighting_for_equality', $translations))); ?></h3>
                        <p><?= esc((string)($slide['subtitle'] ?? t('join_our_union_subtitle', $translations))); ?></p>
                        <div class="hero-actions">
                            <a class="btn btn-primary" href="<?= esc(!empty($slide['btn1_link']) ? (string)$slide['btn1_link'] : base_url('register.php')); ?>"><?= esc((string)($slide['btn1_text'] ?? t('join_the_union', $translations))); ?></a>
                            <a class="btn btn-outline" href="<?= esc(!empty($slide['btn2_link']) ? (string)$slide['btn2_link'] : base_url('pages/contact.php')); ?>"><?= esc((string)($slide['btn2_text'] ?? t('submit_issue', $translations))); ?></a>
                        </div>
                        <div class="hero-trust-row">
                            <div class="avatar-stack">
                                <img src="https://i.pravatar.cc/80?img=11" alt="Member 1">
                                <img src="https://i.pravatar.cc/80?img=22" alt="Member 2">
                                <img src="https://i.pravatar.cc/80?img=33" alt="Member 3">
                                <img src="https://i.pravatar.cc/80?img=44" alt="Member 4">
                            </div>
                            <p><strong><?= esc(number_format($totalMembersCount)); ?></strong> <?= esc((string)($slide['joined_label'] ?? t('nurses_already_joined', $translations))); ?></p>
                            <span class="trust-growth"><?= esc((string)($slide['growth_text'] ?? t('growing_strong', $translations))); ?></span>
                        </div>
                    </div>

                    <div class="home-hero-right">
                        <div class="hero-image-wrap">
                            <img src="<?= esc($heroBg); ?>" alt="APCSNSC hero background image">
                            <span class="hero-image-gradient"></span>
                            <article class="floating-stat card-glass stat-one">
                                <span class="floating-stat-icon stat-icon-district" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                                <div>
                                    <strong><?= esc((string)$districtCount); ?></strong>
                                    <small><?= esc((string)($slide['district_label'] ?? t('districts', $translations))); ?></small>
                                </div>
                            </article>
                            <article class="floating-stat card-glass stat-two">
                                <span class="floating-stat-icon stat-icon-issues" aria-hidden="true"><i class="fa-solid fa-water"></i></span>
                                <div>
                                    <strong><?= esc((string)$pendingComplaintsCount); ?></strong>
                                    <small><?= esc((string)($slide['issues_label'] ?? t('active_issues', $translations))); ?></small>
                                </div>
                            </article>
                            <article class="floating-stat card-glass stat-three">
                                <span class="floating-stat-icon stat-icon-card" aria-hidden="true"><i class="fa-regular fa-id-card"></i></span>
                                <div>
                                    <strong><?= esc(number_format($idCardsIssuedCount)); ?></strong>
                                    <small><?= esc((string)($slide['cards_label'] ?? t('id_cards_issued', $translations))); ?></small>
                                </div>
                            </article>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (count($heroSlides) > 1): ?>
                <button class="home-hero-nav prev" type="button" data-home-hero-prev aria-label="Previous slide">&#10094;</button>
                <button class="home-hero-nav next" type="button" data-home-hero-next aria-label="Next slide">&#10095;</button>
                <div class="home-hero-dots" role="tablist" aria-label="Hero slider dots">
                    <?php foreach ($heroSlides as $index => $slide): ?>
                        <button class="home-hero-dot <?= $index === 0 ? 'is-active' : ''; ?>" type="button" data-home-hero-dot="<?= esc((string)$index); ?>" aria-label="Slide <?= esc((string)($index + 1)); ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section section-stats section-stats-modern" aria-labelledby="stats-title">
    <div class="container home-shell">
        <div class="stats-surface fade-in">
            <div class="stats-head">
                <h2 id="stats-title"><?= t('impact_dashboard_title', $translations); ?></h2>
                <p><?= t('impact_dashboard_subtitle', $translations); ?></p>
            </div>
            <div class="stats-grid stats-grid-modern" role="list">
                <?php foreach ($statsCards as $index => $item): ?>
                    <article class="card stat-card stat-card-modern <?= $index === 0 ? 'is-primary' : ''; ?>" role="listitem">
                        <span class="stat-icon" aria-hidden="true"><i class="<?= esc((string)$item['icon']); ?>"></i></span>
                        <p class="stat-number-wrap">
                            <span
                                class="stat-value counter-value"
                                data-target="<?= esc((string)$item['value']); ?>"
                                data-suffix="<?= esc((string)$item['suffix']); ?>"
                                data-duration="1600"
                            ><?= esc(number_format((int)$item['value']) . (string)$item['suffix']); ?></span>
                        </p>
                        <p class="stat-label"><?= esc((string)$item['label']); ?></p>
                        <small class="stat-note"><?= esc((string)$item['note']); ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="section union-cta-section">
    <div class="container home-shell">
        <div class="updates-layout fade-in">
            <div class="updates-main updates-carousel" data-updates-carousel>
                <div class="section-title section-title-tools">
                    <h2><?= t('latest_updates', $translations); ?></h2>
                    <div class="title-controls">
                        <button type="button" data-updates-prev aria-label="Previous updates">←</button>
                        <button type="button" data-updates-next aria-label="Next updates">→</button>
                    </div>
                </div>
                <div class="updates-cards" data-updates-track>
                    <?php foreach ($updates as $index => $item): ?>
                        <?php
                        $tags = ['Protest', 'Meeting', 'Demand'];
                        $tag = $tags[$index % 3];
                        $description = (string)$item['description'];
                        $updateImages = get_update_images($item);
                        $primaryImage = $updateImages[0] ?? (!empty($item['image']) ? (string)$item['image'] : '');
                        $excerpt = function_exists('mb_strimwidth')
                            ? mb_strimwidth($description, 0, 100, '...')
                            : (strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description);
                        ?>
                        <article class="card news-card">
                            <div class="news-image-wrap">
                                <?php if (count($updateImages) > 1): ?>
                                    <div class="news-image-slider" data-update-card-slider>
                                        <div class="news-image-slider-track">
                                            <?php foreach ($updateImages as $imgIndex => $imagePath): ?>
                                                <img class="news-image-slide <?= $imgIndex === 0 ? 'is-active' : ''; ?>" data-update-slide src="<?= esc(base_url($imagePath)); ?>" alt="<?= esc($item['title']); ?>">
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="news-image-slider-btn prev" data-update-card-prev aria-label="Previous image">‹</button>
                                        <button type="button" class="news-image-slider-btn next" data-update-card-next aria-label="Next image">›</button>
                                        <div class="news-image-slider-dots">
                                            <?php foreach ($updateImages as $imgIndex => $imagePath): ?>
                                                <button type="button" class="news-image-slider-dot <?= $imgIndex === 0 ? 'is-active' : ''; ?>" data-update-card-dot="<?= esc((string)$imgIndex); ?>" aria-label="Image <?= esc((string)($imgIndex + 1)); ?>"></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <img src="<?= esc(!empty($primaryImage) ? base_url($primaryImage) : $updateFallbackImages[$index % count($updateFallbackImages)]); ?>" alt="<?= esc($item['title']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="news-meta-row">
                                <span class="news-tag"><?= esc($tag); ?></span>
                                <small class="muted"><?= esc(date('M d, Y', strtotime((string)$item['created_at']))); ?></small>
                            </div>
                            <h3><?= esc($item['title']); ?></h3>
                            <p class="muted"><?= esc($excerpt); ?></p>
                            <a class="read-more" href="<?= esc(get_update_url($item)); ?>">Read More</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="quick-actions card gos-widget" id="gos-widget" aria-labelledby="gos-title">
                <div class="quick-head gos-head">
                    <div>
                        <h3 id="gos-title">GO’s &amp; Official Updates</h3>
                        <p class="muted">Real-time Government Updates</p>
                    </div>
                    <div class="gos-live">
                        <span class="gos-live-dot" aria-hidden="true"></span>
                        <small class="muted">Live</small>
                    </div>
                </div>

                <div class="gos-carousel-wrapper">
                    <div class="gos-carousel" data-gos-carousel>
                        <div class="gos-carousel-track" data-gos-track>
                            <!-- Card 1 -->
                            <div class="gos-highlight gos-carousel-card">
                                <div class="gos-badge">NEW</div>
                                <h4 class="gos-highlight-title">GO Ms No.60 – Salary Enhancement</h4>
                                <div class="gos-meta-row">
                                    <small class="muted">Apr 28, 2026</small>
                                    <span class="gos-tag">Salary</span>
                                </div>
                                <p class="muted gos-desc">This Government Order provides salary enhancement guidelines affecting contract staff nurses across state health services.</p>
                                <div class="gos-actions">
                                    <a class="btn btn-primary" href="<?= esc(base_url('uploads/gos/GO_Ms_60.pdf')); ?>" target="_blank" rel="noopener">View PDF</a>
                                    <button type="button" class="btn btn-outline gos-summary-toggle">Quick Summary</button>
                                </div>
                                <div class="gos-summary" hidden>
                                    <h5>Quick Summary</h5>
                                    <p><strong>What this GO says:</strong> The GO raises base pay scales for specified contract categories and outlines implementation timelines.</p>
                                    <p><strong>Impact on contract staff nurses:</strong> Incremental salary increases and revised allowances; may affect leave and contract renewal terms.</p>
                                </div>
                            </div>

                            <!-- Card 2 -->
                            <div class="gos-highlight gos-carousel-card">
                                <div class="gos-badge">UPDATE</div>
                                <h4 class="gos-highlight-title">GO Ms No.45 – Recruitment Guidelines</h4>
                                <div class="gos-meta-row">
                                    <small class="muted">Mar 15, 2026</small>
                                    <span class="gos-tag">Recruitment</span>
                                </div>
                                <p class="muted gos-desc">New recruitment framework for contract positions outlining eligibility criteria, selection process and onboarding procedures for health workers.</p>
                                <div class="gos-actions">
                                    <a class="btn btn-primary" href="<?= esc(base_url('uploads/gos/GO_Ms_45.pdf')); ?>" target="_blank" rel="noopener">View PDF</a>
                                    <button type="button" class="btn btn-outline gos-summary-toggle">Quick Summary</button>
                                </div>
                                <div class="gos-summary" hidden>
                                    <h5>Quick Summary</h5>
                                    <p><strong>What this GO says:</strong> Establishes new recruitment standards and qualification requirements for contract staff positions.</p>
                                    <p><strong>Impact on contract staff nurses:</strong> Clarifies career progression pathways and standardizes recruitment across all districts.</p>
                                </div>
                            </div>

                            <!-- Card 3 -->
                            <div class="gos-highlight gos-carousel-card">
                                <div class="gos-badge">NOTICE</div>
                                <h4 class="gos-highlight-title">GO Ms No.32 – Welfare Policies</h4>
                                <div class="gos-meta-row">
                                    <small class="muted">Feb 20, 2026</small>
                                    <span class="gos-tag">Policies</span>
                                </div>
                                <p class="muted gos-desc">Comprehensive welfare and benefits scheme for contract staff including health insurance, leave provisions and emergency assistance programs.</p>
                                <div class="gos-actions">
                                    <a class="btn btn-primary" href="<?= esc(base_url('uploads/gos/GO_Ms_32.pdf')); ?>" target="_blank" rel="noopener">View PDF</a>
                                    <button type="button" class="btn btn-outline gos-summary-toggle">Quick Summary</button>
                                </div>
                                <div class="gos-summary" hidden>
                                    <h5>Quick Summary</h5>
                                    <p><strong>What this GO says:</strong> Introduces comprehensive welfare benefits and insurance schemes for contract workers.</p>
                                    <p><strong>Impact on contract staff nurses:</strong> Provides health coverage, emergency funds, and improved leave policies for better job security.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Carousel Navigation -->
                    <button type="button" class="gos-carousel-nav gos-carousel-prev" data-gos-prev aria-label="Previous GO">‹</button>
                    <button type="button" class="gos-carousel-nav gos-carousel-next" data-gos-next aria-label="Next GO">›</button>
                </div>

                <div class="gos-quick-grid" role="list" aria-label="Quick actions">
                    <a href="<?= esc(base_url('pages/gos.php')); ?>" role="listitem" title="All GO's"><span class="icon"><i class="fa-regular fa-file-lines" aria-hidden="true"></i></span></a>
                    <a href="<?= esc(base_url('pages/gos.php?cat=recruitment')); ?>" role="listitem" title="Recruitment"><span class="icon"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></span></a>
                    <a href="<?= esc(base_url('pages/gos.php?cat=salary')); ?>" role="listitem" title="Salary Updates"><span class="icon"><i class="fa-solid fa-money-bill" aria-hidden="true"></i></span></a>
                    <a href="<?= esc(base_url('pages/gos.php?cat=circulars')); ?>" role="listitem" title="Circulars"><span class="icon"><i class="fa-regular fa-circle-check" aria-hidden="true"></i></span></a>
                    <a href="<?= esc(base_url('pages/gos.php?cat=policies')); ?>" role="listitem" title="Policies"><span class="icon"><i class="fa-solid fa-gavel" aria-hidden="true"></i></span></a>
                    <a href="<?= esc(base_url('pages/gos.php?cat=downloads')); ?>" role="listitem" title="Downloads"><span class="icon"><i class="fa-solid fa-download" aria-hidden="true"></i></span></a>
                </div>

                <div class="gos-footer">
                    <a class="btn btn-block btn-outline" href="<?= esc(base_url('pages/gos.php')); ?>">View All GO’s &amp; Official Updates →</a>
                </div>

            </aside>

            <script>
            (function(){
                document.addEventListener('DOMContentLoaded', function(){
                    // Carousel functionality
                    var carousel = document.querySelector('[data-gos-carousel]');
                    var track = document.querySelector('[data-gos-track]');
                    var prevBtn = document.querySelector('[data-gos-prev]');
                    var nextBtn = document.querySelector('[data-gos-next]');
                    var cards = track ? track.querySelectorAll('.gos-carousel-card') : [];
                    
                    var currentIndex = 0;
                    
                    function updateCarousel() {
                        if (!track) return;
                        var offset = currentIndex * -100;
                        track.style.transform = 'translateX(' + offset + '%)';
                    }
                    
                    if (prevBtn) {
                        prevBtn.addEventListener('click', function(){
                            currentIndex = (currentIndex - 1 + cards.length) % cards.length;
                            updateCarousel();
                        });
                    }
                    
                    if (nextBtn) {
                        nextBtn.addEventListener('click', function(){
                            currentIndex = (currentIndex + 1) % cards.length;
                            updateCarousel();
                        });
                    }
                    
                    // Quick Summary toggle for all cards
                    var summaryToggles = document.querySelectorAll('.gos-summary-toggle');
                    summaryToggles.forEach(function(toggle){
                        toggle.addEventListener('click', function(){
                            var summary = toggle.parentElement.parentElement.querySelector('.gos-summary');
                            if (!summary) return;
                            var isHidden = summary.hasAttribute('hidden');
                            if (isHidden) {
                                summary.removeAttribute('hidden');
                                toggle.innerText = 'Hide Summary';
                            } else {
                                summary.setAttribute('hidden','');
                                toggle.innerText = 'Quick Summary';
                            }
                        });
                    });
                });
            })();
            </script>

        </div>
    </div>
</section>

<section class="section union-cta-section">
    <div class="container home-shell">
        <div class="district-layout fade-in">
            <div class="district-carousel-wrap" data-district-carousel>
                <div class="section-title">
                    <h2><?= t('district_committees', $translations); ?></h2>
                    <div class="title-controls">
                        <a class="btn btn-outline" href="<?= esc(base_url('pages/districts.php')); ?>" style="font-size:13px;padding:6px 14px;"><?= t('view_all_districts', $translations); ?></a>
                        <button type="button" data-district-prev aria-label="Previous districts">←</button>
                        <button type="button" data-district-next aria-label="Next districts">→</button>
                    </div>
                </div>
                <div class="district-cards" data-district-track>
                    <?php foreach ($districts as $index => $district): ?>
                        <article class="card district-photo-card">
                            <img src="<?= esc(!empty($district['image']) ? base_url($district['image']) : $districtFallbackImages[$index % count($districtFallbackImages)]); ?>" alt="<?= esc($district['name']); ?>">
                            <div class="district-info">
                                <h3><?= esc($district['name']); ?></h3>
                                <a href="<?= esc(base_url('pages/districts.php')); ?>">View Details</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="member-cta-card">
                <span class="member-crown">👑</span>
                <h3><?= t('become_a_member', $translations); ?></h3>
                <p><?= t('strengthen_your_voice', $translations); ?></p>
                <a class="btn btn-primary" href="<?= esc(base_url('register.php')); ?>"><?= t('register_now', $translations); ?></a>
                <div class="hero-trust-row">
                    <div class="avatar-stack">
                        <img src="https://i.pravatar.cc/80?img=55" alt="Member">
                        <img src="https://i.pravatar.cc/80?img=59" alt="Member">
                        <img src="https://i.pravatar.cc/80?img=62" alt="Member">
                    </div>
                    <p><strong><?= esc(number_format($totalMembersCount)); ?>+</strong></p>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php
$mediaShowcaseInclude = __DIR__ . '/includes/media_showcase_dynamic.php';
if (file_exists($mediaShowcaseInclude)) {
    include $mediaShowcaseInclude;
} else {
    $fallbackFeatured = $featuredVideo;
    $fallbackMiniVideos = [];
    foreach ($videoPlaylist as $videoRow) {
        if ((string)($videoRow['video_id'] ?? '') === (string)($fallbackFeatured['video_id'] ?? '')) {
            continue;
        }
        $fallbackMiniVideos[] = $videoRow;
    }
    ?>
    <section id="media-showcase-optionb" class="section" aria-labelledby="media-showcase-title">
        <div class="container home-shell ms-shell">
            <header class="ms-header">
                <h2 id="media-showcase-title">Latest Media &amp; Updates</h2>
                <p>Stay updated with our latest activities, campaigns &amp; publications</p>
            </header>

            <div class="ms-tabs">
                <button class="ms-tab is-active" data-tab-id="videos" aria-selected="true"><i class="fa-solid fa-video" aria-hidden="true"></i>Latest Videos</button>
                <button class="ms-tab" data-tab-id="media" aria-selected="false"><i class="fa-solid fa-photo-film" aria-hidden="true"></i>Media Library</button>
                <button class="ms-tab" data-tab-id="epaper" aria-selected="false"><i class="fa-regular fa-newspaper" aria-hidden="true"></i>ePublications</button>
                <button class="ms-tab" data-tab-id="poster" aria-selected="false"><i class="fa-regular fa-image" aria-hidden="true"></i>Posters & Banners</button>
            </div>

            <div class="ms-panels">
                <div class="ms-tab-panel is-active" data-tab-panel="videos">
                    <div class="ms-video-layout">
                        <article class="ms-video-card ms-featured-card" data-video-id="<?= esc((string)($fallbackFeatured['video_id'] ?? '')); ?>" data-video-title="<?= esc((string)($fallbackFeatured['title'] ?? '')); ?>" data-video-date="<?= esc((string)($fallbackFeatured['published_on'] ?? '')); ?>">
                            <div class="ms-card-media">
                                <img src="https://img.youtube.com/vi/<?= esc((string)($fallbackFeatured['video_id'] ?? 'dQw4w9WgXcQ')); ?>/hqdefault.jpg" alt="<?= esc((string)($fallbackFeatured['title'] ?? 'Featured Video')); ?>" loading="lazy">
                                <span class="ms-play-badge" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                                <div class="ms-watch-overlay">▶ Watch Now</div>
                            </div>
                            <div class="ms-card-content">
                                <h3 class="ms-card-title"><?= esc((string)($fallbackFeatured['title'] ?? 'Featured Video')); ?></h3>
                                <div class="ms-card-meta">
                                  <span class="ms-card-date"><i class="fa-regular fa-calendar"></i> <?= esc((string)($fallbackFeatured['published_on'] ?? '')); ?></span>
                                  <span><i class="fa-solid fa-eye"></i> 1.2k Views</span>
                                  <span><i class="fa-solid fa-tag"></i> Latest</span>
                                </div>
                            </div>
                        </article>

                        <div class="ms-side-column-wrapper playlist-container">
                            <button class="ms-scroll-btn scroll-up" aria-label="Scroll Up" type="button">
                              <i class="fa-solid fa-chevron-up"></i>
                            </button>
                            <div class="ms-side-column">
                                <header class="ms-side-header">
                                    <div class="ms-side-header-title">
                                        <i class="fa-solid fa-list" aria-hidden="true"></i> Playlist
                                    </div>
                                    <a href="#" class="ms-view-all-link">View All &rarr;</a>
                                </header>
                                <div class="ms-side-track" data-ms-scroll-track>
                                    <?php foreach ($fallbackMiniVideos as $miniVideo): ?>
                                        <?php
                                        $miniDuration = esc((string)($miniVideo['duration_text'] ?? ''));
                                        $miniDate = esc((string)($miniVideo['published_on'] ?? ''));
                                        ?>
                                        <article class="ms-video-card ms-mini-card" data-video-id="<?= esc((string)($miniVideo['video_id'] ?? '')); ?>" data-video-title="<?= esc((string)($miniVideo['title'] ?? 'Video')); ?>" data-video-date="<?= $miniDate; ?>">
                                            <div class="ms-card-media">
                                                <img src="https://img.youtube.com/vi/<?= esc((string)($miniVideo['video_id'] ?? 'dQw4w9WgXcQ')); ?>/mqdefault.jpg" alt="<?= esc((string)($miniVideo['title'] ?? 'Video')); ?>" loading="lazy">
                                                <?php if ($miniDuration !== ''): ?>
                                                  <span class="ms-mini-duration"><?= $miniDuration; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ms-card-content">
                                                <h4 class="ms-card-title"><?= esc((string)($miniVideo['title'] ?? 'Video')); ?></h4>
                                                <div class="ms-card-meta">
                                                    <?php if ($miniDate !== ''): ?>
                                                      <span class="ms-card-date"><?= $miniDate; ?></span>
                                                      <span>·</span>
                                                    <?php endif; ?>
                                                    <span><?= esc((string)($miniVideo['views'] ?? '—')); ?> Views</span>
                                                </div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                    <?php if (!$fallbackMiniVideos): ?>
                                         <div style="padding: 1.5rem; color: var(--ms-muted); text-align: center; font-size: 0.9rem;">No other videos in playlist.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button class="ms-scroll-btn scroll-down" aria-label="Scroll Down" type="button">
                              <i class="fa-solid fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="ms-tab-panel" data-tab-panel="media">
                    <div class="ms-content-grid">
                        <?php foreach (array_slice($showcaseGallery, 0, 8) as $g): ?>
                            <article class="ms-media-card">
                                <a href="<?= esc((string)($g['image_path'] ?? '')); ?>" class="ms-card-media" data-fancybox="media-gallery" data-caption="<?= esc((string)($g['title'] ?? '')); ?>" style="display: block;">
                                    <img src="<?= esc((string)($g['image_path'] ?? '')); ?>" alt="<?= esc((string)($g['title'] ?? '')); ?>" loading="lazy">
                                    <div class="ms-watch-overlay">View Item</div>
                                </a>
                                <div class="ms-card-content">
                                    <h4 class="ms-card-title"><?= esc((string)($g['title'] ?? '')); ?></h4>
                                    <p class="ms-card-date"><?= esc((string)($g['category'] ?? '')); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ms-tab-panel" data-tab-panel="epaper">
                    <div class="ms-content-grid">
                        <?php foreach (array_slice($showcaseNews, 0, 8) as $notice): ?>
                            <article class="ms-media-card">
                                <div class="ms-card-content">
                                    <h4 class="ms-card-title"><?= esc((string)($notice['notice_text'] ?? 'Update')); ?></h4>
                                    <p class="ms-card-date"><?= esc((string)($notice['date_badge'] ?? '')); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ms-tab-panel" data-tab-panel="poster">
                    <div class="ms-content-grid">
                        <?php foreach (array_slice($showcaseGallery, 0, 6) as $poster): ?>
                            <article class="ms-media-card">
                                <a href="<?= esc((string)($poster['image_path'] ?? '')); ?>" class="ms-card-media" data-fancybox="poster-gallery" data-caption="<?= esc((string)($poster['title'] ?? 'Poster')); ?>" style="display: block;">
                                    <img src="<?= esc((string)($poster['image_path'] ?? '')); ?>" alt="<?= esc((string)($poster['title'] ?? '')); ?>" loading="lazy">
                                    <div class="ms-watch-overlay">View Item</div>
                                </a>
                                <div class="ms-card-content">
                                    <h4 class="ms-card-title"><?= esc((string)($poster['title'] ?? 'Poster')); ?></h4>
                                    <p class="ms-card-date">Posters &amp; Banners</p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="ms-footer-actions">
                <a class="ms-view-all" href="<?= esc(base_url('pages/news.php')); ?>">View All &rarr;</a>
            </div>
        </div>
    </section>

    <!-- Cinematic Video Modal -->
    <div class="ms-video-modal" id="ms-cinematic-modal" aria-hidden="true" role="dialog">
      <div class="ms-modal-backdrop" data-modal-close></div>
      <div class="ms-modal-dialog">
        <button class="ms-modal-close" data-modal-close aria-label="Close video" type="button"><i class="fa-solid fa-xmark"></i></button>
        <div class="ms-modal-body">
          <iframe class="ms-modal-iframe" id="ms-modal-iframe" src="" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
      </div>
    </div>

    <!-- JS handled by assets/js/media-showcase-optionb.js -->
    <?php
}
?>


<?php if (($getSetting)('show_union_cta', '1') === '1'): ?>
<section class="section">
    <div class="container home-shell">
        <div class="union-cta-card fade-in">
            <div class="union-cta-copy">
                <span class="union-cta-badge"><i class="fa-solid fa-crown"></i> Stronger Together</span>
                <h2><?= t('stand_together', $translations); ?></h2>
                <p><?= t('join_be_part_of_change', $translations); ?></p>

                <div class="union-cta-benefits" aria-label="Key membership benefits">
                    <div class="union-cta-benefit">
                        <span class="union-cta-benefit-icon"><i class="fa-solid fa-shield-heart"></i></span>
                        <span class="union-cta-benefit-text">Protect<br>Your Rights</span>
                    </div>
                    <div class="union-cta-benefit">
                        <span class="union-cta-benefit-icon"><i class="fa-solid fa-people-group"></i></span>
                        <span class="union-cta-benefit-text">Stronger<br>Together</span>
                    </div>
                    <div class="union-cta-benefit">
                        <span class="union-cta-benefit-icon"><i class="fa-solid fa-scale-balanced"></i></span>
                        <span class="union-cta-benefit-text">Fair<br>For All</span>
                    </div>
                    <div class="union-cta-benefit">
                        <span class="union-cta-benefit-icon"><i class="fa-solid fa-bullhorn"></i></span>
                        <span class="union-cta-benefit-text">Raise<br>Your Voice</span>
                    </div>
                </div>
            </div>

            <div class="union-cta-visual" aria-hidden="true">
                <img src="<?= esc(base_url('assets/images/cta-union-emblem.svg')); ?>" alt="" class="union-cta-emblem" loading="lazy">
            </div>

            <div class="union-cta-strip">
                <div class="union-cta-joiners">
                    <span class="union-cta-avatar-stack" aria-hidden="true">
                        <span class="union-cta-avatar">A</span>
                        <span class="union-cta-avatar">P</span>
                        <span class="union-cta-avatar">C</span>
                    </span>
                    <span class="union-cta-joiners-badge">1K+</span>
                    <span class="union-cta-joiners-copy">
                        <strong>1,000+ nurses already joined</strong>
                        <small>Be a part of the change!</small>
                    </span>
                </div>
                <a class="union-cta-button" href="<?= esc(base_url('register.php')); ?>">
                    <i class="fa-solid fa-user-plus"></i>
                    <span><?= t('join_the_union_now', $translations); ?></span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>