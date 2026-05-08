<?php
require_once __DIR__ . '/../db.php';
require_admin();

$masterPlan = get_master_membership_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid request token.');
        redirect_to('admin/payment_manual.php');
    }

    if (!$masterPlan || strtolower((string)$masterPlan['status']) !== 'active') {
        set_flash('error', 'No active master membership plan. Enable plan in Membership Settings.');
        redirect_to('admin/payment_manual.php');
    }

    $memberId = (int)($_POST['member_id'] ?? 0);
    $paymentMode = strtoupper(clean((string)($_POST['payment_mode'] ?? 'CASH')));
    $remarks = clean((string)($_POST['remarks'] ?? ''));

    if ($memberId <= 0) {
        set_flash('error', 'Please select a valid member.');
        redirect_to('admin/payment_manual.php');
    }

    $member = fetch_one('SELECT id, membership_status, payment_status, membership_expiry_date FROM members WHERE id = :id LIMIT 1', [':id' => $memberId]);
    if (!$member) {
        set_flash('error', 'Member not found.');
        redirect_to('admin/payment_manual.php');
    }

    $validModes = ['CASH', 'UPI', 'BANK_TRANSFER', 'ONLINE', 'OTHER'];
    if (!in_array($paymentMode, $validModes, true)) {
        $paymentMode = 'OTHER';
    }

    $validityMonths = (int)$masterPlan['validity_months'];
    $amount = (float)$masterPlan['plan_price'];
    $dateCalc = calculate_membership_dates($member, $validityMonths);
    $startDate = $dateCalc['start_date'];
    $expiryDate = $dateCalc['expiry_date'];
    $isRenewal = $dateCalc['is_renewal'] ? 1 : 0;

    if ($isRenewal) {
        $amount = (float)$masterPlan['renewal_price'];
        if (strtotime((string)$member['membership_expiry_date']) < strtotime(date('Y-m-d'))) {
            $amount += (float)$masterPlan['late_fee'];
        }
    }

    $transactionId = generate_transaction_id();
    $now = date('Y-m-d H:i:s');

    try {
        execute_query(
            'INSERT INTO payment_transactions
            (transaction_id, member_id, plan_id, amount, payment_status, payment_mode, approved_date, approved_by, remarks, is_renewal, previous_expiry_date)
            VALUES (:txn_id, :member_id, NULL, :amount, :status, :mode, :approved_date, :admin_id, :remarks, :is_renewal, :previous_expiry_date)',
            [
                ':txn_id' => $transactionId,
                ':member_id' => $memberId,
                ':amount' => $amount,
                ':status' => 'approved',
                ':mode' => $paymentMode,
                ':approved_date' => $now,
                ':admin_id' => (int)($_SESSION['admin_user_id'] ?? 0),
                ':remarks' => $remarks,
                ':is_renewal' => $isRenewal,
                ':previous_expiry_date' => $dateCalc['previous_expiry_date'],
            ]
        );

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
                ':expiry_date' => $expiryDate,
                ':txn_id' => $transactionId,
                ':now' => $now,
                ':mode' => $paymentMode,
                ':remarks' => $remarks,
            ]
        );

        $txnRow = fetch_one('SELECT id FROM payment_transactions WHERE transaction_id = :txn LIMIT 1', [':txn' => $transactionId]);
        $receiptRef = $txnRow ? ' Receipt: ' . get_receipt_no($txnRow) . '.' : '';

        set_flash('success', 'Payment recorded and member activated successfully. TXN: ' . $transactionId . '.' . $receiptRef);
        redirect_to('admin/payment_manual.php');
    } catch (Exception $e) {
        error_log('Payment error: ' . $e->getMessage());
        set_flash('error', 'Error processing payment: ' . $e->getMessage());
        redirect_to('admin/payment_manual.php');
    }
}

$members = fetch_all('SELECT id, full_name, member_id, phone, membership_status, payment_status, membership_expiry_date FROM members ORDER BY full_name ASC');

$pageTitle = 'Manual Payment Entry';
$activeMenu = 'payments';
require_once __DIR__ . '/_top.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/payment-system.css')); ?>">

