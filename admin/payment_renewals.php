<?php
require_once __DIR__ . '/../db.php';
require_admin();

$masterPlan = get_master_membership_settings();

// Get renewal period
$period = strtolower((string)($_GET['period'] ?? 'all'));
$period = in_array($period, ['all', 'expired', 'urgent', '30days'], true) ? $period : 'all';

// Build condition
$dateCondition = '';

if ($period === 'expired') {
    $dateCondition = ' AND m.membership_expiry_date < CURDATE()';
} elseif ($period === 'urgent') {
    $dateCondition = ' AND m.membership_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
} elseif ($period === '30days') {
    $dateCondition = ' AND m.membership_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
} else {
    // All pending renewals
    $dateCondition = ' AND m.membership_status IN ("active", "renewal_due") AND m.membership_expiry_date IS NOT NULL';
}

// Handle renewal action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'process_renewal') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid request token.');
        redirect_to('admin/payment_renewals.php');
    }

    $memberId = (int)($_POST['member_id'] ?? 0);
    $paymentMode = strtoupper(clean((string)($_POST['payment_mode'] ?? 'CASH')));
    $remarks = clean((string)($_POST['remarks'] ?? ''));

    if ($memberId <= 0) {
        set_flash('error', 'Invalid member.');
        redirect_to('admin/payment_renewals.php');
    }

    $member = fetch_one('SELECT id, membership_expiry_date FROM members WHERE id = :id LIMIT 1', [':id' => $memberId]);
    if (!$member) {
        set_flash('error', 'Member not found.');
        redirect_to('admin/payment_renewals.php');
    }

    if (!$masterPlan || strtolower((string)$masterPlan['status']) !== 'active') {
        set_flash('error', 'Active master plan not found. Please configure membership settings.');
        redirect_to('admin/payment_renewals.php');
    }

    try {
        // Calculate renewal dates and amount.
        $previousExpiry = $member['membership_expiry_date'];
        $validityMonths = (int)$masterPlan['validity_months'];
        $dateCalc = calculate_membership_dates($member, $validityMonths);
        $startDate = $dateCalc['start_date'];
        $newExpiry = $dateCalc['expiry_date'];
        $transactionId = generate_transaction_id();
        $now = date('Y-m-d H:i:s');
        $amount = (float)$masterPlan['renewal_price'];

        if ($previousExpiry && strtotime((string)$previousExpiry) < strtotime(date('Y-m-d'))) {
            $amount += (float)$masterPlan['late_fee'];
        }

        // Create renewal transaction
        execute_query(
            'INSERT INTO payment_transactions 
            (transaction_id, member_id, plan_id, amount, payment_status, payment_mode, approved_date, approved_by, remarks, is_renewal, previous_expiry_date)
            VALUES (:txn_id, :member_id, :plan_id, :amount, :status, :mode, :approved_date, :admin_id, :remarks, :is_renewal, :prev_expiry)',
            [
                ':txn_id' => $transactionId,
                ':member_id' => $memberId,
                ':plan_id' => null,
                ':amount' => $amount,
                ':status' => 'approved',
                ':mode' => $paymentMode,
                ':approved_date' => $now,
                ':admin_id' => (int)($_SESSION['admin_user_id'] ?? 0),
                ':remarks' => $remarks,
                ':is_renewal' => 1,
                ':prev_expiry' => $previousExpiry,
            ]
        );

        // Update member
        execute_query(
            'UPDATE members SET
                membership_status = "active",
                payment_status = "paid",
                plan_name = :plan_name,
                plan_amount = :amount,
                membership_start_date = :start_date,
                membership_expiry_date = :expiry_date,
                transaction_id = :txn_id,
                last_payment_date = :now,
                payment_mode = :mode,
                payment_remarks = :remarks,
                renewal_count = renewal_count + 1
            WHERE id = :id',
            [
                ':id' => $memberId,
                ':plan_name' => (string)$masterPlan['plan_name'],
                ':amount' => $amount,
                ':start_date' => $startDate,
                ':expiry_date' => $newExpiry,
                ':txn_id' => $transactionId,
                ':now' => $now,
                ':mode' => $paymentMode,
                ':remarks' => $remarks,
            ]
        );

        set_flash('success', 'Membership renewed successfully. Expiry extended to ' . date('d M Y', strtotime($newExpiry)));
        redirect_to('admin/payment_renewals.php');
    } catch (Exception $e) {
        error_log('Renewal error: ' . $e->getMessage());
        set_flash('error', 'Error processing renewal: ' . $e->getMessage());
        redirect_to('admin/payment_renewals.php');
    }
}

