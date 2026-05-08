<?php
require_once __DIR__ . '/../db.php';
require_admin();

$masterPlan = get_master_membership_settings();

if (isset($_GET['toggle_plan']) && verify_csrf((string)($_GET['token'] ?? null))) {
    if ($masterPlan) {
        $newStatus = strtolower((string)($masterPlan['status'] ?? 'active')) === 'active' ? 'inactive' : 'active';
        execute_query('UPDATE membership_settings SET status = :status', [':status' => $newStatus]);
        set_flash('success', 'Master membership plan status updated to ' . strtoupper($newStatus) . '.');
    }
    redirect_to('admin/payments.php');
}

if ((string)($_GET['export'] ?? '') === 'revenue') {
    $rows = fetch_all(
        'SELECT pt.transaction_id, pt.transaction_date, m.member_id, m.full_name, m.district, pt.amount, pt.payment_mode, pt.payment_status
         FROM payment_transactions pt
         LEFT JOIN members m ON pt.member_id = m.id
         WHERE pt.payment_status = "approved"
         ORDER BY pt.transaction_date DESC'
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="apcsn_revenue_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Transaction ID', 'Date', 'Member ID', 'Member Name', 'District', 'Amount', 'Mode', 'Status']);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['transaction_id'] ?? '',
            $row['transaction_date'] ?? '',
            $row['member_id'] ?? '',
            $row['full_name'] ?? '',
            $row['district'] ?? '',
            $row['amount'] ?? 0,
            $row['payment_mode'] ?? '',
            $row['payment_status'] ?? '',
        ]);
    }
    fclose($output);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'unpaid') {
    $rows = fetch_all(
        'SELECT member_id, full_name, phone, district, membership_status, payment_status, membership_expiry_date
         FROM members
         WHERE payment_status IN ("unpaid", "pending") OR membership_status IN ("expired", "renewal_due")
         ORDER BY district ASC, full_name ASC'
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="apcsn_unpaid_members_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Member ID', 'Name', 'Phone', 'District', 'Membership Status', 'Payment Status', 'Expiry Date']);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['member_id'] ?? '',
            $row['full_name'] ?? '',
            $row['phone'] ?? '',
            $row['district'] ?? '',
            $row['membership_status'] ?? '',
            $row['payment_status'] ?? '',
            $row['membership_expiry_date'] ?? '',
        ]);
    }
    fclose($output);
    exit;
}

$totalTransactions = (int)(fetch_one('SELECT COUNT(*) AS total FROM payment_transactions')['total'] ?? 0);
$totalRevenueAmount = (float)(fetch_one('SELECT COALESCE(SUM(amount), 0) AS total FROM payment_transactions WHERE payment_status = "approved"')['total'] ?? 0);
$pendingApprovals = (int)(fetch_one("SELECT COUNT(*) AS total FROM payment_transactions WHERE payment_status = 'pending'")['total'] ?? 0);
$expiredMembers = (int)(fetch_one("SELECT COUNT(*) AS total FROM members WHERE membership_status = 'expired'")['total'] ?? 0);
$renewalsThisMonth = (int)(fetch_one('SELECT COUNT(*) AS total FROM payment_transactions WHERE is_renewal = 1 AND DATE_FORMAT(transaction_date, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m")')['total'] ?? 0);
$activeMembers = (int)(fetch_one("SELECT COUNT(*) AS total FROM members WHERE membership_status = 'active' AND payment_status = 'paid'")['total'] ?? 0);

$latestTxn = fetch_one('SELECT id FROM payment_transactions ORDER BY transaction_date DESC LIMIT 1');

$search = clean((string)($_GET['q'] ?? ''));
$district = clean((string)($_GET['district'] ?? ''));
$phone = preg_replace('/[^0-9]/', '', (string)($_GET['phone'] ?? ''));
$expiryFilter = strtolower((string)($_GET['expiry'] ?? 'all'));
$paymentFilter = strtolower((string)($_GET['payment'] ?? 'all'));
$validExpiry = ['all', 'this_month'];
$validPayment = ['all', 'unpaid', 'renewal_due', 'expired'];
$expiryFilter = in_array($expiryFilter, $validExpiry, true) ? $expiryFilter : 'all';
$paymentFilter = in_array($paymentFilter, $validPayment, true) ? $paymentFilter : 'all';

$memberWhere = ['1=1'];
$memberParams = [];

