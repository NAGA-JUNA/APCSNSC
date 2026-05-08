<?php
require_once __DIR__ . '/db.php';
$lang = current_lang();
$translations = get_translations();
$current = basename($_SERVER['PHP_SELF']);
$cssFile = __DIR__ . '/assets/css/style.css';
$cssVer = file_exists($cssFile) ? (string)filemtime($cssFile) : (string)time();

$logoCandidates = [
    'uploads/logo.png',
    'uploads/logo.jpg',
    'uploads/logo.jpeg',
    'uploads/APCSNSC-logo.png',
    'uploads/APCSNSC-logo.jpg',
    'uploads/APCSNSC-logo.jpeg',
    'uploads/site-logo.png',
    'uploads/site-logo.jpg',
    'uploads/site-logo.jpeg',
];
$siteLogo = null;
foreach ($logoCandidates as $candidate) {
    if (file_exists(__DIR__ . '/' . $candidate)) {
        $siteLogo = base_url($candidate);
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= esc($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('site_title', $translations); ?></title>
    <meta name="description" content="<?= t('site_description', $translations); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/style.css?v=' . $cssVer)); ?>">
    <?php if (!empty($pageStyles) && is_array($pageStyles)): ?>
        <?php foreach ($pageStyles as $styleHref): ?>
            <link rel="stylesheet" href="<?= esc((string)$styleHref); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="site-body">
<header class="site-header" id="top">
    <nav class="navbar container">
        <a class="brand" href="<?= esc(base_url('index.php')); ?>">
            <span class="brand-mark">
                <?php if ($siteLogo !== null): ?>
                    <img class="brand-logo-img" src="<?= esc($siteLogo); ?>" alt="APCSNSC logo">
                <?php else: ?>
                    <span class="brand-logo">A</span>
                <?php endif; ?>
            </span>
            <span>
                <strong>APCSNSC</strong>
                <small>Andhra Pradesh Contract Staff Nurses Struggle Committee</small>
            </span>
        </a>

        <a class="nav-join-btn" href="<?= esc(base_url('register.php')); ?>">
            <i class="fa-solid fa-user-plus"></i> Join Now
        </a>

        <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <div class="nav-overlay" id="navOverlay"></div>
        <div class="nav-wrap" id="navWrap">

            <!-- Drawer header — only visible on mobile -->
            <div class="nav-drawer-header">
                <div class="nav-drawer-brand">
                    <span class="nav-drawer-logo">
                        <?php if ($siteLogo !== null): ?>
                            <img src="<?= esc($siteLogo); ?>" alt="APCSNSC logo">
                        <?php else: ?>
                            <span>A</span>
                        <?php endif; ?>
                    </span>
                    <div>
                        <strong>APCSNSC</strong>
                        <small>Strength &middot; Unity &middot; Justice</small>
                    </div>
                </div>
                <button class="nav-drawer-close" id="navCloseBtn" aria-label="Close menu">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <ul class="nav-links">
                <li>
                    <a class="<?= $current === 'index.php' ? 'active' : ''; ?>" href="<?= esc(base_url('index.php')); ?>">
                        <span class="nav-link-icon"><i class="fa-solid fa-house"></i></span>
                        <?= t('home', $translations); ?>
                    </a>
                </li>
                <li>
                    <a class="<?= $current === 'about.php' ? 'active' : ''; ?>" href="<?= esc(base_url('pages/about.php')); ?>">
                        <span class="nav-link-icon"><i class="fa-solid fa-circle-info"></i></span>
                        <?= t('about', $translations); ?>
                    </a>
                </li>
                <li>
                    <a class="<?= $current === 'leadership.php' ? 'active' : ''; ?>" href="<?= esc(base_url('pages/leadership.php')); ?>">
                        <span class="nav-link-icon"><i class="fa-solid fa-users"></i></span>
                        <?= t('leadership', $translations); ?>
                    </a>
                </li>
                <li>
                    <a class="<?= $current === 'districts.php' ? 'active' : ''; ?>" href="<?= esc(base_url('pages/districts.php')); ?>">
                        <span class="nav-link-icon"><i class="fa-solid fa-location-dot"></i></span>
                        <?= t('districts', $translations); ?>
                    </a>
                </li>
                <li>
                    <a class="<?= $current === 'news.php' ? 'active' : ''; ?>" href="<?= esc(base_url('pages/news.php')); ?>">
                        <span class="nav-link-icon"><i class="fa-solid fa-newspaper"></i></span>
                        <?= t('news', $translations); ?>
                    </a>
                </li>
                <li>
                    <a class="<?= $current === 'contact.php' ? 'active' : ''; ?>" href="<?= esc(base_url('pages/contact.php')); ?>">
                        <span class="nav-link-icon"><i class="fa-solid fa-phone"></i></span>
                        <?= t('contact', $translations); ?>
                    </a>
                </li>
            </ul>

            <div class="nav-actions">
                <div class="lang-toggle">
                    <a class="<?= $lang === 'en' ? 'selected' : ''; ?>" href="?lang=en">EN</a>
                    <span>/</span>
                    <a class="<?= $lang === 'te' ? 'selected' : ''; ?>" href="?lang=te">తెలుగు</a>
                </div>
                <a class="btn btn-primary nav-login-btn" href="<?= esc(base_url('member_dashboard.php')); ?>">
                    <i class="fa-solid fa-user-shield"></i> <?= t('login', $translations); ?>
                </a>
            </div>

            <!-- Trust badges — only visible on mobile -->
            <div class="nav-trust-badges">
                <div class="nav-trust-item">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span class="nav-trust-title">Trust</span>
                    <span class="nav-trust-sub">Transparent</span>
                </div>
                <div class="nav-trust-item">
                    <i class="fa-solid fa-handshake"></i>
                    <span class="nav-trust-title">Unity</span>
                    <span class="nav-trust-sub">Stronger</span>
                </div>
                <div class="nav-trust-item">
                    <i class="fa-solid fa-scale-balanced"></i>
                    <span class="nav-trust-title">Justice</span>
                    <span class="nav-trust-sub">Fair for All</span>
                </div>
            </div>

        </div>
    </nav>
</header>
<main>