<section class="admin-card" style="margin-bottom: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="mb-1">Record Manual Payment</h3>
            <p class="mb-0 text-secondary">Use active master plan for new payment or renewal.</p>
        </div>
        <div class="quick-actions">
            <a class="quick-btn" href="<?= esc(base_url('admin/payments.php')); ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
</section>

<?php if (!$masterPlan): ?>
    <section class="admin-card" style="max-width: 760px; border-left: 4px solid #dc3545;">
        <p class="mb-0">Master membership plan not found. Run migration and configure plan first.</p>
    </section>
<?php else: ?>
    <section class="admin-card" style="max-width: 760px; margin-bottom: 12px;">
        <div class="master-plan-grid" style="margin-bottom: 0;">
            <div><p class="plan-k">Plan Name</p><p class="plan-v"><?= esc((string)$masterPlan['plan_name']); ?></p></div>
            <div><p class="plan-k">Amount</p><p class="plan-v">₹<?= esc(number_format((float)$masterPlan['plan_price'], 2)); ?></p></div>
            <div><p class="plan-k">Renewal Price</p><p class="plan-v">₹<?= esc(number_format((float)$masterPlan['renewal_price'], 2)); ?></p></div>
            <div><p class="plan-k">Validity</p><p class="plan-v"><?= esc(get_membership_validity_label((int)$masterPlan['validity_months'])); ?></p></div>
        </div>
    </section>
<?php endif; ?>