if ($search !== '') {
    $memberWhere[] = '(m.full_name LIKE :search OR m.member_id LIKE :search)';
    $memberParams[':search'] = '%' . $search . '%';
}
if ($district !== '') {
    $memberWhere[] = 'm.district = :district';
    $memberParams[':district'] = $district;
}
if ($phone !== '') {
    $memberWhere[] = 'm.phone LIKE :phone';
    $memberParams[':phone'] = '%' . $phone . '%';
}
if ($expiryFilter === 'this_month') {
    $memberWhere[] = 'm.membership_expiry_date IS NOT NULL AND DATE_FORMAT(m.membership_expiry_date, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m")';
}
if ($paymentFilter === 'unpaid') {
    $memberWhere[] = 'm.payment_status IN ("unpaid", "pending")';
}
if ($paymentFilter === 'renewal_due') {
    $memberWhere[] = 'm.membership_status = "renewal_due"';
}
if ($paymentFilter === 'expired') {
    $memberWhere[] = 'm.membership_status = "expired"';
}

$membersList = fetch_all(
    'SELECT m.id, m.member_id, m.full_name, m.phone, m.district, m.payment_status, m.membership_status, m.membership_expiry_date
     FROM members m
     WHERE ' . implode(' AND ', $memberWhere) . '
     ORDER BY m.full_name ASC
     LIMIT 100',
    $memberParams
);

$districts = fetch_all('SELECT DISTINCT district FROM members WHERE district IS NOT NULL AND district <> "" ORDER BY district ASC');

$recentTransactions = fetch_all(
    'SELECT pt.id, pt.transaction_id, pt.amount, pt.payment_status, pt.payment_mode,
            pt.transaction_date, m.full_name, m.member_id
     FROM payment_transactions pt
     LEFT JOIN members m ON pt.member_id = m.id
     ORDER BY pt.transaction_date DESC
     LIMIT 12'
);

$renewalAlerts = fetch_all(
    'SELECT m.id, m.full_name, m.member_id, m.membership_expiry_date
     FROM members m
     WHERE m.membership_status = "active"
     AND m.membership_expiry_date IS NOT NULL
     AND m.membership_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY m.membership_expiry_date ASC
     LIMIT 8'
);

$pageTitle = 'Payment Management';
$activeMenu = 'payments';
require_once __DIR__ . '/_top.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/payment-system.css')); ?>">

<section class="admin-card" style="margin-bottom: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="mb-1">Payment Management</h3>
            <p class="mb-0 text-secondary">Master membership billing control panel for APCSNSC.</p>
        </div>
        <div class="quick-actions">
            <a class="quick-btn" href="<?= esc(base_url('admin/payment_manual.php')); ?>"><i class="fa-solid fa-circle-plus me-1"></i>Collect Payment</a>
            <a class="quick-btn" href="<?= esc(base_url('admin/payment_approvals.php')); ?>"><i class="fa-solid fa-hourglass-half me-1"></i>View Pending</a>
            <a class="quick-btn" href="<?= esc(base_url('admin/payments.php?export=revenue')); ?>"><i class="fa-solid fa-file-export me-1"></i>Export Revenue</a>
            <?php if ($latestTxn): ?>
                <a class="quick-btn" href="<?= esc(base_url('admin/payment_receipt.php?id=' . urlencode((string)$latestTxn['id']))); ?>" target="_blank"><i class="fa-solid fa-print me-1"></i>Print Receipt</a>
            <?php endif; ?>
            <a class="quick-btn" href="<?= esc(base_url('admin/payment_renewals.php')); ?>"><i class="fa-solid fa-rotate me-1"></i>Renew Member</a>
            <a class="quick-btn" href="<?= esc(base_url('admin/payment_bulk.php')); ?>"><i class="fa-solid fa-layer-group me-1"></i>Bulk Actions</a>
        </div>
    </div>
</section>

<?php $flash = get_flash('success'); if ($flash): ?>
    <section class="admin-card" style="margin-bottom: 12px; border-left: 4px solid #10a860;">
        <p class="mb-0" style="color: #0a6924;"><i class="fa-solid fa-circle-check me-2"></i><?= esc($flash); ?></p>
    </section>
<?php endif; ?>

