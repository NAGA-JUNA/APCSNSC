<?php
require_once __DIR__ . '/../db.php';
require_admin();

if (!can_approve_payments()) {
    set_flash('error', 'You are not authorized to approve payments.');
    redirect_to('admin/dashboard.php');
}

$isDistrictScoped = is_district_president() && admin_district() !== '';
$scopedDistrict = admin_district();

// Handle approval action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid request token.');
        redirect_to('admin/payment_approvals.php');
    }

    $txnId = (int)($_POST['txn_id'] ?? 0);
    $action = strtolower((string)($_POST['action'] ?? 'approve'));
    $status = $action === 'reject' ? 'failed' : 'approved';

    if ($txnId <= 0 || !in_array($status, ['approved', 'failed'], true)) {
        set_flash('error', 'Invalid action.');
        redirect_to('admin/payment_approvals.php');
    }

    $txn = fetch_one(
        'SELECT pt.*, m.district AS member_district
         FROM payment_transactions pt
         LEFT JOIN members m ON pt.member_id = m.id
         WHERE pt.id = :id LIMIT 1',
        [':id' => $txnId]
    );
    if (!$txn) {
        set_flash('error', 'Transaction not found.');
        redirect_to('admin/payment_approvals.php');
    }

    if ($isDistrictScoped && !admin_can_access_district((string)($txn['member_district'] ?? ''))) {
        set_flash('error', 'You can only approve payments for your assigned district.');
        redirect_to('admin/payment_approvals.php');
    }

    $remarks = clean((string)($_POST['remarks'] ?? ''));

    if ($status === 'approved') {
        $member = fetch_one('SELECT id, membership_status, payment_status, membership_expiry_date FROM members WHERE id = :id LIMIT 1', [':id' => (int)$txn['member_id']]);
        $masterPlan = get_master_membership_settings();

        if ($member && $masterPlan) {
            // Update member payment status
            $validityMonths = (int)$masterPlan['validity_months'];
            $dateCalc = calculate_membership_dates($member, $validityMonths);
            $startDate = $dateCalc['start_date'];
            $expiryDate = $dateCalc['expiry_date'];

            execute_query(
                'UPDATE members SET
                    membership_status = "active",
                    payment_status = "paid",
                    plan_name = :plan_name,
                    plan_amount = :plan_amount,
                    membership_start_date = :start_date,
                    membership_expiry_date = :expiry_date,
                    transaction_id = :transaction_id,
                    last_payment_date = NOW(),
                    payment_mode = :payment_mode,
                    renewal_count = renewal_count + 1
                WHERE id = :id',
                [
                    ':id' => (int)$txn['member_id'],
                    ':plan_name' => (string)$masterPlan['plan_name'],
                    ':plan_amount' => (float)$txn['amount'],
                    ':start_date' => $startDate,
                    ':expiry_date' => $expiryDate,
                    ':transaction_id' => $txn['transaction_id'],
                    ':payment_mode' => $txn['payment_mode'],
                ]
            );

            // Update transaction
            execute_query(
                'UPDATE payment_transactions SET
                    payment_status = "approved",
                    approved_date = NOW(),
                    approved_by = :admin_id,
                    remarks = :remarks
                WHERE id = :id',
                [
                    ':id' => $txnId,
                    ':admin_id' => (int)($_SESSION['admin_user_id'] ?? 0),
                    ':remarks' => $remarks,
                ]
            );

            set_flash('success', 'Payment approved successfully. Member activated under master plan.');
        } else {
            set_flash('error', 'Could not find associated member or active master plan.');
        }
    } else {
        // Mark as failed
        execute_query(
            'UPDATE payment_transactions SET
                payment_status = "failed",
                approved_date = NOW(),
                approved_by = :admin_id,
                remarks = :remarks
            WHERE id = :id',
            [
                ':id' => $txnId,
                ':admin_id' => (int)($_SESSION['admin_user_id'] ?? 0),
                ':remarks' => $remarks,
            ]
        );

        set_flash('success', 'Payment marked as failed.');
    }

    redirect_to('admin/payment_approvals.php');
}

