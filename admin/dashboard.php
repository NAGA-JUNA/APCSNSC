<?php

require_once __DIR__ . '/../db.php';
require_admin();

$isDistrictScoped = is_district_president() && admin_district() !== '';
$scopeDistrict = admin_district();

$scopeWhere = '';
$scopeParams = [];
if ($isDistrictScoped) {
    $scopeWhere = ' WHERE district = :scope_district';
    $scopeParams[':scope_district'] = $scopeDistrict;
}

$totalMembers = (int)(fetch_one('SELECT COUNT(*) AS total FROM members' . $scopeWhere, $scopeParams)['total'] ?? 0);
$activeNurses = (int)(fetch_one("SELECT COUNT(*) AS total FROM members" . $scopeWhere . ($isDistrictScoped ? ' AND' : ' WHERE') . " status IN ('approved','active')", $scopeParams)['total'] ?? 0);
$pendingComplaints = (int)(fetch_one("SELECT COUNT(*) AS total FROM complaints" . ($isDistrictScoped ? ' WHERE district = :scope_district AND status = \'pending\'' : " WHERE status = 'pending'"), $scopeParams)['total'] ?? 0);
$districtsCovered = (int)(fetch_one('SELECT COUNT(DISTINCT district) AS total FROM members' . $scopeWhere, $scopeParams)['total'] ?? 0);
$newRegistrations = (int)(fetch_one('SELECT COUNT(*) AS total FROM members' . $scopeWhere . ($isDistrictScoped ? ' AND' : ' WHERE') . ' DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)', $scopeParams)['total'] ?? 0);

// Payment stats
$paidMembers = (int)(fetch_one("SELECT COUNT(*) AS total FROM members" . $scopeWhere . ($isDistrictScoped ? ' AND' : ' WHERE') . " payment_status = 'paid' AND membership_status = 'active'", $scopeParams)['total'] ?? 0);
$unpaidMembers = (int)(fetch_one("SELECT COUNT(*) AS total FROM members" . $scopeWhere . ($isDistrictScoped ? ' AND' : ' WHERE') . " payment_status IN ('unpaid', 'pending')", $scopeParams)['total'] ?? 0);
$expiredMembers = (int)(fetch_one("SELECT COUNT(*) AS total FROM members" . $scopeWhere . ($isDistrictScoped ? ' AND' : ' WHERE') . " membership_status = 'expired'", $scopeParams)['total'] ?? 0);
$renewalDueMembers = (int)(fetch_one("SELECT COUNT(*) AS total FROM members" . $scopeWhere . ($isDistrictScoped ? ' AND' : ' WHERE') . " membership_status = 'renewal_due'", $scopeParams)['total'] ?? 0);
$totalComplaints = (int)(fetch_one('SELECT COUNT(*) AS total FROM complaints' . ($isDistrictScoped ? ' WHERE district = :scope_district' : ''), $scopeParams)['total'] ?? 0);
$resolvedComplaints = (int)(fetch_one("SELECT COUNT(*) AS total FROM complaints" . ($isDistrictScoped ? " WHERE district = :scope_district AND status = 'resolved'" : " WHERE status = 'resolved'"), $scopeParams)['total'] ?? 0);
$totalRevenue = fetch_one('SELECT COALESCE(SUM(pt.amount), 0) AS total FROM payment_transactions pt LEFT JOIN members m ON pt.member_id = m.id WHERE pt.payment_status = "approved"' . ($isDistrictScoped ? ' AND m.district = :scope_district' : ''), $scopeParams);
$totalRevenueAmount = (float)($totalRevenue['total'] ?? 0);
$pendingPayments = (int)(fetch_one("SELECT COUNT(*) AS total FROM payment_transactions pt LEFT JOIN members m ON pt.member_id = m.id WHERE pt.payment_status = 'pending'" . ($isDistrictScoped ? ' AND m.district = :scope_district' : ''), $scopeParams)['total'] ?? 0);