<section class="admin-grid stats" style="margin-bottom: 12px;">
    <article class="metric-card card-income">
        <div class="metric-head"><p class="metric-label">Total Revenue</p><span class="metric-icon"><i class="fa-solid fa-indian-rupee-sign"></i></span></div>
        <p class="metric-value">₹<?= esc(number_format($totalRevenueAmount, 2)); ?></p>
    </article>
    <article class="metric-card">
        <div class="metric-head"><p class="metric-label">Total Transactions</p><span class="metric-icon"><i class="fa-solid fa-receipt"></i></span></div>
        <p class="metric-value"><?= esc(number_format($totalTransactions)); ?></p>
    </article>
    <article class="metric-card card-alert-warning">
        <div class="metric-head"><p class="metric-label">Pending Approvals</p><span class="metric-icon"><i class="fa-solid fa-hourglass-half"></i></span></div>
        <p class="metric-value"><?= esc(number_format($pendingApprovals)); ?></p>
    </article>
    <article class="metric-card card-alert-danger">
        <div class="metric-head"><p class="metric-label">Expired Members</p><span class="metric-icon"><i class="fa-solid fa-calendar-xmark"></i></span></div>
        <p class="metric-value"><?= esc(number_format($expiredMembers)); ?></p>
    </article>
    <article class="metric-card card-alert-warning">
        <div class="metric-head"><p class="metric-label">Renewals This Month</p><span class="metric-icon"><i class="fa-solid fa-rotate-right"></i></span></div>
        <p class="metric-value"><?= esc(number_format($renewalsThisMonth)); ?></p>
    </article>
    <article class="metric-card card-income">
        <div class="metric-head"><p class="metric-label">Active Members</p><span class="metric-icon"><i class="fa-solid fa-user-check"></i></span></div>
        <p class="metric-value"><?= esc(number_format($activeMembers)); ?></p>
    </article>
</section>