// Get pending payments
$pendingSql =
    'SELECT pt.id, pt.transaction_id, pt.member_id, pt.plan_id, pt.amount, pt.payment_mode,
            pt.transaction_date, pt.payment_status, m.full_name, m.member_id AS member_code,
            m.plan_name, m.district
     FROM payment_transactions pt
     LEFT JOIN members m ON pt.member_id = m.id
     WHERE pt.payment_status = "pending"';

$pendingParams = [];
if ($isDistrictScoped) {
    $pendingSql .= ' AND m.district = :scope_district';
    $pendingParams[':scope_district'] = $scopedDistrict;
}

$pendingSql .= ' ORDER BY pt.transaction_date DESC';
$pendingPayments = fetch_all($pendingSql, $pendingParams);

$pageTitle = 'Pending Approvals';
$activeMenu = 'payments';
require_once __DIR__ . '/_top.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/payment-system.css')); ?>">

<section class="admin-card" style="margin-bottom: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="mb-1">Payment Approvals</h3>
            <p class="mb-0 text-secondary">Review and approve pending member payments.</p>
        </div>
        <div class="quick-actions">
            <a class="quick-btn" href="<?= esc(base_url('admin/payments.php')); ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
</section>

<?php if (empty($pendingPayments)): ?>
    <section class="admin-card">
        <div style="text-align: center; padding: 40px;">
            <i class="fa-solid fa-check-circle" style="font-size: 48px; color: #10a860; margin-bottom: 16px;"></i>
            <h3 style="color: #10a860;">All Approved!</h3>
            <p class="text-secondary">There are no pending payments waiting for approval.</p>
            <a class="quick-btn" href="<?= esc(base_url('admin/payments.php')); ?>">Back to Payments</a>
        </div>
    </section>
<?php else: ?>
    <section style="margin-bottom: 12px;">
        <?php foreach ($pendingPayments as $payment): ?>
            <article class="payment-card">
                <div class="payment-header">
                    <div>
                        <strong style="font-size: 16px;">Transaction <?= esc($payment['transaction_id']); ?></strong>
                        <p style="margin: 6px 0 0; font-size: 13px; color: #94a3b8;">
                            <?= esc(date('d M Y, h:i A', strtotime((string)$payment['transaction_date']))); ?>
                        </p>
                    </div>
                    <span class="badge badge-warning">Pending Review</span>
                </div>

                <div class="payment-info">
                    <div class="payment-info-item">
                        <span class="payment-info-label">Member Name</span>
                        <span class="payment-info-value"><?= esc($payment['full_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="payment-info-item">
                        <span class="payment-info-label">Member ID</span>
                        <span class="payment-info-value"><?= esc($payment['member_code'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="payment-info-item">
                        <span class="payment-info-label">Plan</span>
                        <span class="payment-info-value"><?= esc($payment['plan_name'] ?? 'Master Plan'); ?></span>
                    </div>
                    <div class="payment-info-item">
                        <span class="payment-info-label">Amount</span>
                        <span class="payment-info-value" style="color: #10a860;">₹<?= esc(number_format((float)$payment['amount'], 2)); ?></span>
                    </div>
                    <div class="payment-info-item">
                        <span class="payment-info-label">Payment Mode</span>
                        <span class="payment-info-value"><?= esc(ucfirst($payment['payment_mode'] ?? 'Cash')); ?></span>
                    </div>
                </div>

                <div style="background: #f8fafc; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                    <form method="post" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                        <input type="hidden" name="txn_id" value="<?= esc((string)$payment['id']); ?>">

                        <div class="form-group full">
                            <label>Admin Remarks</label>
                            <textarea name="remarks" placeholder="Add notes about this payment (optional)" rows="3"></textarea>
                        </div>

                        <div class="form-group full" style="display: flex; gap: 12px;">
                            <button type="submit" name="action" value="approve" class="btn btn-success" style="flex: 1;">
                                <i class="fa-solid fa-check me-1"></i>Approve Payment
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger" style="flex: 1; background-color: #dc3545; color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                <i class="fa-solid fa-times me-1"></i>Reject Payment
                            </button>
                        </div>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/_bottom.php'; ?>
