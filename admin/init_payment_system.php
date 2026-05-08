<?php
/**
 * APCSNSC Payment System Initialization Script
 * 
 * Run this once after deploying the payment system to:
 * 1. Set default payment status for existing members
 * 2. Initialize master membership settings
 * 3. Verify database schema
 * 
 * Access via: /admin/init_payment_system.php?secret=your-secret
 */

require_once __DIR__ . '/../db.php';

$secret = $_GET['secret'] ?? '';
$configSecret = getenv('APCSNSC_INIT_SECRET') ?: 'change-me-secret';

if (empty($secret) || $secret !== $configSecret) {
    http_response_code(403);
    echo '<h1>Access Denied</h1>';
    echo '<p>Invalid or missing initialization secret.</p>';
    echo '<p>Set APCSNSC_INIT_SECRET environment variable or pass correct secret in URL:</p>';
    echo '<code>/admin/init_payment_system.php?secret=your-secret-key</code>';
    exit;
}

require_admin();

$output = [];

try {
    // Step 1: Check table structure
    $output[] = '✓ Checking membership and payment columns...';
    
    $columns = fetch_all('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "members"');
    $columnNames = array_column($columns, 'COLUMN_NAME');
    
    $requiredColumns = ['payment_status', 'membership_status', 'plan_name', 'plan_amount', 'membership_start_date', 'membership_expiry_date'];
    $missingColumns = array_diff($requiredColumns, $columnNames);
    
    if (!empty($missingColumns)) {
        $output[] = '⚠ Missing columns: ' . implode(', ', $missingColumns);
        $output[] = 'Please run migration_payment_system.sql first!';
    } else {
        $output[] = '✓ All required columns exist';
    }
    
    // Step 2: Initialize existing members with default payment status
    $output[] = '';
    $output[] = '✓ Initializing existing members...';
    
    $updateResult = execute_query(
        'UPDATE members 
         SET membership_status = "unpaid", 
             payment_status = "unpaid"
         WHERE (membership_status IS NULL OR membership_status = "" OR payment_status IS NULL OR payment_status = "")'
    );
    
    $approvedCount = (int)(fetch_one('SELECT COUNT(*) AS total FROM members WHERE status IN ("approved", "active")')['total'] ?? 0);
    $output[] = "✓ Set unpaid status for members without payment status";
    $output[] = "Note: $approvedCount members have approved/active status. Consider manually activating their payments.";
    
    // Step 3: Verify master membership settings
    $output[] = '';
    $output[] = '✓ Checking master membership settings...';

    $settingsTableExists = fetch_one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_settings'");

    if (!$settingsTableExists) {
        $output[] = '⚠ membership_settings table not found. Run database/migration_master_membership_settings.sql';
    } else {
        $activeSettings = fetch_one('SELECT COUNT(*) AS total FROM membership_settings WHERE status = "active"');
        $activeCount = (int)($activeSettings['total'] ?? 0);

        if ($activeCount === 0) {
            execute_query(
                'INSERT INTO membership_settings
                (plan_name, plan_price, renewal_price, validity_months, description, late_fee, auto_reminder_days, allow_id_card_generation, status)
                VALUES
                ("APCSNSC Membership Plan", 100.00, 100.00, 12, "Valid membership with ID card and union benefits", 0.00, 30, 1, "active")'
            );
            $output[] = '✓ Default master membership plan created';
        } else {
            $current = fetch_one('SELECT plan_name, plan_price, validity_months FROM membership_settings WHERE status = "active" ORDER BY updated_at DESC, id DESC LIMIT 1');
            $output[] = '✓ Active master plan found: ' . (string)($current['plan_name'] ?? 'Membership Plan') . ' (₹' . number_format((float)($current['plan_price'] ?? 0), 2) . ')';
        }
    }

    // Step 4: Legacy membership_plans check (backward compatibility)
    $output[] = '';
    $output[] = '✓ Checking legacy membership_plans table (compatibility)...';
    
    $planCount = (int)(fetch_one('SELECT COUNT(*) AS total FROM membership_plans')['total'] ?? 0);
    
    if ($planCount === 0) {
        $output[] = '⚠ No membership plans found. Creating default plans...';
        
        execute_query(
            'INSERT INTO membership_plans (plan_name, plan_description, amount, validity_months) 
             VALUES 
             ("Basic Plan", "1 Year valid membership", 100.00, 12),
             ("Premium Plan", "3 Years valid membership", 300.00, 36),
             ("Lifetime Plan", "Lifetime membership without renewal", 1000.00, 1200)'
        );
        
        $output[] = '✓ Default plans created successfully';
    } else {
        $output[] = "✓ Found $planCount membership plans";
    }
    
    // Step 5: Verify transaction table exists
    $output[] = '';
    $output[] = '✓ Checking transaction table...';
    
    $tableExists = fetch_one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_transactions'");
    
    if ($tableExists) {
        $txnCount = (int)(fetch_one('SELECT COUNT(*) AS total FROM payment_transactions')['total'] ?? 0);
        $output[] = "✓ Payment transactions table exists ($txnCount records)";
    } else {
        $output[] = '⚠ Payment transactions table not found. Run migration SQL.';
    }
    
    // Step 6: Check for renewal alerts table
    $output[] = '';
    $output[] = '✓ Checking renewal alerts table...';
    
    $alertsExists = fetch_one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'renewal_alerts'");
    
    if ($alertsExists) {
        $alertCount = (int)(fetch_one('SELECT COUNT(*) AS total FROM renewal_alerts')['total'] ?? 0);
        $output[] = "✓ Renewal alerts table exists ($alertCount records)";
    } else {
        $output[] = '⚠ Renewal alerts table not found. Run migration SQL.';
    }
    
    // Step 7: Summary
    $output[] = '';
    $output[] = '═══════════════════════════════════════';
    $output[] = '✓ INITIALIZATION COMPLETE';
    $output[] = '═══════════════════════════════════════';
    $output[] = '';
    $output[] = 'Next steps:';
    $output[] = '1. Admin Dashboard - Check payment stat cards';
    $output[] = '2. Admin Payments - View payments management';
    $output[] = '3. Set up cron job for renewal_cron.php';
    $output[] = '';
    $output[] = 'Add to cron (runs daily at midnight):';
    $output[] = '/usr/bin/php ' . __DIR__ . '/renewal_cron.php';
    $output[] = '';
    $output[] = 'Or access via web (set secret in .env):';
    $output[] = base_url('admin/renewal_cron.php?secret=your-secret');
    
} catch (Exception $e) {
    $output[] = '';
    $output[] = '✗ ERROR: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment System Initialization</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 40px 20px;
            color: #333;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0f172a;
            margin-bottom: 30px;
            border-bottom: 3px solid #10a860;
            padding-bottom: 15px;
        }
        pre {
            background: #f8fafc;
            border-left: 4px solid #10a860;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #0f172a;
        }
        .success { color: #10a860; }
        .warning { color: #f77f00; }
        .error { color: #dc3545; }
        .note {
            background: #fffbea;
            border-left: 4px solid #f77f00;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 13px;
        }
        code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>APCSNSC Payment System Initialization</h1>
        <pre><?php foreach ($output as $line) {
            $line = esc($line);
            if (strpos($line, '✓') === 0) {
                echo '<span class="success">' . $line . '</span>';
            } elseif (strpos($line, '✗') === 0) {
                echo '<span class="error">' . $line . '</span>';
            } elseif (strpos($line, '⚠') === 0) {
                echo '<span class="warning">' . $line . '</span>';
            } elseif (strpos($line, '═') === 0) {
                echo '<strong>' . $line . '</strong>';
            } else {
                echo $line;
            }
            echo PHP_EOL;
        } ?></pre>
        
        <div class="note">
            <strong>🔐 Security Note:</strong> This initialization page requires a secret token. Keep your <code>APCSNSC_INIT_SECRET</code> environment variable safe and consider disabling this page after initial setup.
        </div>
    </div>
</body>
</html>