<section class="admin-layout-2" style="margin-bottom: 12px;">
    <article class="admin-card master-plan-card">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
            <div>
                <h4 class="mb-1">Master Membership Plan</h4>
                <p class="text-secondary mb-0">Single editable plan managed by admin.</p>
            </div>
            <?php if ($masterPlan && strtolower((string)$masterPlan['status']) === 'active'): ?>
                <span class="badge badge-success">Active</span>
            <?php else: ?>
                <span class="badge badge-secondary">Inactive</span>
            <?php endif; ?>
        </div>

        <?php if ($masterPlan): ?>
            <div class="master-plan-grid">
                <div><p class="plan-k">Plan Name</p><p class="plan-v"><?= esc((string)$masterPlan['plan_name']); ?></p></div>
                <div><p class="plan-k">Price</p><p class="plan-v">₹<?= esc(number_format((float)$masterPlan['plan_price'], 2)); ?></p></div>
                <div><p class="plan-k">Renewal Price</p><p class="plan-v">₹<?= esc(number_format((float)$masterPlan['renewal_price'], 2)); ?></p></div>
                <div><p class="plan-k">Validity</p><p class="plan-v"><?= esc(get_membership_validity_label((int)$masterPlan['validity_months'])); ?></p></div>
                <div><p class="plan-k">Late Fee</p><p class="plan-v">₹<?= esc(number_format((float)$masterPlan['late_fee'], 2)); ?></p></div>
                <div><p class="plan-k">Last Updated</p><p class="plan-v"><?= esc(date('d M Y, h:i A', strtotime((string)$masterPlan['updated_at']))); ?></p></div>
            </div>
            <div class="master-plan-desc">
                <?= esc((string)($masterPlan['description'] ?? '')); ?>
            </div>
            <div class="quick-actions mt-3">
                <a class="quick-btn" href="<?= esc(base_url('admin/membership_settings.php')); ?>"><i class="fa-solid fa-pen-to-square me-1"></i>Edit Plan</a>
                <a class="quick-btn" href="<?= esc(base_url('admin/payments.php?toggle_plan=1&token=' . urlencode(csrf_token()))); ?>"><i class="fa-solid fa-toggle-on me-1"></i><?= strtolower((string)$masterPlan['status']) === 'active' ? 'Disable' : 'Enable'; ?></a>
                <a class="quick-btn" href="<?= esc(base_url('admin/payments.php#member-filters')); ?>"><i class="fa-solid fa-users me-1"></i>View Members</a>
                <a class="quick-btn" href="<?= esc(base_url('admin/payments.php#txn-history')); ?>"><i class="fa-solid fa-clock-rotate-left me-1"></i>Payment History</a>
            </div>
        <?php else: ?>
            <p class="text-danger">No master membership plan found. Run migration_master_membership_settings.sql first.</p>
        <?php endif; ?>
    </article>

    <article class="admin-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Renewals Due (30 Days)</h4>
            <a class="btn btn-sm btn-outline-warning" href="<?= esc(base_url('admin/payment_renewals.php')); ?>">View All</a>
        </div>
        <?php if (!empty($renewalAlerts)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Member</th>
                        <th>Member ID</th>
                        <th>Expiry</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($renewalAlerts as $alert): ?>
                        <tr>
                            <td><?= esc((string)$alert['full_name']); ?></td>
                            <td><?= esc((string)$alert['member_id']); ?></td>
                            <td><?= esc(date('d M Y', strtotime((string)$alert['membership_expiry_date']))); ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="<?= esc(base_url('admin/payment_renewals.php')); ?>">Renew</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-secondary mb-0">No renewals due in the next 30 days.</p>
        <?php endif; ?>
    </article>
</section>

<section id="member-filters" class="admin-card" style="margin-bottom: 12px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Search Filters</h4>
        <a class="quick-btn" href="<?= esc(base_url('admin/payments.php?export=unpaid')); ?>"><i class="fa-solid fa-download me-1"></i>Bulk Export Unpaid List</a>
    </div>
    <form method="get" class="row g-2">
        <div class="col-md-3">
            <input class="form-control" type="text" name="q" value="<?= esc($search); ?>" placeholder="Member Name / Member ID">
        </div>
        <div class="col-md-2">
            <select class="form-select" name="district">
                <option value="">District</option>
                <?php foreach ($districts as $d): ?>
                    <option value="<?= esc((string)$d['district']); ?>" <?= $district === (string)$d['district'] ? 'selected' : ''; ?>><?= esc((string)$d['district']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input class="form-control" type="text" name="phone" value="<?= esc($phone); ?>" placeholder="Phone">
        </div>
        <div class="col-md-2">
            <select class="form-select" name="expiry">
                <option value="all" <?= $expiryFilter === 'all' ? 'selected' : ''; ?>>Any Expiry</option>
                <option value="this_month" <?= $expiryFilter === 'this_month' ? 'selected' : ''; ?>>Expiry This Month</option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="payment">
                <option value="all" <?= $paymentFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="unpaid" <?= $paymentFilter === 'unpaid' ? 'selected' : ''; ?>>Unpaid Members</option>
                <option value="renewal_due" <?= $paymentFilter === 'renewal_due' ? 'selected' : ''; ?>>Renewal Due</option>
                <option value="expired" <?= $paymentFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
            </select>
        </div>
        <div class="col-md-1 d-grid">
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
    </form>

    <div class="table-responsive mt-3">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Member</th>
                <th>Member ID</th>
                <th>District</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Expiry</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($membersList)): ?>
                <tr><td colspan="6" class="text-secondary">No members found for current filters.</td></tr>
            <?php else: ?>
                <?php foreach ($membersList as $m): ?>
                    <tr>
                        <td><?= esc((string)$m['full_name']); ?></td>
                        <td><?= esc((string)$m['member_id']); ?></td>
                        <td><?= esc((string)$m['district']); ?></td>
                        <td><?= esc((string)$m['phone']); ?></td>
                        <td>
                            <?= get_payment_status_badge((string)($m['payment_status'] ?? 'unpaid')); ?>
                            <?= get_membership_status_badge((string)($m['membership_status'] ?? 'unpaid')); ?>
                        </td>
                        <td><?= !empty($m['membership_expiry_date']) ? esc(date('d M Y', strtotime((string)$m['membership_expiry_date']))) : 'N/A'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="txn-history" class="admin-card" style="margin-bottom: 12px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Payment History (Recent)</h4>
        <a class="btn btn-sm btn-outline-primary" href="<?= esc(base_url('admin/payment_reports.php')); ?>">Open Reports</a>
    </div>
    <?php if (!empty($recentTransactions)): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Transaction ID</th>
                    <th>Member</th>
                    <th>Amount</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentTransactions as $txn): ?>
                    <tr>
                        <td><small><code><?= esc(get_receipt_no($txn)); ?></code></small></td>
                        <td><small><code><?= esc((string)$txn['transaction_id']); ?></code></small></td>
                        <td>
                            <?= esc((string)($txn['full_name'] ?? 'N/A')); ?><br>
                            <small class="text-secondary"><?= esc((string)($txn['member_id'] ?? '')); ?></small>
                        </td>
                        <td><strong style="color:#10a860;">₹<?= esc(number_format((float)$txn['amount'], 2)); ?></strong></td>
                        <td><?= esc(ucfirst(strtolower((string)($txn['payment_mode'] ?? 'N/A')))); ?></td>
                        <td><?= get_payment_status_badge((string)($txn['payment_status'] ?? 'pending')); ?></td>
                        <td><?= esc(date('d M Y', strtotime((string)$txn['transaction_date']))); ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= esc(base_url('admin/payment_receipt.php?id=' . urlencode((string)$txn['id']))); ?>" target="_blank">Receipt</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-secondary mb-0">No transactions found.</p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/_bottom.php'; ?>