// Get renewal members
$renewalMembers = fetch_all(
    'SELECT m.id, m.full_name, m.member_id, m.membership_expiry_date, m.plan_name, 
            m.membership_status, m.renewal_count, m.last_payment_date
     FROM members m
     WHERE m.membership_status IN ("active", "renewal_due", "expired")
     AND m.membership_expiry_date IS NOT NULL' . $dateCondition . '
     ORDER BY m.membership_expiry_date ASC'
);

// Count stats
$statsExpired = (int)(fetch_one("SELECT COUNT(*) AS total FROM members WHERE membership_expiry_date < CURDATE() AND membership_status IN ('active', 'renewal_due')")['total'] ?? 0);
$statsUrgent = (int)(fetch_one("SELECT COUNT(*) AS total FROM members WHERE membership_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND membership_status IN ('active', 'renewal_due')")['total'] ?? 0);
$stats30Days = (int)(fetch_one("SELECT COUNT(*) AS total FROM members WHERE membership_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND membership_status IN ('active', 'renewal_due')")['total'] ?? 0);

$pageTitle = 'Renewal Management';
$activeMenu = 'payments';
require_once __DIR__ . '/_top.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/payment-system.css')); ?>">

<section class="admin-card" style="margin-bottom: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="mb-1">Membership Renewal Management</h3>
            <p class="mb-0 text-secondary">Track and process member renewals before expiry.</p>
        </div>
        <div class="quick-actions">
            <a class="quick-btn <?= $period === 'all' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/payment_renewals.php?period=all')); ?>">All Renewals</a>
            <a class="quick-btn <?= $period === 'urgent' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/payment_renewals.php?period=urgent')); ?>">Urgent (7 Days)</a>
            <a class="quick-btn <?= $period === '30days' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/payment_renewals.php?period=30days')); ?>">30 Days</a>
            <a class="quick-btn <?= $period === 'expired' ? 'active' : ''; ?>" href="<?= esc(base_url('admin/payment_renewals.php?period=expired')); ?>">Expired</a>
            <a class="quick-btn" href="<?= esc(base_url('admin/payments.php')); ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
</section>

<!-- Stats -->
<section class="admin-grid stats" style="margin-bottom: 12px;">
    <article class="metric-card">
        <div class="metric-head"><p class="metric-label">Urgent (7 Days)</p><span class="metric-icon"><i class="fa-solid fa-bell"></i></span></div>
        <p class="metric-value" style="color: #dc3545;"><?= esc(number_format($statsUrgent)); ?></p>
    </article>
    <article class="metric-card">
        <div class="metric-head"><p class="metric-label">30 Days Notice</p><span class="metric-icon"><i class="fa-solid fa-calendar"></i></span></div>
        <p class="metric-value" style="color: #f77f00;"><?= esc(number_format($stats30Days)); ?></p>
    </article>
    <article class="metric-card">
        <div class="metric-head"><p class="metric-label">Already Expired</p><span class="metric-icon"><i class="fa-solid fa-exclamation-triangle"></i></span></div>
        <p class="metric-value" style="color: #6c757d;"><?= esc(number_format($statsExpired)); ?></p>
    </article>
</section>

<?php if (empty($renewalMembers)): ?>
    <section class="admin-card">
        <div style="text-align: center; padding: 40px;">
            <i class="fa-solid fa-check-circle" style="font-size: 48px; color: #10a860; margin-bottom: 16px;"></i>
            <h3 style="color: #10a860;">All Clear!</h3>
            <p class="text-secondary">No members require renewal action for the selected period.</p>
            <a class="quick-btn" href="<?= esc(base_url('admin/payments.php')); ?>">Back to Payments</a>
        </div>
    </section>
