<?php
require_once __DIR__ . '/db.php';

$transactionId = clean((string)($_GET['txn_id'] ?? ''));
$memberIdStr = clean((string)($_GET['member_id'] ?? ''));

if ($transactionId === '' || $memberIdStr === '') {
    die('<h2>Invalid receipt request</h2>');
}

// Fetch transaction details and verify it belongs to this member
$txn = fetch_one(
    'SELECT pt.*, m.member_id, m.full_name, m.phone, m.district, m.email
     FROM payment_transactions pt
     LEFT JOIN members m ON pt.member_id = m.id
     WHERE pt.transaction_id = :txn_id AND m.member_id = :member_id LIMIT 1',
    [':txn_id' => $transactionId, ':member_id' => $memberIdStr]
);

if (!$txn) {
    die('<h2>Receipt not found or access denied.</h2>');
}

// Get membership settings
$masterPlan = get_master_membership_settings();

// Get approved date and approver
$approverQuery = fetch_one(
    'SELECT username FROM admin_users WHERE id = :admin_id LIMIT 1',
    [':admin_id' => (int)($txn['approved_by'] ?? 0)]
);
$approverName = $approverQuery['username'] ?? 'System';

// Calculate membership validity
$validityMonths = (int)(($masterPlan['validity_months'] ?? null) ?: 12);
$validityLabel = get_membership_validity_label($validityMonths);

// Membership dates
$memberInfo = fetch_one('SELECT membership_expiry_date FROM members WHERE id = :id', [':id' => $txn['member_id']]);
$expiryDate = $memberInfo['membership_expiry_date'] ?? date('Y-m-d', strtotime('+' . $validityMonths . ' months'));

$receiptNo = get_receipt_no($txn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt <?= esc($receiptNo); ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f2f6fb; color: #1e293b; margin: 0; padding: 20px; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .receipt { max-width: 760px; margin: 0 auto; background: #fff; border: 1px solid #dbe3ef; border-radius: 14px; overflow: hidden; }
        .head { background: linear-gradient(120deg, #0f4a7d, #1f7a63); color: #fff; padding: 20px; }
        .head h1 { margin: 0 0 4px; font-size: 24px; }
        .head p { margin: 0; opacity: 0.9; }
        .body { padding: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
        .cell { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; }
        .k { margin: 0 0 5px; color: #64748b; font-size: 12px; text-transform: uppercase; }
        .v { margin: 0; font-weight: 700; color: #0f172a; font-size: 15px; }
        .amount { color: #10a860; font-size: 26px; }
        .foot { display: flex; justify-content: space-between; border-top: 1px dashed #cbd5e1; margin-top: 16px; padding-top: 12px; color: #475569; font-size: 13px; }
        @media print { .print-hide { display: none !important; } body { background: #fff; padding: 0; } .receipt { margin: 0; border: 1px solid #dbe3ef; } }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="head">
            <h1>Payment Receipt</h1>
            <p>APCSNSC Membership Payment Confirmation</p>
        </div>
        <div class="body">
            <div class="grid">
                <div class="cell"><p class="k">Receipt No</p><p class="v"><?= esc($receiptNo); ?></p></div>
                <div class="cell"><p class="k">Transaction ID</p><p class="v"><?= esc((string)$txn['transaction_id']); ?></p></div>
                <div class="cell"><p class="k">Member Name</p><p class="v"><?= esc((string)($txn['full_name'] ?? 'N/A')); ?></p></div>
                <div class="cell"><p class="k">Member ID</p><p class="v"><?= esc((string)($txn['member_id'] ?? 'N/A')); ?></p></div>
                <div class="cell"><p class="k">Payment Date</p><p class="v"><?= esc(date('d M Y, h:i A', strtotime((string)$txn['transaction_date']))); ?></p></div>
                <div class="cell"><p class="k">Payment Mode</p><p class="v"><?= esc(ucwords(strtolower(str_replace('_', ' ', (string)($txn['payment_mode'] ?? 'N/A'))))); ?></p></div>
                <div class="cell"><p class="k">Plan</p><p class="v"><?= esc((string)($masterPlan['plan_name'] ?? 'APCSNSC Membership Plan')); ?></p></div>
                <div class="cell"><p class="k">Status</p><p class="v"><?= esc(strtoupper((string)($txn['payment_status'] ?? 'pending'))); ?></p></div>
            </div>

            <div class="cell" style="margin-bottom: 14px;">
                <p class="k">Amount Received</p>
                <p class="v amount">₹<?= esc(number_format((float)$txn['amount'], 2)); ?></p>
                <?php if ((int)($txn['is_renewal'] ?? 0) === 1): ?>
                <p style="margin: 4px 0 0; color: #64748b; font-size: 12px; font-weight: 600;">(Renewal Payment)</p>
                <?php endif; ?>
            </div>

            <div class="cell">
                <p class="k">Validity</p>
                <p class="v" style="font-weight: 500;"><?= esc($validityLabel); ?> (Expires: <?= esc(date('d M Y', strtotime((string)$expiryDate))); ?>)</p>
            </div>

            <div class="foot">
                <span>Generated on <?= esc(date('d M Y, h:i A')); ?></span>
                <span>APCSNSC Member Portal</span>
            </div>

            <div class="print-hide" style="margin-top: 14px; text-align: right; display: flex; justify-content: flex-end; gap: 10px;">
                <button onclick="window.print()" style="border: none; background: #0f4a7d; color: #fff; border-radius: 8px; padding: 10px 14px; cursor: pointer;">🖨️ Print Receipt</button>
                <button onclick="window.close()" style="border: none; background: #e2e8f0; color: #1e293b; border-radius: 8px; padding: 10px 14px; cursor: pointer;">Close</button>
            </div>
        </div>
    </div>
</body>
</html>