<?php
require_once __DIR__ . '/../db.php';
require_member();

$transactionId = clean((string)($_GET['txn_id'] ?? ''));
$memberId = (int)($_SESSION['member_id'] ?? 0);

if (!$transactionId || $memberId <= 0) {
    die('<h2>Invalid receipt request</h2>');
}

// Fetch transaction details
$txn = fetch_one(
    'SELECT pt.*, m.member_id, m.full_name, m.phone, m.district, m.email
     FROM payment_transactions pt
     LEFT JOIN members m ON pt.member_id = m.id
     WHERE pt.transaction_id = :txn_id AND pt.member_id = :member_id LIMIT 1',
    [':txn_id' => $transactionId, ':member_id' => $memberId]
);

if (!$txn) {
    die('<h2>Receipt not found</h2>');
}

// Get membership settings
$masterPlan = get_master_membership_settings();

// Get approved date and approver
$approverQuery = fetch_one(
    'SELECT u.username FROM users u WHERE u.id = :admin_id LIMIT 1',
    [':admin_id' => (int)($txn['approved_by'] ?? 0)]
);
$approverName = $approverQuery['username'] ?? 'System';

// Calculate membership validity
$validityMonths = (int)(($masterPlan['validity_months'] ?? null) ?: 12);
$validityLabel = get_membership_validity_label($validityMonths);

// Membership dates
$memberInfo = fetch_one('SELECT membership_expiry_date FROM members WHERE id = :id', [':id' => $memberId]);
$expiryDate = $memberInfo['membership_expiry_date'] ?? date('Y-m-d', strtotime('+' . $validityMonths . ' months'));

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?= esc($txn['transaction_id']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .receipt-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .receipt-header h1 { font-size: 28px; margin-bottom: 5px; }
        .receipt-header p { font-size: 14px; opacity: 0.9; }

        .receipt-number {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            letter-spacing: 1px;
        }

        .receipt-body {
            padding: 40px 30px;
        }

        .receipt-section {
            margin-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 20px;
        }

        .receipt-section:last-of-type {
            border-bottom: none;
        }

        .receipt-section h3 {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 15px;
            letter-spacing: 0.5px;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 14px;
        }

        .receipt-row.highlight {
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
        }

        .receipt-row label { color: #64748b; font-weight: 500; }
        .receipt-row value { color: #0f172a; font-weight: 600; }

        .amount-section {
            background: #e8f5e9;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .amount-section .receipt-row { margin: 10px 0; }
        .amount-section .receipt-row label { color: #1b5e20; }
        .amount-section .receipt-row value { color: #2e7d32; font-weight: 700; font-size: 18px; }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-approved { background: #d1f7d8; color: #0a6924; }
        .status-pending { background: #fff3cd; color: #664d03; }
        .status-failed { background: #f8d7da; color: #842029; }

        .footer {
            text-align: center;
            padding: 30px;
            border-top: 2px solid #e5e7eb;
            font-size: 12px;
            color: #64748b;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
            padding: 0 30px;
        }

        button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-print {
            background: #0f172a;
            color: white;
        }

        .btn-print:hover { background: #1e293b; }

        .btn-back {
            background: #e5e7eb;
            color: #1f2937;
        }

        .btn-back:hover { background: #d1d5db; }

        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; }
            .action-buttons { display: none; }
            .footer { border-top: 1px solid #e5e7eb; }
        }

        @media (max-width: 600px) {
            .receipt-body { padding: 20px; }
            .receipt-header { padding: 25px 15px; }
            button { padding: 8px 16px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Header -->
        <div class="receipt-header">
            <h1>Payment Receipt</h1>
            <p>APCSNSC Membership Payment Confirmation</p>
            <div class="receipt-number"><?= esc($txn['transaction_id']); ?></div>
        </div>

        <!-- Body -->
        <div class="receipt-body">
            <!-- Payment Status -->
            <div class="receipt-section">
                <div class="receipt-row">
                    <label>Payment Status</label>
                    <value><span class="status-badge status-<?= strtolower(esc($txn['payment_status'])); ?>"><?= esc(strtoupper($txn['payment_status'])); ?></span></value>
                </div>
                <div class="receipt-row">
                    <label>Receipt Date</label>
                    <value><?= esc(date('d M Y, h:i A', strtotime((string)($txn['transaction_date'] ?? 'now')))); ?></value>
                </div>
            </div>

            <!-- Member Information -->
            <div class="receipt-section">
                <h3>Member Information</h3>
                <div class="receipt-row highlight">
                    <label>Member ID</label>
                    <value><?= esc($txn['member_id']); ?></value>
                </div>
                <div class="receipt-row">
                    <label>Member Name</label>
                    <value><?= esc($txn['full_name'] ?? 'N/A'); ?></value>
                </div>
                <div class="receipt-row">
                    <label>Phone</label>
                    <value><?= esc($txn['phone'] ?? 'N/A'); ?></value>
                </div>
                <div class="receipt-row">
                    <label>District</label>
                    <value><?= esc($txn['district'] ?? 'N/A'); ?></value>
                </div>
            </div>

            <!-- Plan Details -->
            <div class="receipt-section">
                <h3>Membership Plan Details</h3>
                <div class="receipt-row">
                    <label>Plan Name</label>
                    <value><?= esc($masterPlan['plan_name'] ?? 'APCSNSC Membership'); ?></value>
                </div>
                <div class="receipt-row">
                    <label>Validity Period</label>
                    <value><?= esc($validityLabel); ?></value>
                </div>
                <div class="receipt-row">
                    <label>Membership Valid Until</label>
                    <value><?= esc(date('d M Y', strtotime((string)$expiryDate))); ?></value>
                </div>
            </div>

            <!-- Amount Section -->
            <div class="amount-section">
                <div class="receipt-row">
                    <label>Plan Amount</label>
                    <value>₹<?= esc(number_format((float)($txn['amount'] ?? 0), 2)); ?></value>
                </div>
                <?php if ((int)($txn['is_renewal'] ?? 0) === 1): ?>
                <div class="receipt-row" style="font-size: 12px; color: #555;">
                    <label>Type</label>
                    <value>Renewal Payment</value>
                </div>
                <?php endif; ?>
            </div>

            <!-- Payment Information -->
            <div class="receipt-section">
                <h3>Payment Information</h3>
                <div class="receipt-row">
                    <label>Payment Mode</label>
                    <value><?= esc(ucwords(strtolower(str_replace('_', ' ', $txn['payment_mode'])))); ?></value>
                </div>
                <div class="receipt-row">
                    <label>Approved Date</label>
                    <value><?= esc(date('d M Y, h:i A', strtotime((string)($txn['approved_date'] ?? 'now')))); ?></value>
                </div>
                <div class="receipt-row">
                    <label>Approved By</label>
                    <value><?= esc($approverName); ?></value>
                </div>
                <?php if (!empty($txn['remarks'])): ?>
                <div class="receipt-row">
                    <label>Remarks</label>
                    <value><?= esc($txn['remarks']); ?></value>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn-print" onclick="window.print()">
                <span>🖨️ Print Receipt</span>
            </button>
            <button class="btn-back" onclick="history.back()">
                <span>← Back</span>
            </button>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is an electronically generated receipt. No signature is required.</p>
            <p>For questions or disputes, please contact APCSNSC administration.</p>
            <p style="margin-top: 10px; color: #94a3b8;">Generated on <?= esc(date('d M Y, h:i:s A')); ?></p>
        </div>
    </div>

    <script>
        // Optional: Auto-focus print dialog on page load
        // Uncomment if desired
        // window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