<?php else: ?>
    <section style="margin-bottom: 12px;">
        <?php foreach ($renewalMembers as $member): ?>
            <?php 
                $daysUntilExpiry = (int)(( strtotime((string)$member['membership_expiry_date']) - time()) / 86400);
                $isExpired = $daysUntilExpiry < 0;
                $isUrgent = $daysUntilExpiry >= 0 && $daysUntilExpiry <= 7;
                $alertClass = $isExpired ? 'danger' : ($isUrgent ? 'warning' : 'success');
            ?>
            <article class="payment-card" style="border-left: 4px solid <?= $isExpired ? '#dc3545' : ($isUrgent ? '#ffc107' : '#10a860'); ?>;">
                <div class="payment-header">
                    <div>
                        <strong style="font-size: 16px;"><?= esc($member['full_name']); ?></strong>
                        <p style="margin: 6px 0 0; font-size: 13px; color: #94a3b8;">
                            ID: <?= esc($member['member_id']); ?> | Renewals: <?= esc((string)$member['renewal_count']); ?>
                        </p>
                    </div>
                    <?php if ($isExpired): ?>
                        <span class="badge badge-danger">Expired</span>
                    <?php elseif ($isUrgent): ?>
                        <span class="badge badge-warning">Urgent</span>
                    <?php else: ?>
                        <span class="badge badge-success">Active</span>
                    <?php endif; ?>
                </div>

                <div style="background: #f8fafc; border-radius: 12px; padding: 12px; margin-bottom: 16px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
                        <div>
                            <p style="margin: 0 0 4px; font-size: 11px; color: #94a3b8; text-transform: uppercase;">Master Plan</p>
                            <p style="margin: 0; font-weight: 600; color: #0f172a;"><?= esc((string)($masterPlan['plan_name'] ?? ($member['plan_name'] ?? 'N/A'))); ?></p>
                        </div>
                        <div>
                            <p style="margin: 0 0 4px; font-size: 11px; color: #94a3b8; text-transform: uppercase;">Last Payment</p>
                            <p style="margin: 0; font-weight: 600; color: #0f172a;"><?= esc(date('d M Y', strtotime((string)$member['last_payment_date']))); ?></p>
                        </div>
                        <div>
                            <p style="margin: 0 0 4px; font-size: 11px; color: #94a3b8; text-transform: uppercase;">Expires</p>
                            <p style="margin: 0; font-weight: 600; color: <?= $isExpired ? '#dc3545' : '#0f172a'; ?>;">
                                <?= esc(date('d M Y', strtotime((string)$member['membership_expiry_date']))); ?>
                            </p>
                        </div>
                        <div>
                            <p style="margin: 0 0 4px; font-size: 11px; color: #94a3b8; text-transform: uppercase;">Days Until Expiry</p>
                            <p style="margin: 0; font-weight: 600; color: <?= $isExpired ? '#dc3545' : ($isUrgent ? '#ffc107' : '#10a860'); ?>;">
                                <?= $isExpired ? 'EXPIRED ' . abs($daysUntilExpiry) . 'd ago' : ($daysUntilExpiry . ' days'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <details style="margin-bottom: 16px;">
                    <summary style="cursor: pointer; padding: 8px 0; color: #0f172a; font-weight: 600; user-select: none;">
                        <i class="fa-solid fa-chevron-right me-2"></i>Process Renewal
                    </summary>
                    <form method="post" class="form-grid" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                        <input type="hidden" name="action" value="process_renewal">
                        <input type="hidden" name="member_id" value="<?= esc((string)$member['id']); ?>">

                        <div class="form-group">
                            <label>Renewal Plan</label>
                            <input type="text" readonly value="<?= esc((string)($masterPlan['plan_name'] ?? 'Master Plan')); ?> (₹<?= esc(number_format((float)($masterPlan['renewal_price'] ?? 0), 2)); ?>)">
                        </div>

                        <div class="form-group">
                            <label>Payment Mode</label>
                            <select name="payment_mode" required>
                                <option value="CASH">Cash</option>
                                <option value="UPI">UPI / Mobile</option>
                                <option value="BANK_TRANSFER">Bank Transfer</option>
                                <option value="ONLINE">Online Payment</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label>Admin Remarks</label>
                            <textarea name="remarks" rows="2" placeholder="Notes (optional)..."></textarea>
                        </div>

                        <div class="form-group full">
                            <button type="submit" class="btn btn-success" style="width: 100%;">
                                <i class="fa-solid fa-sync-alt me-2"></i>Complete Renewal
                            </button>
                        </div>
                    </form>
                </details>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/_bottom.php'; ?>
