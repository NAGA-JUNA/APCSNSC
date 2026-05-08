<?php
require_once __DIR__ . '/../db.php';
require_admin();

$period = strtolower((string)($_GET['period'] ?? 'all'));
$period = in_array($period, ['all', 'today', 'week', 'month', 'year'], true) ? $period : 'all';

$dateCondition = '';
if ($period === 'today') {
    $dateCondition = ' AND DATE(pt.transaction_date) = CURDATE()';
} elseif ($period === 'week') {
    $dateCondition = ' AND pt.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
} elseif ($period === 'month') {
    $dateCondition = ' AND pt.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
} elseif ($period === 'year') {
    $dateCondition = ' AND YEAR(pt.transaction_date) = YEAR(CURDATE())';
}

if ((string)($_GET['export'] ?? '') === 'district') {
    $rows = fetch_all(
        'SELECT COALESCE(m.district, "Unknown") AS district,
                COUNT(pt.id) AS transactions,
                COALESCE(SUM(pt.amount), 0) AS revenue
         FROM payment_transactions pt
         LEFT JOIN members m ON pt.member_id = m.id
         WHERE pt.payment_status = "approved"' . $dateCondition . '
         GROUP BY COALESCE(m.district, "Unknown")
         ORDER BY revenue DESC'
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="apcsn_district_revenue_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['District', 'Transactions', 'Revenue']);
    foreach ($rows as $row) {
        fputcsv($output, [$row['district'] ?? '', $row['transactions'] ?? 0, $row['revenue'] ?? 0]);
    }
    fclose($output);
    exit;
}

$stats = fetch_one(
    'SELECT
        COUNT(*) AS total_transactions,
        COUNT(CASE WHEN pt.payment_status = "approved" THEN 1 END) AS approved_count,
        COUNT(CASE WHEN pt.payment_status = "pending" THEN 1 END) AS pending_count,
        COUNT(CASE WHEN pt.payment_status = "failed" THEN 1 END) AS failed_count,
        COALESCE(SUM(CASE WHEN pt.payment_status = "approved" THEN pt.amount ELSE 0 END), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN pt.payment_status = "pending" THEN pt.amount ELSE 0 END), 0) AS pending_revenue,
        AVG(CASE WHEN pt.payment_status = "approved" THEN pt.amount ELSE NULL END) AS avg_transaction
     FROM payment_transactions pt
     WHERE 1=1' . $dateCondition
);

$dailyRevenue = fetch_all(
    'SELECT DATE(pt.transaction_date) AS report_date,
            COUNT(pt.id) AS transactions,
            COALESCE(SUM(pt.amount), 0) AS revenue
     FROM payment_transactions pt
     WHERE pt.payment_status = "approved"' . $dateCondition . '
     GROUP BY DATE(pt.transaction_date)
     ORDER BY report_date DESC
     LIMIT 31'
);

$monthlyRevenue = fetch_all(
    'SELECT DATE_FORMAT(pt.transaction_date, "%Y-%m") AS report_month,
            COUNT(pt.id) AS transactions,
            COALESCE(SUM(pt.amount), 0) AS revenue
     FROM payment_transactions pt
     WHERE pt.payment_status = "approved"
     GROUP BY DATE_FORMAT(pt.transaction_date, "%Y-%m")
     ORDER BY report_month DESC
     LIMIT 12'
);

$districtRevenue = fetch_all(
    'SELECT COALESCE(m.district, "Unknown") AS district,
            COUNT(pt.id) AS transactions,
            COALESCE(SUM(pt.amount), 0) AS revenue
     FROM payment_transactions pt
     LEFT JOIN members m ON pt.member_id = m.id
     WHERE pt.payment_status = "approved"' . $dateCondition . '
     GROUP BY COALESCE(m.district, "Unknown")
     ORDER BY revenue DESC'
);

$expiredMembers = fetch_all(
    'SELECT m.member_id, m.full_name, m.district, m.phone, m.membership_expiry_date
     FROM members m
     WHERE m.membership_status = "expired"
     ORDER BY m.membership_expiry_date DESC
     LIMIT 100'
);

$pageTitle = 'Payment Reports';
$activeMenu = 'payments';
require_once __DIR__ . '/_top.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/payment-system.css')); ?>">

<section class="admin-card" style="margin-bottom: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="mb-1">Payment Reports & Analytics</h3>
            <p class="mb-0 text-secondary">Daily, monthly, district-wise revenue and expiry reports.</p>
        </div>
        <div class="quick-actions">
            <a class="quick-btn <?= $period === 'all' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/payment_reports.php?period=all')); ?>">All Time</a>
            <a class="quick-btn <?= $period === 'year' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/payment_reports.php?period=year')); ?>">This Year</a>
            <a class="quick-btn <?= $period === 'month' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/payment_reports.php?period=month')); ?>">30 Days</a>
            <a class="quick-btn <?= $period === 'week' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/payment_reports.php?period=week')); ?>">7 Days</a>
            <a class="quick-btn <?= $period === 'today' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/payment_reports.php?period=today')); ?>">Today</a>
            <a class="quick-btn" href="<?= esc(base_url('admin/payment_reports.php?period=' . urlencode($period) . '&export=district')); ?>"><i class="fa-solid fa-download me-1"></i>Export District</a>
            <a class="quick-btn" href="<?= esc(base_url('admin/payments.php')); ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
