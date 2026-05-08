<?php
require_once __DIR__ . '/db.php';

$member = null;
$error = null;
$memberPayments = [];
$memberComplaints = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token. Please refresh and try again.';
    }

    $memberId = clean($_POST['member_id'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');

    if ($error === null && ($memberId === '' || strlen($phone) < 10)) {
        $error = 'Enter valid Member ID and phone number.';
    } elseif ($error === null) {
        $member = fetch_one('SELECT * FROM members WHERE member_id = :member_id AND phone = :phone LIMIT 1', [
            ':member_id' => $memberId,
            ':phone' => $phone,
        ]);

        if (!$member) {
            $error = 'No member found with those details.';
        } else {
            $memberPayments = fetch_all(
                'SELECT id, transaction_id, amount, payment_mode, payment_status, transaction_date
                 FROM payment_transactions
                 WHERE member_id = :member_id
                 ORDER BY transaction_date DESC
                 LIMIT 20',
                [':member_id' => (int)$member['id']]
            );

            $memberName = trim((string)($member['full_name'] ?? $member['name'] ?? ''));
            if ($memberName !== '') {
                $memberComplaints = fetch_all(
                    'SELECT id, issue, status, created_at
                     FROM complaints
                     WHERE name = :name AND district = :district
                     ORDER BY created_at DESC
                     LIMIT 10',
                    [':name' => $memberName, ':district' => $member['district']]
                );
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/payment-system.css')); ?>">

<section class="container page-hero fade-in">
    <h1>Member Dashboard</h1>
    <p>Check your registration status and access your member card.</p>
</section>

<section class="section">
    <div class="container">
        <div class="card fade-in">
            <h2>Member Login</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= esc($error); ?></div>
            <?php endif; ?>

            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                <div class="form-group">
                    <label>Member ID</label>
                    <input type="text" name="member_id" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" required>
                </div>
                <div class="form-group full">
                    <button class="btn btn-primary" type="submit">Check Dashboard</button>
                </div>
            </form>
        </div>

        <?php if ($member): ?>
            <div class="card fade-in" style="margin-top:18px;">
                <h3><?= esc($member['name'] ?? $member['full_name'] ?? ''); ?></h3>
                <p><strong>Member ID:</strong> <?= esc($member['member_id']); ?></p>
                <p><strong>Status:</strong> <?= esc(ucfirst($member['status'])); ?></p>
                <p><strong>District:</strong> <?= esc($member['district']); ?></p>
                
                <!-- Payment Status Section -->
                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e2e8f0;">
                    <h4 style="margin-bottom: 16px;">Membership Payment Status</h4>
                    
                    <?php
                    $paymentStatus = strtolower((string)($member['payment_status'] ?? 'unpaid'));
                    $membershipStatus = strtolower((string)($member['membership_status'] ?? 'unpaid'));
                    $expiryDate = $member['membership_expiry_date'] ?? null;
                    $isExpired = $expiryDate && strtotime($expiryDate) < time();
                    $isExpiring = $expiryDate && !$isExpired && (strtotime($expiryDate) - time()) <= 30 * 86400;
                    ?>
                    
                    <!-- Alert if unpaid -->
                    <?php if ($paymentStatus === 'unpaid' || $paymentStatus === 'pending'): ?>
                        <div class="payment-alert danger">
                            <div class="payment-alert-icon">
                                <i class="fa-solid fa-exclamation-circle"></i>
                            </div>
                            <div class="payment-alert-content">
                                <div class="payment-alert-title">Payment Pending</div>
                                <div class="payment-alert-text">
                                    Your membership payment is pending. Please contact the office to complete your payment and activate ID card access.
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Alert if expired -->
                    <?php if ($isExpired && $membershipStatus === 'expired'): ?>
                        <div class="payment-alert danger">
                            <div class="payment-alert-icon">
                                <i class="fa-solid fa-calendar-times"></i>
                            </div>
                            <div class="payment-alert-content">
                                <div class="payment-alert-title">Membership Expired</div>
                                <div class="payment-alert-text">
                                    Your membership expired on <?= esc(date('d M Y', strtotime((string)$expiryDate))); ?>. 
                                    Please renew your membership to regain access to ID card and member benefits.
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Alert if expiring soon -->
                    <?php if ($isExpiring && !$isExpired && $membershipStatus === 'active'): ?>
                        <div class="payment-alert warning">
                            <div class="payment-alert-icon">
                                <i class="fa-solid fa-bell"></i>
                            </div>
                            <div class="payment-alert-content">
                                <div class="payment-alert-title">Renewal Due Soon</div>
                                <div class="payment-alert-text">
                                    Your membership will expire on <?= esc(date('d M Y', strtotime((string)$expiryDate))); ?>. 
                                    Please plan your renewal payment soon.
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Success if active -->
                    <?php if ($paymentStatus === 'paid' && $membershipStatus === 'active' && !$isExpired): ?>
                        <div class="payment-alert success">
                            <div class="payment-alert-icon">
                                <i class="fa-solid fa-check-circle"></i>
                            </div>
                            <div class="payment-alert-content">
                                <div class="payment-alert-title">Membership Active</div>
                                <div class="payment-alert-text">
                                    Your membership is active and valid until <?= esc(date('d M Y', strtotime((string)$expiryDate))); ?>.
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Membership Details -->
                    <div style="background: #f8fafc; border-radius: 12px; padding: 16px; margin-top: 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                        <div>
                            <p style="margin: 0 0 6px; font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Plan</p>
                            <p style="margin: 0; font-weight: 600; color: #0f172a;"><?= esc($member['plan_name'] ?? 'Not Set'); ?></p>
                        </div>
                        <div>
                            <p style="margin: 0 0 6px; font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Valid Until</p>
                            <p style="margin: 0; font-weight: 600; color: #0f172a;">
                                <?= $expiryDate ? esc(date('d M Y', strtotime((string)$expiryDate))) : 'N/A'; ?>
                            </p>
                        </div>
                        <div>
                            <p style="margin: 0 0 6px; font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Status</p>
                            <p style="margin: 0;">
                                <?php
                                if ($paymentStatus === 'paid' && $membershipStatus === 'active') {
                                    echo '<span class="badge badge-success">Active Paid</span>';
                                } elseif ($membershipStatus === 'expired') {
                                    echo '<span class="badge badge-secondary">Expired</span>';
                                } elseif ($membershipStatus === 'renewal_due') {
                                    echo '<span class="badge badge-warning">Renewal Due</span>';
                                } else {
                                    echo '<span class="badge badge-danger">Unpaid</span>';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <p style="margin: 0 0 6px; font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Renewals</p>
                            <p style="margin: 0; font-weight: 600; color: #0f172a;"><?= esc((string)($member['renewal_count'] ?? 0)); ?> times</p>
                        </div>
                    </div>

                    <div style="margin-top: 16px; display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php if ($membershipStatus === 'expired'): ?>
                            <span class="badge badge-danger" style="padding: 8px 12px;">Your membership expired. Please renew now.</span>
                        <?php endif; ?>
                        <a class="btn btn-secondary" href="<?= esc(base_url('member_dashboard.php')); ?>" style="background-color:#0f4a7d;color:#fff;padding:10px 14px;text-decoration:none;border-radius:8px;font-weight:600;">Renew Now</a>
                        <?php if (can_generate_id_card($member)): ?>
                            <a class="btn btn-secondary" href="<?= esc(base_url('id_card.php?member_id=' . urlencode((string)$member['member_id']))); ?>" target="_blank" style="background-color: #10a860; color: white; padding: 10px 14px; text-decoration: none; border-radius: 8px; font-weight: 600;">
                                <i class="fa-solid fa-id-card me-2"></i>Download ID Card
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ID Card Section -->
                <div style="margin-top: 24px;">
                    <?php if (can_generate_id_card($member)): ?>
                        <p>
                            <a class="btn btn-secondary" href="<?= esc(base_url('id_card.php?member_id=' . urlencode((string)$member['member_id']))); ?>" target="_blank" style="background-color: #10a860; color: white; padding: 12px 20px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: 600;">
                                <i class="fa-solid fa-id-card me-2"></i>Download / Print ID Card
                            </a>
                        </p>
                    <?php else: ?>
                        <div class="payment-alert danger">
                            <div class="payment-alert-icon">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <div class="payment-alert-content">
                                <div class="payment-alert-title">ID Card Not Available</div>
                                <div class="payment-alert-text">
                                    ID card generation is only available for active paid members. Please complete your payment to generate your ID card.
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e2e8f0;">
                    <h4 style="margin-bottom: 12px;">Payment History</h4>
                    <?php if (empty($memberPayments)): ?>
                        <p style="color:#64748b; margin: 0;">No payment history available yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Receipt No</th>
                                        <th>Transaction ID</th>
                                        <th>Amount</th>
                                        <th>Mode</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($memberPayments as $payment): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= esc(base_url('receipt.php?txn_id=' . urlencode((string)$payment['transaction_id']) . '&member_id=' . urlencode((string)$member['member_id']))); ?>" target="_blank" title="Download Receipt" style="text-decoration:none; color:#0f4a7d;">
                                                    <small><code><?= esc(get_receipt_no($payment)); ?> <i class="fa-solid fa-download ms-1"></i></code></small>
                                                </a>
                                            </td>
                                            <td><small><code><?= esc((string)$payment['transaction_id']); ?></code></small></td>
                                            <td><strong style="color:#10a860;">₹<?= esc(number_format((float)$payment['amount'], 2)); ?></strong></td>
                                            <td><?= esc((string)$payment['payment_mode']); ?></td>
                                            <td><?= get_payment_status_badge((string)$payment['payment_status']); ?></td>
                                            <td><?= esc(date('d M Y', strtotime((string)$payment['transaction_date']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- My Complaints Section -->
                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e2e8f0;">
                    <h4 style="margin-bottom: 12px;">My Complaints</h4>
                    <?php if (empty($memberComplaints)): ?>
                        <p style="color:#64748b; margin: 0;">No complaints found for your name and district.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Complaint ID</th>
                                        <th>Issue</th>
                                        <th>Status</th>
                                        <th>Date Submitted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($memberComplaints as $complaint): ?>
                                        <tr>
                                            <td><small><code>#CMP-<?= esc(str_pad((string)$complaint['id'], 5, '0', STR_PAD_LEFT)); ?></code></small></td>
                                            <td><strong><?= esc((string)$complaint['issue']); ?></strong></td>
                                            <td>
                                                <?php
                                                $cStatus = strtolower((string)($complaint['status'] ?? 'pending'));
                                                if ($cStatus === 'resolved' || $cStatus === 'closed') {
                                                    echo '<span class="badge badge-success">' . esc(ucfirst($cStatus)) . '</span>';
                                                } elseif ($cStatus === 'in-progress') {
                                                    echo '<span class="badge badge-warning">' . esc(ucfirst($cStatus)) . '</span>';
                                                } else {
                                                    echo '<span class="badge badge-secondary">' . esc(ucfirst($cStatus)) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?= esc(date('d M Y', strtotime((string)$complaint['created_at']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 16px;">
                        <a href="<?= esc(base_url('pages/contact.php')); ?>" class="btn btn-outline" style="padding: 8px 16px; border-radius: 8px; font-size: 14px; text-decoration: none; font-weight: 600;">Raise New Complaint</a>
                    </div>
                </div>

                <!-- Member Photo -->
                <?php if (!empty($member['photo'])): ?>
                    <div style="margin-top: 24px;">
                        <p style="font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Profile Photo</p>
                        <img src="<?= esc(base_url((string)$member['photo'])); ?>" alt="Member Photo" style="width:120px;height:120px;border-radius:12px;object-fit:cover;">
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