$recentMembers = fetch_all('SELECT id, name, member_id, district, phone, status, created_at FROM members' . $scopeWhere . ' ORDER BY created_at DESC LIMIT 8', $scopeParams);
$latestComplaints = fetch_all('SELECT id, name, district, issue, status, created_at FROM complaints' . ($isDistrictScoped ? ' WHERE district = :scope_district' : '') . ' ORDER BY created_at DESC LIMIT 8', $scopeParams);
$districtAnalytics = fetch_all(
    'SELECT CASE WHEN district IS NULL OR district = "" THEN "Unassigned" ELSE district END AS district_name, COUNT(*) AS total
     FROM members
     ' . ($isDistrictScoped ? 'WHERE district = :scope_district ' : '') . ' 
     GROUP BY CASE WHEN district IS NULL OR district = "" THEN "Unassigned" ELSE district END
     ORDER BY total DESC
     LIMIT 6',
    $scopeParams
);

$chartRows = fetch_all(
    'SELECT DATE_FORMAT(created_at, "%b") AS month_name, COUNT(*) AS total
     FROM members
     ' . ($isDistrictScoped ? 'WHERE district = :scope_district AND' : 'WHERE') . ' created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, "%Y-%m")
     ORDER BY DATE_FORMAT(created_at, "%Y-%m") ASC',
    $scopeParams
);

$chartLabels = [];
$chartValues = [];
foreach ($chartRows as $row) {
    $chartLabels[] = (string)($row['month_name'] ?? '');
    $chartValues[] = (int)($row['total'] ?? 0);
}

$notifications = [
    [
        'label' => 'Pending complaints',
        'value' => $pendingComplaints,
        'url' => base_url('admin/complaints.php?status_filter=pending')
    ],
    [
        'label' => 'New registrations (30 days)',
        'value' => $newRegistrations,
        'url' => base_url('admin/members.php')
    ],
    [
        'label' => 'Active nurses total',
        'value' => $activeNurses,
        'url' => base_url('admin/members.php?status=active')
    ],
    [
        'label' => 'Paid members',
        'value' => $paidMembers,
        'url' => base_url('admin/members.php')
    ],
    [
        'label' => 'Pending payments',
        'value' => $pendingPayments,
        'url' => base_url('admin/payment_approvals.php')
    ],
    [
        'label' => 'Renewal due',
        'value' => $renewalDueMembers,
        'url' => base_url('admin/payment_renewals.php')
    ],
];

$quickActions = [
    ['label' => 'Add Member', 'icon' => 'fa-user-plus', 'url' => base_url('admin/add_member.php'), 'tone' => 'blue'],
    ['label' => 'Members', 'icon' => 'fa-users', 'url' => base_url('admin/members.php'), 'tone' => 'indigo'],
    ['label' => 'Complaints', 'icon' => 'fa-comment-dots', 'url' => base_url('admin/complaints.php'), 'tone' => 'orange'],
    ['label' => 'Payments', 'icon' => 'fa-credit-card', 'url' => base_url('admin/payments.php'), 'tone' => 'emerald'],
    ['label' => 'Reports', 'icon' => 'fa-file-export', 'url' => base_url('admin/reports.php'), 'tone' => 'slate'],
];

$kpiCards = [
    ['label' => 'Total Members', 'value' => $totalMembers, 'icon' => 'fa-users', 'tone' => 'blue', 'note' => 'Registered members'],
    ['label' => 'Paid Members', 'value' => $paidMembers, 'icon' => 'fa-user-check', 'tone' => 'emerald', 'note' => 'Active and settled'],
    ['label' => 'Unpaid Members', 'value' => $unpaidMembers, 'icon' => 'fa-user-clock', 'tone' => 'red', 'note' => 'Needs follow-up'],
    ['label' => 'Renewal Due', 'value' => $renewalDueMembers, 'icon' => 'fa-bell', 'tone' => 'amber', 'note' => 'Upcoming renewals'],
    ['label' => 'Expired Members', 'value' => $expiredMembers, 'icon' => 'fa-calendar-times', 'tone' => 'slate', 'note' => 'Expired memberships'],
    ['label' => 'Revenue Collected', 'value' => '₹' . number_format($totalRevenueAmount, 2), 'icon' => 'fa-rupee-sign', 'tone' => 'blue', 'note' => 'Approved payments'],
    ['label' => 'Pending Approvals', 'value' => $pendingPayments, 'icon' => 'fa-hourglass-half', 'tone' => 'amber', 'note' => 'Awaiting approval'],
    ['label' => 'Districts Covered', 'value' => $districtsCovered, 'icon' => 'fa-map-location-dot', 'tone' => 'indigo', 'note' => 'Operational reach'],
];

