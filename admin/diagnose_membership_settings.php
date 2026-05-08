<?php
/**
 * APCSNSC Membership Settings Diagnostic Tool
 * 
 * Run this script to check if membership_settings table exists and is properly configured.
 * URL: /admin/diagnose_membership_settings.php
 */

require_once __DIR__ . '/../db.php';
require_admin();

$pageTitle = 'Membership Settings Diagnostic';
$activeMenu = 'payments';
require_once __DIR__ . '/_top.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/payment-system.css')); ?>">

<section class="admin-card" style="margin-bottom: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="mb-1">Membership Settings Diagnostic</h3>
            <p class="mb-0 text-secondary">Check database configuration and fix issues.</p>
        </div>
        <div class="quick-actions">
            <a class="quick-btn" href="<?= esc(base_url('admin/payments.php')); ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
</section>

<?php

$diagnostics = [];
$hasError = false;

// 1. Check if table exists
try {
    $tableExists = fetch_one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_settings'");
    
    if ($tableExists) {
        $diagnostics[] = ['status' => 'success', 'message' => '✓ membership_settings table exists'];
    } else {
        $diagnostics[] = ['status' => 'error', 'message' => '✗ membership_settings table NOT FOUND'];
        $hasError = true;
    }
} catch (Exception $e) {
    $diagnostics[] = ['status' => 'error', 'message' => '✗ Error checking table: ' . htmlspecialchars($e->getMessage())];
    $hasError = true;
}

// 2. Check table columns
if (!$hasError) {
    try {
        $columns = fetch_all('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "membership_settings"');
        $columnNames = array_column($columns, 'COLUMN_NAME');
        
        $requiredColumns = ['id', 'plan_name', 'plan_price', 'renewal_price', 'validity_months', 'description', 'late_fee', 'auto_reminder_days', 'allow_id_card_generation', 'status', 'created_at', 'updated_at'];
        $missingColumns = array_diff($requiredColumns, $columnNames);
        
        if (empty($missingColumns)) {
            $diagnostics[] = ['status' => 'success', 'message' => '✓ All required columns exist: ' . implode(', ', $requiredColumns)];
        } else {
            $diagnostics[] = ['status' => 'error', 'message' => '✗ Missing columns: ' . implode(', ', $missingColumns)];
            $hasError = true;
        }
    } catch (Exception $e) {
        $diagnostics[] = ['status' => 'error', 'message' => '✗ Error checking columns: ' . htmlspecialchars($e->getMessage())];
        $hasError = true;
    }
}

// 3. Check if active plan exists
if (!$hasError) {
    try {
        $activePlan = fetch_one('SELECT id, plan_name, plan_price, validity_months FROM membership_settings WHERE status = "active" LIMIT 1');
        
        if ($activePlan) {
            $diagnostics[] = [
                'status' => 'success',
                'message' => '✓ Active master plan found: ' . esc($activePlan['plan_name']) . ' (₹' . number_format((float)$activePlan['plan_price'], 2) . ', ' . $activePlan['validity_months'] . ' months)'
            ];
        } else {
            $diagnostics[] = ['status' => 'warning', 'message' => '⚠ No active master plan found'];
            
            // Try to create default plan
            try {
                execute_query(
                    'INSERT INTO membership_settings (plan_name, plan_price, renewal_price, validity_months, description, status) VALUES (?, ?, ?, ?, ?, ?)',
                    ['APCSNSC Membership Plan', 100.00, 100.00, 12, 'Valid membership with ID card and union benefits', 'active']
                );
                $diagnostics[] = ['status' => 'success', 'message' => '✓ Default master plan created successfully'];
            } catch (Exception $e) {
                $diagnostics[] = ['status' => 'error', 'message' => '✗ Could not create default plan: ' . htmlspecialchars($e->getMessage())];
                $hasError = true;
            }
        }
    } catch (Exception $e) {
        $diagnostics[] = ['status' => 'error', 'message' => '✗ Error checking active plan: ' . htmlspecialchars($e->getMessage())];
        $hasError = true;
    }
}

// 4. Check get_master_membership_settings() function
try {
    $masterPlan = get_master_membership_settings();
    
    if ($masterPlan) {
        $diagnostics[] = [
            'status' => 'success',
            'message' => '✓ get_master_membership_settings() returns: ' . esc($masterPlan['plan_name']) . ' (₹' . number_format((float)$masterPlan['plan_price'], 2) . ')'
        ];
    } else {
        $diagnostics[] = ['status' => 'error', 'message' => '✗ get_master_membership_settings() returned null - no plan available'];
        $hasError = true;
    }
} catch (Exception $e) {
    $diagnostics[] = ['status' => 'error', 'message' => '✗ Error calling get_master_membership_settings(): ' . htmlspecialchars($e->getMessage())];
    $hasError = true;
}

?>

<section class="admin-card">
    <h4 style="margin-top: 0; margin-bottom: 16px;">Diagnostic Results</h4>
    
    <?php foreach ($diagnostics as $diag): ?>
        <div style="padding: 10px 12px; margin-bottom: 8px; border-radius: 6px; border-left: 4px solid <?= 
            $diag['status'] === 'success' ? '#10a860' : ($diag['status'] === 'error' ? '#dc3545' : '#ffc107')
        ?>; background-color: <?= 
            $diag['status'] === 'success' ? '#eef7f3' : ($diag['status'] === 'error' ? '#fef1f3' : '#fffbf0')
        ?>; color: <?= 
            $diag['status'] === 'success' ? '#0a6924' : ($diag['status'] === 'error' ? '#842029' : '#856404')
        ?>;">
            <?= $diag['message']; ?>
        </div>
    <?php endforeach; ?>
</section>

<?php if ($hasError): ?>
<section class="admin-card" style="margin-top: 16px; border: 1px solid #dc3545; background-color: #fef1f3;">
    <h4 style="color: #842029; margin-top: 0;">Action Required</h4>
    <p style="color: #842029; margin-bottom: 12px;">The membership_settings table is missing or misconfigured. Please run the database migration:</p>
    
    <div style="background: #fff; padding: 12px; border-radius: 6px; margin-bottom: 12px; font-family: monospace; font-size: 12px; overflow-x: auto;">
        Import via phpMyAdmin or MySQL CLI:<br>
        <code>database/migration_master_plan.sql</code>
    </div>
    
    <p style="color: #842029; margin-bottom: 0; font-size: 13px;">After running the migration, refresh this page to verify the fix.</p>
</section>
<?php else: ?>
<section class="admin-card" style="margin-top: 16px; border: 1px solid #10a860; background-color: #eef7f3;">
    <p style="color: #0a6924; margin: 0;">✓ All systems are working correctly! The Membership Settings page should now function properly.</p>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/_bottom.php'; ?>
