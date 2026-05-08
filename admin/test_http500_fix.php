<?php
/**
 * PRE-DEPLOYMENT TEST SCRIPT
 * 
 * Run this script BEFORE importing the migration to verify your system is ready for the fix
 * 
 * URL: https://apcsn.jnvweb.in/admin/test_http500_fix.php?secret=test123
 */

require_once __DIR__ . '/../db.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== 'test123') {
    http_response_code(403);
    die('Access denied');
}

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: Database connection
$test = ['name' => 'Database Connection', 'status' => 'PASS', 'message' => 'Connected to MySQL'];
try {
    $conn = db();
    $passed++;
} catch (Exception $e) {
    $test['status'] = 'FAIL';
    $test['message'] = 'Cannot connect to database: ' . $e->getMessage();
    $failed++;
}
$tests[] = $test;

// Test 2: Check if membership_settings table exists
$test = ['name' => 'Membership Settings Table', 'status' => 'N/A', 'message' => 'Table not yet created (expected before migration)'];
try {
    $tableExists = fetch_one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_settings'");
    if ($tableExists) {
        $test['status'] = 'EXISTS';
        $test['message'] = 'Table already exists - migration may have been run';
        $passed++;
    } else {
        $test['status'] = 'MISSING';
        $test['message'] = 'Table does not exist - migration needs to be run';
    }
} catch (Exception $e) {
    $test['status'] = 'ERROR';
    $test['message'] = 'Error checking table: ' . $e->getMessage();
    $failed++;
}
$tests[] = $test;

// Test 3: Required functions exist
$test = ['name' => 'Required Functions', 'status' => 'PASS', 'message' => 'All required functions exist'];
$missing = [];
if (!function_exists('get_master_membership_settings')) $missing[] = 'get_master_membership_settings';
if (!function_exists('verify_csrf')) $missing[] = 'verify_csrf';
if (!function_exists('set_flash')) $missing[] = 'set_flash';
if (!function_exists('redirect_to')) $missing[] = 'redirect_to';

if (!empty($missing)) {
    $test['status'] = 'FAIL';
    $test['message'] = 'Missing functions: ' . implode(', ', $missing);
    $failed++;
} else {
    $passed++;
}
$tests[] = $test;

// Test 4: Admin authentication
$test = ['name' => 'Admin Authentication', 'status'] = 'UNKNOWN', 'message' => 'You are ' . (admin_logged_in() ? 'logged in as admin' : 'NOT logged in as admin')];
if (admin_logged_in()) {
    $test['status'] = 'PASS';
    $passed++;
} else {
    $test['status'] = 'FAIL';
    $test['message'] = 'Not logged in as admin - log in first';
    $failed++;
}
$tests[] = $test;

// Test 5: Try calling get_master_membership_settings()
$test = ['name' => 'Master Membership Settings Function', 'status'] = 'TESTING'];
try {
    $result = get_master_membership_settings();
    if ($result === null) {
        $test['status'] = 'NULL';
        $test['message'] = 'Function returned null (expected if table missing) - this is OK';
    } else {
        $test['status'] = 'FOUND';
        $test['message'] = 'Found active plan: ' . esc($result['plan_name'] ?? 'Unknown');
        $passed++;
    }
} catch (Exception $e) {
    $test['status'] = 'ERROR';
    $test['message'] = 'Function threw error: ' . $e->getMessage();
    $failed++;
}
$tests[] = $test;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTTP 500 Fix - Pre-Deployment Test</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-top: 0; }
        .test { padding: 12px; margin: 10px 0; border-left: 4px solid #ccc; border-radius: 4px; }
        .PASS { border-left-color: #10a860; background: #eef7f3; }
        .FAIL { border-left-color: #dc3545; background: #fef1f3; }
        .ERROR { border-left-color: #ffc107; background: #fffbf0; }
        .N-A { border-left-color: #6c757d; background: #f8f9fa; }
        .EXISTS { border-left-color: #0dcaf0; background: #f0f8ff; }
        .MISSING { border-left-color: #ffc107; background: #fffbf0; }
        .UNKNOWN { border-left-color: #6c757d; background: #f8f9fa; }
        .TESTING { border-left-color: #0dcaf0; background: #f0f8ff; }
        .NULL { border-left-color: #0dcaf0; background: #f0f8ff; }
        .FOUND { border-left-color: #10a860; background: #eef7f3; }
        .test-name { font-weight: bold; color: #333; margin-bottom: 4px; }
        .test-status { padding: 4px 8px; border-radius: 4px; display: inline-block; font-weight: bold; font-size: 12px; margin-bottom: 4px; }
        .PASS .test-status { background: #10a860; color: white; }
        .FAIL .test-status { background: #dc3545; color: white; }
        .ERROR .test-status { background: #ffc107; color: black; }
        .test-message { color: #666; margin-top: 4px; font-size: 13px; }
        .summary { padding: 12px; margin: 20px 0; border-radius: 4px; text-align: center; font-weight: bold; }
        .summary-pass { background: #eef7f3; color: #0a6924; border: 1px solid #10a860; }
        .summary-fail { background: #fef1f3; color: #842029; border: 1px solid #dc3545; }
        .actions { margin-top: 20px; padding: 12px; background: #f0f8ff; border-left: 4px solid #0dcaf0; border-radius: 4px; }
        .actions h3 { margin-top: 0; }
        .actions ul { margin: 0; padding-left: 20px; }
        .actions li { margin: 6px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>HTTP 500 Fix - Pre-Deployment Test</h1>
    <p>This test verifies your system is ready for the HTTP 500 error fix.</p>
    
    <div class="summary <?= ($failed === 0) ? 'summary-pass' : 'summary-fail' ?>">
        <?php if ($failed === 0): ?>
            ✓ All tests passed! Your system is ready for the migration.
        <?php else: ?>
            ✗ <?= $failed ?> test(s) failed. See details below.
        <?php endif; ?>
    </div>

    <h2>Test Results</h2>
    <?php foreach ($tests as $test): ?>
        <div class="test <?= str_replace('-', '-', $test['status']); ?>">
            <div class="test-name"><?= $test['name']; ?></div>
            <div class="test-status"><?= $test['status']; ?></div>
            <div class="test-message"><?= $test['message']; ?></div>
        </div>
    <?php endforeach; ?>

    <div class="actions">
        <h3>Next Steps</h3>
        <?php if ($failed === 0): ?>
            <p>✓ Your system is ready! Now:</p>
            <ol>
                <li>Go to: <a href="diagnose_membership_settings.php">diagnose_membership_settings.php</a></li>
                <li>Import the migration file: <code>database/migration_master_plan.sql</code></li>
                <li>Return here to verify the fix worked</li>
            </ol>
        <?php else: ?>
            <p>✗ Fix the errors above, then try again.</p>
            <p>If you need help:</p>
            <ul>
                <li>Check DEPLOYMENT_STEPS.txt</li>
                <li>Email your hosting provider with this test result</li>
            </ul>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
