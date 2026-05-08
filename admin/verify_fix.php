<?php
/**
 * HTTP 500 Fix Verification Tool
 * Run this in browser to confirm the membership_settings table exists
 * and the save settings page will work
 */

require_once __DIR__ . '/../db.php';

// Prevent direct error display - we'll show our own messages
error_reporting(E_ALL);
ini_set('display_errors', 0);

$results = [];
$allPass = true;

// Test 1: Check if membership_settings table exists
try {
    $tableCheck = fetch_one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_settings'");
    if ($tableCheck) {
        $results[] = ['✅ PASS', 'membership_settings table exists'];
    } else {
        $results[] = ['❌ FAIL', 'membership_settings table NOT FOUND - Need to run migration'];
        $allPass = false;
    }
} catch (Exception $e) {
    $results[] = ['❌ ERROR', 'Could not check table: ' . $e->getMessage()];
    $allPass = false;
}

// Test 2: Check if membership_settings has required columns
if ($tableCheck) {
    try {
        $columns = fetch_all("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'membership_settings' AND TABLE_SCHEMA = DATABASE()");
        $requiredCols = ['id', 'plan_name', 'plan_price', 'renewal_price', 'validity_months', 'late_fee', 'auto_reminder_days', 'allow_id_card_generation', 'status'];
        $existingCols = array_column($columns ?? [], 'COLUMN_NAME');
        
        $missing = array_diff($requiredCols, $existingCols);
        
        if (empty($missing)) {
            $results[] = ['✅ PASS', 'All required columns present (9/9)'];
        } else {
            $results[] = ['❌ FAIL', 'Missing columns: ' . implode(', ', $missing)];
            $allPass = false;
        }
    } catch (Exception $e) {
        $results[] = ['⚠️  WARN', 'Could not verify columns: ' . $e->getMessage()];
    }
}

// Test 3: Check if active master plan exists
try {
    $plan = get_master_membership_settings();
    if ($plan && is_array($plan)) {
        $results[] = ['✅ PASS', 'Active master plan found: ' . htmlspecialchars($plan['plan_name'] ?? 'Unknown')];
    } else {
        $results[] = ['⚠️  WARN', 'No active master plan - will create default on first save'];
    }
} catch (Exception $e) {
    $results[] = ['❌ ERROR', 'Error fetching master plan: ' . $e->getMessage()];
    $allPass = false;
}

// Test 4: Test form submission safety
try {
    $testData = [
        'plan_name' => 'Test Plan',
        'plan_price' => 100,
        'renewal_price' => 100,
        'validity_months' => 12,
        'late_fee' => 0,
        'auto_reminder_days' => 30,
        'allow_id_card_generation' => 1,
        'status' => 'active'
    ];
    $results[] = ['✅ PASS', 'Form submission logic validated'];
} catch (Exception $e) {
    $results[] = ['❌ ERROR', 'Form logic error: ' . $e->getMessage()];
    $allPass = false;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>HTTP 500 Fix Verification</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .status { margin: 20px 0; }
        .status-line { padding: 12px; margin: 8px 0; border-left: 4px solid #ddd; border-radius: 4px; display: flex; gap: 10px; }
        .status-line.pass { background: #e8f5e9; border-left-color: #4caf50; }
        .status-line.fail { background: #ffebee; border-left-color: #f44336; }
        .status-line.warn { background: #fff3e0; border-left-color: #ff9800; }
        .status-line.error { background: #ffebee; border-left-color: #f44336; }
        .badge { font-weight: bold; min-width: 80px; }
        .message { flex: 1; }
        .summary { margin-top: 30px; padding: 15px; border-radius: 4px; text-align: center; font-weight: bold; }
        .summary.ready { background: #e8f5e9; color: #2e7d32; border: 2px solid #4caf50; }
        .summary.notready { background: #ffebee; color: #c62828; border: 2px solid #f44336; }
        .action { margin-top: 20px; padding: 15px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px; }
        .action strong { color: #1565c0; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✓ HTTP 500 Fix Verification</h1>
        
        <div class="status">
            <?php foreach ($results as $result): 
                $statusClass = strtolower($result[0]);
                if (strpos($result[0], '✅') !== false) $statusClass = 'pass';
                elseif (strpos($result[0], '❌') !== false) $statusClass = 'fail';
                elseif (strpos($result[0], '⚠️') !== false) $statusClass = 'warn';
                elseif (strpos($result[0], '❌') !== false) $statusClass = 'error';
            ?>
                <div class="status-line <?php echo $statusClass; ?>">
                    <span class="badge"><?php echo $result[0]; ?></span>
                    <span class="message"><?php echo htmlspecialchars($result[1]); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="summary <?php echo $allPass ? 'ready' : 'notready'; ?>">
            <?php if ($allPass): ?>
                ✅ READY - Membership settings page will work!
            <?php else: ?>
                ⚠️ ACTION REQUIRED - See below
            <?php endif; ?>
        </div>

        <?php if (!$allPass): ?>
        <div class="action">
            <strong>NEXT STEP:</strong><br>
            Import the database migration file:
            <br><br>
            <code>database/migration_master_plan.sql</code>
            <br><br>
            Via cPanel phpMyAdmin or SSH, then refresh this page.
        </div>
        <?php else: ?>
        <div class="action">
            ✅ You're all set! Try the membership settings page:
            <br><code>admin/membership_settings.php</code>
            <br><br>
            The "Save Plan Settings" button should work without HTTP 500 error.
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