<section class="admin-card" style="max-width: 760px;">
    <?php $flash = get_flash('success'); if ($flash): ?>
        <div class="alert alert-success" role="alert"><i class="fa-solid fa-check-circle me-2"></i><?= esc($flash); ?></div>
    <?php endif; ?>
    <?php $flashError = get_flash('error'); if ($flashError): ?>
        <div class="alert alert-danger" role="alert"><i class="fa-solid fa-exclamation-circle me-2"></i><?= esc($flashError); ?></div>
    <?php endif; ?>

    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

        <div class="form-group full">
            <label for="member_id">Select Member <span style="color: #dc3545;">*</span></label>
            <select id="member_id" name="member_id" required>
                <option value="">-- Choose a Member --</option>
                <?php foreach ($members as $m): ?>
                    <option
                        value="<?= esc((string)$m['id']); ?>"
                        data-member-name="<?= esc((string)$m['full_name']); ?>"
                        data-member-code="<?= esc((string)$m['member_id']); ?>"
                        data-member-status="<?= esc((string)$m['membership_status']); ?>"
                        data-payment-status="<?= esc((string)$m['payment_status']); ?>"
                        data-expiry="<?= esc((string)($m['membership_expiry_date'] ?? '')); ?>"
                    >
                        <?= esc($m['full_name'] . ' (' . $m['member_id'] . ' - ' . $m['phone'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group full">
            <label for="payment_mode">Payment Mode <span style="color: #dc3545;">*</span></label>
            <select id="payment_mode" name="payment_mode" required>
                <option value="CASH">Cash</option>
                <option value="UPI">UPI</option>
                <option value="BANK_TRANSFER">Bank Transfer</option>
                <option value="ONLINE">Online</option>
                <option value="OTHER">Other</option>
            </select>
        </div>

        <div class="form-group full">
            <label for="remarks">Admin Remarks (Optional)</label>
            <textarea id="remarks" name="remarks" rows="3" placeholder="Add notes about this payment..."></textarea>
        </div>

        <div class="form-group full" style="background: #f8fafc; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
            <p style="margin: 0 0 12px; font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Payment Summary</p>
            <div id="summary-member" style="margin-bottom: 6px;"><span style="color: #94a3b8;">Member Name:</span> <strong style="color: #0f172a;">Not selected</strong></div>
            <div id="summary-member-id" style="margin-bottom: 6px;"><span style="color: #94a3b8;">Member ID:</span> <strong style="color: #0f172a;">N/A</strong></div>
            <div id="summary-current-status" style="margin-bottom: 6px;"><span style="color: #94a3b8;">Current Status:</span> <strong style="color: #0f172a;">N/A</strong></div>
            <div id="summary-plan" style="margin-bottom: 6px;"><span style="color: #94a3b8;">Plan Name:</span> <strong style="color: #0f172a;"><?= esc((string)($masterPlan['plan_name'] ?? 'Not configured')); ?></strong></div>
            <div id="summary-amount" style="margin-bottom: 6px;"><span style="color: #94a3b8;">Amount:</span> <strong style="color: #10a860; font-size: 18px;">₹<?= esc(number_format((float)($masterPlan['plan_price'] ?? 0), 2)); ?></strong></div>
            <div id="summary-validity" style="margin-bottom: 6px;"><span style="color: #94a3b8;">Validity:</span> <strong style="color: #0f172a;"><?= esc(get_membership_validity_label((int)($masterPlan['validity_months'] ?? 12))); ?></strong></div>
            <div id="summary-expiry" style="color: #334155; font-size: 13px;">Expiry date after payment will be calculated automatically.</div>
        </div>

        <div class="form-group full">
            <button type="submit" class="btn btn-success" <?= (!$masterPlan || strtolower((string)$masterPlan['status']) !== 'active') ? 'disabled' : ''; ?>>
                <i class="fa-solid fa-plus-circle me-2"></i>Record Payment & Activate Member
            </button>
        </div>
    </form>
</section>

<script>
(function () {
    var memberSelect = document.getElementById('member_id');
    if (!memberSelect) {
        return;
    }

    var planPrice = <?= json_encode((float)($masterPlan['plan_price'] ?? 0)); ?>;
    var renewalPrice = <?= json_encode((float)($masterPlan['renewal_price'] ?? 0)); ?>;
    var lateFee = <?= json_encode((float)($masterPlan['late_fee'] ?? 0)); ?>;
    var validityMonths = <?= json_encode((int)($masterPlan['validity_months'] ?? 12)); ?>;

    function addMonths(date, months) {
        var d = new Date(date.getTime());
        d.setMonth(d.getMonth() + months);
        return d;
    }

    function fmt(date) {
        var dd = String(date.getDate()).padStart(2, '0');
        var mm = String(date.getMonth() + 1).padStart(2, '0');
        var yyyy = date.getFullYear();
        return dd + '-' + mm + '-' + yyyy;
    }

    function updateSummary() {
        var opt = memberSelect.options[memberSelect.selectedIndex];
        var memberName = opt ? (opt.getAttribute('data-member-name') || 'Not selected') : 'Not selected';
        var memberCode = opt ? (opt.getAttribute('data-member-code') || 'N/A') : 'N/A';
        var membershipStatus = opt ? (opt.getAttribute('data-member-status') || 'unpaid') : 'unpaid';
        var paymentStatus = opt ? (opt.getAttribute('data-payment-status') || 'unpaid') : 'unpaid';
        var expiryText = opt ? (opt.getAttribute('data-expiry') || '') : '';

        document.getElementById('summary-member').innerHTML = '<span style="color: #94a3b8;">Member Name:</span> <strong style="color: #0f172a;">' + memberName + '</strong>';
        document.getElementById('summary-member-id').innerHTML = '<span style="color: #94a3b8;">Member ID:</span> <strong style="color: #0f172a;">' + memberCode + '</strong>';
        document.getElementById('summary-current-status').innerHTML = '<span style="color: #94a3b8;">Current Status:</span> <strong style="color: #0f172a;">' + paymentStatus + ' / ' + membershipStatus + '</strong>';

        var today = new Date();
        var expiryDate = new Date(today.getTime());
        var amount = planPrice;

        if (expiryText && paymentStatus.toLowerCase() === 'paid' && membershipStatus.toLowerCase() === 'active') {
            var currentExpiry = new Date(expiryText + 'T00:00:00');
            if (!isNaN(currentExpiry.getTime()) && currentExpiry >= today) {
                expiryDate = addMonths(currentExpiry, validityMonths);
                amount = renewalPrice;
            } else {
                expiryDate = addMonths(today, validityMonths);
                amount = renewalPrice + lateFee;
            }
        } else {
            expiryDate = addMonths(today, validityMonths);
        }

        document.getElementById('summary-amount').innerHTML = '<span style="color: #94a3b8;">Amount:</span> <strong style="color: #10a860; font-size: 18px;">₹' + amount.toFixed(2) + '</strong>';
        document.getElementById('summary-expiry').textContent = 'Expiry Date After Payment: ' + fmt(expiryDate);
    }

    memberSelect.addEventListener('change', updateSummary);
})();
</script>

<?php require_once __DIR__ . '/_bottom.php'; ?>
