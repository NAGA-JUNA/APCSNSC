<?php
require_once __DIR__ . '/../db.php';
require_admin();
$pageTitle = $pageTitle ?? 'Admin Panel';
$activeMenu = $activeMenu ?? 'dashboard';
$cssFile = __DIR__ . '/../assets/css/admin-panel.css';
$cssVer = file_exists($cssFile) ? (string)filemtime($cssFile) : (string)time();
$adminName = $_SESSION['admin_username'] ?? 'Admin';
$adminInitial = strtoupper(substr((string)$adminName, 0, 1));
$adminRoleLabel = admin_role_label();
$adminScopeDistrict = admin_district();
$isSuperAdmin = is_super_admin();
$today = date('d M Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle); ?> - APCSNSC Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/admin-panel.css?v=' . $cssVer)); ?>">
    <?php if (!empty($pageStyles) && is_array($pageStyles)): ?>
        <?php foreach ($pageStyles as $styleHref): ?>
            <link rel="stylesheet" href="<?= esc((string)$styleHref); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="admin-page <?= esc(trim((string)($bodyClass ?? ''))); ?>">
<div class="admin-loader" id="adminLoader" aria-hidden="true">
    <div class="admin-loader-spinner"></div>
</div>

<div class="admin-shell" id="adminShell">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-brand-wrap">
            <div class="admin-brand-mark"><i class="fa-solid fa-staff-snake"></i></div>
            <div class="admin-brand">
                <h2>APCSNSC</h2>
                <p>Admin Panel</p>
            </div>
        </div>

        <nav>
            <a class="<?= $activeMenu === 'dashboard' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/dashboard.php')); ?>"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><span>Dashboard</span></a>
            <a class="<?= $activeMenu === 'members' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/members.php')); ?>"><i class="fa-solid fa-users" aria-hidden="true"></i><span>Members</span></a>
            <a class="<?= $activeMenu === 'add-member' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/add_member.php')); ?>"><i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>Add Member</span></a>
            <a class="<?= $activeMenu === 'complaints' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/complaints.php')); ?>"><i class="fa-regular fa-message" aria-hidden="true"></i><span>Complaints</span></a>
            <a class="<?= $activeMenu === 'districts' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/districts.php')); ?>"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Districts</span></a>
            <a class="<?= $activeMenu === 'district-committees' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/district_committees.php')); ?>"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i><span>District Committees</span></a>
            <a class="<?= $activeMenu === 'media' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/media.php')); ?>"><i class="fa-solid fa-photo-film" aria-hidden="true"></i><span>Media</span></a>
            <a class="<?= $activeMenu === 'updates' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/updates.php')); ?>"><i class="fa-regular fa-newspaper" aria-hidden="true"></i><span>Updates</span></a>
            <a class="<?= $activeMenu === 'homepage-showcase' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/homepage_showcase.php')); ?>"><i class="fa-solid fa-panorama" aria-hidden="true"></i><span>Benefits, Counters &amp; News</span></a>
            <a class="<?= $activeMenu === 'showcase-sections' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/showcase_sections.php')); ?>"><i class="fa-solid fa-tv" aria-hidden="true"></i><span>Media &amp; Updates Section</span></a>
            <?php if ($isSuperAdmin): ?>
            <a class="<?= $activeMenu === 'hero' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/hero.php')); ?>"><i class="fa-solid fa-star" aria-hidden="true"></i><span>Hero Section</span></a>
            <?php endif; ?>
            <?php if ($isSuperAdmin): ?>
            <a class="<?= $activeMenu === 'card-templates' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/card_templates.php')); ?>"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>Card Templates</span></a>
            <?php endif; ?>
            <a class="<?= $activeMenu === 'id-cards' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/id_cards.php')); ?>"><i class="fa-solid fa-id-card" aria-hidden="true"></i><span>ID Cards</span></a>
            <a class="<?= $activeMenu === 'reports' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/reports.php')); ?>"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Reports</span></a>
            <?php if ($isSuperAdmin): ?>
            <a class="<?= $activeMenu === 'settings' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/settings.php')); ?>"><i class="fa-solid fa-gear" aria-hidden="true"></i><span>Settings</span></a>
            <?php endif; ?>
            <a href="<?= esc(base_url('admin/logout.php')); ?>"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Logout</span></a>
        </nav>

        <div class="admin-sidebar-user">
            <span class="admin-user-avatar"><?= esc($adminInitial); ?></span>
            <div>
                <strong><?= esc((string)$adminName); ?></strong>
                <small>
                    <?= esc($adminRoleLabel); ?>
                    <?= $adminScopeDistrict !== '' ? ' - ' . esc($adminScopeDistrict) : ''; ?>
                </small>
            </div>
        </div>
    </aside>

    <section class="admin-content">
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button class="admin-menu-toggle" id="adminMenuToggle" type="button" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <form class="admin-topbar-search" role="search" onsubmit="return false;">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" aria-label="Search admin" placeholder="Search members, complaints, districts...">
                </form>
            </div>

            <div class="admin-topbar-actions">
                <span class="admin-clock" id="adminClock" data-time-format="h12">--:--:--</span>
                <span class="admin-date"><?= esc($today); ?></span>
                <button type="button" class="topbar-icon-btn" id="darkModeToggle" aria-label="Toggle dark mode"><i class="fa-solid fa-moon"></i></button>
                <div class="dropdown">
                    <button class="topbar-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="fa-regular fa-bell"></i>
                        <span class="notif-dot"></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="<?= esc(base_url('admin/complaints.php')); ?>">New complaint requires attention</a></li>
                        <li><a class="dropdown-item" href="<?= esc(base_url('admin/members.php')); ?>">New member registration pending</a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="admin-profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="admin-profile-avatar"><?= esc($adminInitial); ?></span>
                        <span class="admin-profile-name"><?= esc((string)$adminName); ?></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown">
                        <?php if ($isSuperAdmin): ?>
                        <li><a class="dropdown-item" href="<?= esc(base_url('admin/settings.php')); ?>">Admin Profile</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= esc(base_url('index.php')); ?>" target="_blank" rel="noopener">View Website</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= esc(base_url('admin/logout.php')); ?>">Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <?php if (empty($hideAdminPageTitle)): ?>
            <div class="admin-page-title">
                <h1><?= esc($pageTitle); ?></h1>
                <p>APCSNSC Government-grade operational dashboard.</p>
            </div>
        <?php endif; ?>