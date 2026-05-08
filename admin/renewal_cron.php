<?php
/**
 * APCSNSC Membership Renewal Cron Job
 * 
 * This script automatically:
 * 1. Marks memberships as renewal_due (30 days before expiry)
 * 2. Marks memberships as expired (after expiry date)
 * 3. Creates renewal alerts
 * 
 * Usage: Add to cPanel cron jobs:
 * 0 0 * * * /usr/bin/php /home/yourusername/public_html/admin/renewal_cron.php
 * 
 * This will run daily at midnight.
 */

require_once __DIR__ . '/../db.php';

// Only run this script from command line or authorized cron
if (php_sapi_name() !== 'cli') {
    // If accessed via web, require a secret token
    $cronSecret = $_GET['secret'] ?? '';
    $configSecret = getenv('APCSNSC_CRON_SECRET') ?: 'your-secret-key-here';
    
    if (empty($cronSecret) || $cronSecret !== $configSecret) {
        http_response_code(403);
        echo 'Unauthorized';
        exit;
    }
}

error_log('[RENEWAL_CRON] Starting membership status check at ' . date('Y-m-d H:i:s'));

try {
    $masterPlan = get_master_membership_settings();
    $reminderDays = (int)(($masterPlan['auto_reminder_days'] ?? null) ?: 30);
    if ($reminderDays < 1 || $reminderDays > 120) {
        $reminderDays = 30;
    }

    // Step 1: Mark expired memberships
    $today = date('Y-m-d');
    $expiredStmt = db()->prepare(
        'UPDATE members 
         SET membership_status = "expired", payment_status = "unpaid"
         WHERE membership_status IN ("active", "renewal_due")
         AND membership_expiry_date < :today
         AND membership_expiry_date IS NOT NULL'
    );
    $expiredStmt->execute([':today' => $today]);
    $expiredCount = $expiredStmt->rowCount();
    
    // Step 2: Mark renewal_due (30 days before expiry)
    $renewalDueDate = date('Y-m-d', strtotime('+' . $reminderDays . ' days'));
    $renewalDueStmt = db()->prepare(
        'UPDATE members 
         SET membership_status = "renewal_due"
         WHERE membership_status = "active"
         AND membership_expiry_date <= :renewal_due_date
         AND membership_expiry_date > :today
         AND membership_expiry_date IS NOT NULL'
    );
    $renewalDueStmt->execute([
        ':renewal_due_date' => $renewalDueDate,
        ':today' => $today,
    ]);
    $renewalDueCount = $renewalDueStmt->rowCount();
    
    // Step 3: Create renewal alerts for urgent renewals (7 days)
    $urgentDate = date('Y-m-d', strtotime('+7 days'));
    
    $members = fetch_all(
        'SELECT m.id 
         FROM members m
         LEFT JOIN renewal_alerts ra ON m.id = ra.member_id AND ra.alert_type = "expiry_urgent"
         WHERE m.membership_status IN ("active", "renewal_due")
         AND m.membership_expiry_date BETWEEN CURDATE() AND :urgent_date
         AND m.membership_expiry_date IS NOT NULL
         AND ra.id IS NULL'
    );
    
    $urgentAlertCount = 0;
    foreach ($members as $member) {
        $result = execute_query(
            'INSERT INTO renewal_alerts (member_id, alert_type, alert_date) 
             VALUES (:member_id, "expiry_urgent", CURDATE())',
            [':member_id' => (int)$member['id']]
        );
        if ($result) {
            $urgentAlertCount++;
        }
    }
    
    // Step 4: Create renewal due alerts (30 days)
    $renewalDate = date('Y-m-d', strtotime('+30 days'));
    
    $members30 = fetch_all(
        'SELECT m.id 
         FROM members m
         LEFT JOIN renewal_alerts ra ON m.id = ra.member_id AND ra.alert_type = "renewal_due"
         WHERE m.membership_status IN ("active", "renewal_due")
            AND m.membership_expiry_date BETWEEN CURDATE() AND :renewal_date
         AND m.membership_expiry_date IS NOT NULL
         AND ra.id IS NULL'
    );
    
    $renewalAlertCount = 0;
    foreach ($members30 as $member) {
        $result = execute_query(
            'INSERT INTO renewal_alerts (member_id, alert_type, alert_date) 
             VALUES (:member_id, "renewal_due", CURDATE())',
            [':member_id' => (int)$member['id']]
        );
        if ($result) {
            $renewalAlertCount++;
        }
    }

    // Step 5: Prepare reminder queue records for members close to expiry.
    $reminderMembers = fetch_all(
        'SELECT m.id
         FROM members m
         LEFT JOIN renewal_alerts ra ON m.id = ra.member_id AND ra.alert_type = "sms_reminder" AND ra.alert_date = CURDATE()
         WHERE m.membership_status IN ("renewal_due", "active")
         AND m.membership_expiry_date BETWEEN CURDATE() AND :reminder_date
         AND ra.id IS NULL',
        [':reminder_date' => $renewalDueDate]
    );

    $smsReminderCount = 0;
    foreach ($reminderMembers as $member) {
        $ok = execute_query(
            'INSERT INTO renewal_alerts (member_id, alert_type, alert_date, is_sent)
             VALUES (:member_id, "sms_reminder", CURDATE(), 0)',
            [':member_id' => (int)$member['id']]
        );
        if ($ok) {
            $smsReminderCount++;
        }
    }
    
    // Log results
    $logMessage = sprintf(
        '[RENEWAL_CRON] Completed: Expired=%d, RenewalDue=%d, UrgentAlerts=%d, RenewalAlerts=%d, SmsQueue=%d, ReminderWindow=%d days at %s',
        $expiredCount,
        $renewalDueCount,
        $urgentAlertCount,
        $renewalAlertCount,
        $smsReminderCount,
        $reminderDays,
        date('Y-m-d H:i:s')
    );
    error_log($logMessage);
    
    if (php_sapi_name() === 'cli') {
        echo $logMessage . PHP_EOL;
    }
    
} catch (Exception $e) {
    error_log('[RENEWAL_CRON] ERROR: ' . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    }
}