$paymentAnalytics = [
    ['label' => 'Paid', 'value' => $paidMembers, 'accent' => 'success', 'percent' => $totalMembers > 0 ? round(($paidMembers / $totalMembers) * 100) : 0],
    ['label' => 'Unpaid', 'value' => $unpaidMembers, 'accent' => 'danger', 'percent' => $totalMembers > 0 ? round(($unpaidMembers / $totalMembers) * 100) : 0],
    ['label' => 'Renewal Due', 'value' => $renewalDueMembers, 'accent' => 'warning', 'percent' => $totalMembers > 0 ? round(($renewalDueMembers / $totalMembers) * 100) : 0],
    ['label' => 'Expired', 'value' => $expiredMembers, 'accent' => 'muted', 'percent' => $totalMembers > 0 ? round(($expiredMembers / $totalMembers) * 100) : 0],
];

$complaintOverview = [
    ['label' => 'Open Queue', 'value' => $pendingComplaints, 'accent' => 'warning'],
    ['label' => 'Resolved', 'value' => $resolvedComplaints, 'accent' => 'success'],
    ['label' => 'Total Complaints', 'value' => $totalComplaints, 'accent' => 'blue'],
];

$scopeLabel = $isDistrictScoped && $scopeDistrict !== '' ? $scopeDistrict : 'Statewide';

$pageTitle = 'Dashboard Home';
$activeMenu = 'dashboard';
$bodyClass = 'admin-dashboard-page';
$hideAdminPageTitle = true;
$dashCssVer = file_exists(__DIR__ . '/../assets/css/admin-dashboard.css') ? (string)filemtime(__DIR__ . '/../assets/css/admin-dashboard.css') : (string)time();
$pageStyles = [base_url('assets/css/admin-dashboard.css?v=' . $dashCssVer)];
require_once __DIR__ . '/_top.php';
?>