</section>

<section class="admin-grid stats" style="margin-bottom: 12px;">
    <article class="metric-card card-income">
        <div class="metric-head"><p class="metric-label">Total Revenue</p><span class="metric-icon"><i class="fa-solid fa-indian-rupee-sign"></i></span></div>
        <p class="metric-value">₹<?= esc(number_format((float)($stats['total_revenue'] ?? 0), 2)); ?></p>
    </article>
    <article class="metric-card">
        <div class="metric-head"><p class="metric-label">Approved Transactions</p><span class="metric-icon"><i class="fa-solid fa-circle-check"></i></span></div>
        <p class="metric-value"><?= esc(number_format((int)($stats['approved_count'] ?? 0))); ?></p>
    </article>
    <article class="metric-card card-alert-warning">
        <div class="metric-head"><p class="metric-label">Pending Revenue</p><span class="metric-icon"><i class="fa-solid fa-hourglass-half"></i></span></div>
        <p class="metric-value">₹<?= esc(number_format((float)($stats['pending_revenue'] ?? 0), 2)); ?></p>
    </article>
    <article class="metric-card card-alert-danger">
        <div class="metric-head"><p class="metric-label">Failed Payments</p><span class="metric-icon"><i class="fa-solid fa-circle-xmark"></i></span></div>
        <p class="metric-value"><?= esc(number_format((int)($stats['failed_count'] ?? 0))); ?></p>
    </article>
</section>

<section class="admin-layout-2" style="margin-bottom: 12px;">
    <article class="admin-card">
        <h4 style="margin-bottom: 16px;">Daily Revenue</h4>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Date</th><th>Transactions</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php if (empty($dailyRevenue)): ?>
                    <tr><td colspan="3" class="text-secondary">No data for selected period.</td></tr>
                <?php else: ?>
                    <?php foreach ($dailyRevenue as $row): ?>
                        <tr>
                            <td><?= esc(date('d M Y', strtotime((string)$row['report_date']))); ?></td>
                            <td><?= esc(number_format((int)$row['transactions'])); ?></td>
                            <td><strong style="color:#10a860;">₹<?= esc(number_format((float)$row['revenue'], 2)); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="admin-card">
        <h4 style="margin-bottom: 16px;">Monthly Revenue</h4>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Month</th><th>Transactions</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php if (empty($monthlyRevenue)): ?>
                    <tr><td colspan="3" class="text-secondary">No monthly data found.</td></tr>
                <?php else: ?>
                    <?php foreach ($monthlyRevenue as $row): ?>
                        <tr>
                            <td><?= esc(date('M Y', strtotime((string)$row['report_month'] . '-01'))); ?></td>
                            <td><?= esc(number_format((int)$row['transactions'])); ?></td>
                            <td><strong style="color:#10a860;">₹<?= esc(number_format((float)$row['revenue'], 2)); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="admin-layout-2" style="margin-bottom: 12px;">
    <article class="admin-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">District Wise Revenue</h4>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>District</th><th>Transactions</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php if (empty($districtRevenue)): ?>
                    <tr><td colspan="3" class="text-secondary">No district data found.</td></tr>
                <?php else: ?>
                    <?php foreach ($districtRevenue as $row): ?>
                        <tr>
                            <td><?= esc((string)$row['district']); ?></td>
                            <td><?= esc(number_format((int)$row['transactions'])); ?></td>
                            <td><strong style="color:#10a860;">₹<?= esc(number_format((float)$row['revenue'], 2)); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="admin-card">
        <h4 style="margin-bottom: 16px;">Expired Members Report</h4>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Member</th><th>ID</th><th>District</th><th>Phone</th><th>Expired On</th></tr></thead>
                <tbody>
                <?php if (empty($expiredMembers)): ?>
                    <tr><td colspan="5" class="text-secondary">No expired members found.</td></tr>
                <?php else: ?>
                    <?php foreach ($expiredMembers as $member): ?>
                        <tr>
                            <td><?= esc((string)$member['full_name']); ?></td>
                            <td><?= esc((string)$member['member_id']); ?></td>
                            <td><?= esc((string)$member['district']); ?></td>
                            <td><?= esc((string)$member['phone']); ?></td>
                            <td><?= esc(date('d M Y', strtotime((string)$member['membership_expiry_date']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<?php require_once __DIR__ . '/_bottom.php'; ?>
