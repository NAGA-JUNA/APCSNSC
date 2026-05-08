<?php
require_once __DIR__ . '/../db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid request token.');
        redirect_to('admin/payment_bulk.php');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'queue_sms') {
        $masterPlan = get_master_membership_settings();
        $days = (int)(($masterPlan['auto_reminder_days'] ?? null) ?: 30);
        if ($days < 1 || $days > 120) {
            $days = 30;
        }
        $endDate = date('Y-m-d', strtotime('+' . $days . ' days'));

        $members = fetch_all(
            'SELECT m.id
             FROM members m
             LEFT JOIN renewal_alerts ra ON m.id = ra.member_id AND ra.alert_type = "sms_reminder" AND ra.alert_date = CURDATE()
             WHERE m.membership_status IN ("active", "renewal_due")
             AND m.membership_expiry_date BETWEEN CURDATE() AND :end_date
             AND ra.id IS NULL',
            [':end_date' => $endDate]
        );

        $queued = 0;
        foreach ($members as $member) {
            $ok = execute_query(
                'INSERT INTO renewal_alerts (member_id, alert_type, alert_date, is_sent)
                 VALUES (:member_id, "sms_reminder", CURDATE(), 0)',
                [':member_id' => (int)$member['id']]
            );
            if ($ok) {
                $queued++;
            }
        }

        set_flash('success', 'Queued ' . $queued . ' SMS reminder records for renewal follow-up.');
        redirect_to('admin/payment_bulk.php');
    }

    if ($action === 'export_unpaid') {
        redirect_to('admin/payments.php?export=unpaid');
    }
}

$pageTitle = 'Bulk Payment Actions';
$activeMenu = 'payments';
require_once __DIR__ . '/_top.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/payment-system.css')); ?>">

<section class="admin-card" style="margin-bottom: 12px;">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1">Bulk Actions</h3>
            <p class="mb-0 text-secondary">Run bulk reminder and export operations for payments.</p>
        </div>
        <a class="quick-btn" href="<?= esc(base_url('admin/payments.php')); ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
    </div>
</section>

<?php $ok = get_flash('success'); if ($ok): ?>
<section class="admin-card" style="margin-bottom:12px; border-left:4px solid #10a860;"><p class="mb-0"><?= esc($ok); ?></p></section>
<?php endif; ?>
<?php $err = get_flash('error'); if ($err): ?>
<section class="admin-card" style="margin-bottom:12px; border-left:4px solid #dc3545;"><p class="mb-0"><?= esc($err); ?></p></section>
<?php endif; ?>

<section class="admin-layout-2">
    <article class="admin-card">
        <h4>Bulk SMS Renewal Reminders</h4>
        <p class="text-secondary">Create reminder queue entries for members due within configured reminder window.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
            <input type="hidden" name="action" value="queue_sms">
            <button type="submit" class="btn btn-warning"><i class="fa-solid fa-bell me-2"></i>Queue SMS Renewal Reminders</button>
        </form>
    </article>

    <article class="admin-card">
        <h4>Bulk Export Unpaid List</h4>
        <p class="text-secondary">Download unpaid and renewal-due members for external follow-up.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
            <input type="hidden" name="action" value="export_unpaid">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-export me-2"></i>Export Unpaid CSV</button>
        </form>
    </article>
</section>

<?php require_once __DIR__ . '/_bottom.php'; ?>