<div class="dashboard-app">
    <header class="admin-card dashboard-hero">
        <div class="dashboard-hero-copy">
            <span class="dashboard-kicker">APCSNSC Command Center</span>
            <h1>Welcome back, <?= esc((string)($_SESSION['admin_username'] ?? 'Admin')); ?> <span aria-hidden="true">👋</span></h1>
            <p>Monitor membership growth, complaint flow, payment health, and district operations from one premium control surface.</p>
            <div class="dashboard-hero-meta">
                <span><i class="fa-solid fa-shield-heart"></i><?= esc($scopeLabel); ?> scope</span>
                <span><i class="fa-solid fa-circle-nodes"></i>Live analytics</span>
                <span><i class="fa-solid fa-bolt"></i>Mobile-first interface</span>
            </div>
        </div>
        <div class="dashboard-hero-visual">
            <div class="dashboard-hero-glass">
                <span class="dashboard-live-pill"><span class="dashboard-pulse"></span>System healthy</span>
                <strong><?= esc(number_format($totalMembers)); ?></strong>
                <p>members under active management</p>
                <div class="dashboard-hero-mini-grid">
                    <div><span>Revenue</span><strong>₹<?= esc(number_format($totalRevenueAmount, 2)); ?></strong></div>
                    <div><span>Complaints</span><strong><?= esc(number_format($pendingComplaints)); ?></strong></div>
                    <div><span>Districts</span><strong><?= esc(number_format($districtsCovered)); ?></strong></div>
                </div>
            </div>
        </div>
    </header>

    <section class="admin-card dashboard-actions-card">
        <div class="dashboard-section-head">
            <div>
                <h2>Quick actions</h2>
                <p>Common operations optimized for touch and keyboard alike.</p>
            </div>
            <span class="dashboard-live-pill soft"><span class="dashboard-pulse"></span>Swipe</span>
        </div>
        <div class="dashboard-action-strip" aria-label="Quick actions">
            <?php foreach ($quickActions as $action): ?>
                <a class="dashboard-action-card tone-<?= esc($action['tone']); ?>" href="<?= esc($action['url']); ?>">
                    <span class="dashboard-action-icon"><i class="fa-solid <?= esc($action['icon']); ?>"></i></span>
                    <strong><?= esc($action['label']); ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="dashboard-section-label"><span>Overview</span><a class="dashboard-link" href="<?= esc(base_url('admin/reports.php')); ?>">View All</a></div>
    <section class="dashboard-kpi-grid" aria-label="Key performance indicators">
        <?php foreach ($kpiCards as $card): ?>
            <article class="dashboard-kpi-card tone-<?= esc($card['tone']); ?>">
                <div class="metric-head dashboard-kpi-head">
                    <p class="metric-label"><?= esc($card['label']); ?></p>
                    <span class="metric-icon"><i class="fa-solid <?= esc($card['icon']); ?>"></i></span>
                </div>
                <p class="metric-value"><?= esc((string)$card['value']); ?></p>
                <span class="dashboard-kpi-note"><?= esc($card['note']); ?></span>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="dashboard-grid-2">
        <article class="admin-card dashboard-panel dashboard-chart-panel">
            <div class="dashboard-section-head">
                <div>
                    <h2>Membership growth</h2>
                    <p>Last 6 months trend with a live Chart.js line.</p>
                </div>
                <span class="dashboard-chip">6M</span>
            </div>
            <div class="dashboard-chart-wrap">
                <canvas id="membershipGrowthChart" height="150"></canvas>
            </div>
        </article>

        <article class="admin-card dashboard-panel dashboard-notification-panel">
            <div class="dashboard-section-head">
                <div>
                    <h2>Notifications</h2>
                    <p>Operational alerts and shortcuts.</p>
                </div>
                <a class="dashboard-link" href="<?= esc(base_url('admin/reports.php')); ?>">View all</a>
            </div>
            <ul class="dashboard-notification-list">
                <?php foreach ($notifications as $item): ?>
                    <li>
                        <a href="<?= esc($item['url']); ?>">
                            <span><?= esc($item['label']); ?></span>
                            <strong><?= esc((string)$item['value']); ?></strong>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </article>
    </section>

    <section class="dashboard-grid-2 dashboard-grid-2-tight">
        <article class="admin-card dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <h2>Recent registrations</h2>
                    <p>Latest members with mobile-friendly card fallback.</p>
                </div>
                <a class="dashboard-link" href="<?= esc(base_url('admin/members.php')); ?>">View all</a>
            </div>

            <div class="d-none d-lg-block table-responsive dashboard-table-wrap">
                <table class="table table-hover align-middle mb-0 dashboard-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Member ID</th>
                            <th>District</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentMembers as $member): ?>
                            <?php $status = strtolower((string)($member['status'] ?? 'pending')); ?>
                            <tr>
                                <td>
                                    <div class="dashboard-table-name">
                                        <span class="dashboard-avatar"><?= esc(strtoupper(substr((string)$member['name'], 0, 2))); ?></span>
                                        <strong><?= esc((string)$member['name']); ?></strong>
                                    </div>
                                </td>
                                <td><?= esc((string)$member['member_id']); ?></td>
                                <td><?= esc((string)$member['district']); ?></td>
                                <td><?= esc((string)$member['phone']); ?></td>
                                <td><span class="badge-soft <?= $status === 'approved' || $status === 'active' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning'); ?>"><?= esc(ucfirst($status)); ?></span></td>
                                <td><?= esc(date('d M Y', strtotime((string)$member['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-lg-none dashboard-mobile-stack">
                <?php foreach ($recentMembers as $member): ?>
                    <?php $status = strtolower((string)($member['status'] ?? 'pending')); ?>
                    <div class="dashboard-mobile-row">
                        <div class="dashboard-mobile-row-main">
                            <span class="dashboard-avatar"><?= esc(strtoupper(substr((string)$member['name'], 0, 2))); ?></span>
                            <div>
                                <strong><?= esc((string)$member['name']); ?></strong>
                                <p><?= esc((string)$member['member_id']); ?> • <?= esc((string)$member['district']); ?></p>
                                <small><?= esc((string)$member['phone']); ?></small>
                            </div>
                        </div>
                        <div class="dashboard-mobile-row-meta">
                            <span class="badge-soft <?= $status === 'approved' || $status === 'active' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning'); ?>"><?= esc(ucfirst($status)); ?></span>
                            <small><?= esc(date('d M Y', strtotime((string)$member['created_at']))); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="admin-card dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <h2>Complaint management</h2>
                    <p>Open queue and latest issues in one view.</p>
                </div>
                <a class="dashboard-link" href="<?= esc(base_url('admin/complaints.php')); ?>">Open queue</a>
            </div>
            <div class="dashboard-complaint-summary">
                <?php foreach ($complaintOverview as $item): ?>
                    <div class="dashboard-stat-pill tone-<?= esc($item['accent']); ?>">
                        <span><?= esc($item['label']); ?></span>
                        <strong><?= esc((string)$item['value']); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <ul class="dashboard-complaint-list">
                <?php foreach ($latestComplaints as $complaint): ?>
                    <?php $cStatus = strtolower((string)($complaint['status'] ?? 'pending')); ?>
                    <li>
                        <div class="dashboard-complaint-row-main">
                            <span class="dashboard-complaint-icon <?= $cStatus === 'resolved' ? 'resolved' : 'pending'; ?>"><i class="fa-solid fa-comment-dots"></i></span>
                            <div>
                                <strong><?= esc((string)$complaint['issue']); ?></strong>
                                <p><?= esc((string)$complaint['name']); ?> • <?= esc((string)$complaint['district']); ?></p>
                                <small><?= esc(date('d M Y, g:i A', strtotime((string)$complaint['created_at']))); ?></small>
                            </div>
                        </div>
                        <div class="dashboard-complaint-meta">
                            <span class="badge-soft <?= $cStatus === 'resolved' ? 'success' : 'warning'; ?>"><?= esc(ucfirst($cStatus)); ?></span>
                            <i class="fa-solid fa-chevron-right dashboard-complaint-arrow"></i>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </article>
    </section>

    <section class="dashboard-grid-2 dashboard-grid-2-tight">
        <article class="admin-card dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <h2>Payment analytics</h2>
                    <p>Payment health across membership states.</p>
                </div>
                <span class="dashboard-chip">Analytics</span>
            </div>
            <div class="dashboard-progress-list">
                <?php foreach ($paymentAnalytics as $item): ?>
                    <div class="dashboard-progress-item">
                        <div class="dashboard-progress-head">
                            <span><?= esc($item['label']); ?></span>
                            <strong><?= esc((string)$item['value']); ?></strong>
                        </div>
                        <div class="progress dashboard-progress">
                            <div class="progress-bar bg-<?= esc($item['accent']); ?>" role="progressbar" style="width: <?= esc((string)$item['percent']); ?>%" aria-valuenow="<?= esc((string)$item['percent']); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="admin-card dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <h2>District analytics</h2>
                    <p>Top-performing districts by member count.</p>
                </div>
                <span class="dashboard-chip">Top 6</span>
            </div>
            <div class="dashboard-district-list">
                <?php foreach ($districtAnalytics as $district): ?>
                    <?php $districtName = (string)($district['district_name'] ?? 'Unassigned'); $districtTotal = (int)($district['total'] ?? 0); ?>
                    <div class="dashboard-district-item">
                        <div class="dashboard-progress-head">
                            <span><?= esc($districtName); ?></span>
                            <strong><?= esc(number_format($districtTotal)); ?></strong>
                        </div>
                        <div class="progress dashboard-progress">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= esc((string)($totalMembers > 0 ? round(($districtTotal / $totalMembers) * 100) : 0)); ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>
</div>

<a class="dashboard-fab" href="<?= esc(base_url('admin/add_member.php')); ?>" aria-label="Add member">
    <i class="fa-solid fa-user-plus"></i>
</a>

<nav class="dashboard-bottom-nav" aria-label="Quick navigation">
    <a class="active" href="<?= esc(base_url('admin/dashboard.php')); ?>"><i class="fa-solid fa-chart-line"></i><span>Dashboard</span></a>
    <a href="<?= esc(base_url('admin/members.php')); ?>"><i class="fa-solid fa-users"></i><span>Members</span></a>
    <a href="<?= esc(base_url('admin/complaints.php')); ?>"><i class="fa-regular fa-message"></i><span>Complaints</span></a>
    <a href="<?= esc(base_url('admin/payments.php')); ?>"><i class="fa-solid fa-credit-card"></i><span>Payments</span></a>
    <a href="<?= esc(base_url('admin/reports.php')); ?>"><i class="fa-solid fa-ellipsis"></i><span>More</span></a>
</nav>

<script>
    (function () {
        var canvas = document.getElementById('membershipGrowthChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Registrations',
                    data: <?= json_encode($chartValues); ?>,
                    borderColor: '#0d47a1',
                    backgroundColor: 'rgba(13, 71, 161, 0.14)',
                    fill: true,
                    tension: 0.34,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: '#1565c0'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    })();
</script>

<?php require_once __DIR__ . '/_bottom.php'; ?>
