<?php
require_once __DIR__ . '/../db.php';
require_admin();

$txnId = (int)($_GET['id'] ?? 0);
if ($txnId <= 0) {
    http_response_code(404);
    echo 'Invalid receipt request.';
    exit;
}

$txn = fetch_one(
    'SELECT pt.*, m.member_id, m.full_name, m.phone, m.district
     FROM payment_transactions pt
     LEFT JOIN members m ON pt.member_id = m.id
     WHERE pt.id = :id
     LIMIT 1',
    [':id' => $txnId]
);

if (!$txn) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}

$plan = get_master_membership_settings();
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
        <h1>APCSNSC Payment Receipt</h1>
        <p>Official membership payment acknowledgment</p>
    </div>
    <div class="body">
        <div class="grid">
            <div class="cell"><p class="k">Receipt No</p><p class="v"><?= esc($receiptNo); ?></p></div>
            <div class="cell"><p class="k">Transaction ID</p><p class="v"><?= esc((string)$txn['transaction_id']); ?></p></div>
            <div class="cell"><p class="k">Member Name</p><p class="v"><?= esc((string)($txn['full_name'] ?? 'N/A')); ?></p></div>
            <div class="cell"><p class="k">Member ID</p><p class="v"><?= esc((string)($txn['member_id'] ?? 'N/A')); ?></p></div>
            <div class="cell"><p class="k">Payment Date</p><p class="v"><?= esc(date('d M Y, h:i A', strtotime((string)$txn['transaction_date']))); ?></p></div>
            <div class="cell"><p class="k">Payment Mode</p><p class="v"><?= esc((string)($txn['payment_mode'] ?? 'N/A')); ?></p></div>
            <div class="cell"><p class="k">Plan</p><p class="v"><?= esc((string)($plan['plan_name'] ?? 'APCSNSC Membership Plan')); ?></p></div>
            <div class="cell"><p class="k">Status</p><p class="v"><?= esc(strtoupper((string)($txn['payment_status'] ?? 'pending'))); ?></p></div>
        </div>

        <div class="cell" style="margin-bottom: 14px;">
            <p class="k">Amount Received</p>
            <p class="v amount">₹<?= esc(number_format((float)$txn['amount'], 2)); ?></p>
        </div>

        <div class="cell">
            <p class="k">Remarks</p>
            <p class="v" style="font-weight: 500;"><?= esc((string)($txn['remarks'] ?? 'N/A')); ?></p>
        </div>

        <div class="foot">
            <span>Generated on <?= esc(date('d M Y, h:i A')); ?></span>
            <span>APCSNSC Admin Billing Panel</span>
        </div>

        <div class="print-hide" style="margin-top: 14px; text-align: right;">
            <button onclick="window.print()" style="border: none; background: #0f4a7d; color: #fff; border-radius: 8px; padding: 10px 14px; cursor: pointer;">Print Receipt</button>
        </div>
    </div>
</div>
</body>
</html>
