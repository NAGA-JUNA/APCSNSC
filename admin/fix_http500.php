<?php
/**
 * LIVE SERVER DIAGNOSTIC & GUIDANCE TOOL
 * This page helps the user fix the HTTP 500 error RIGHT NOW
 * Visit: https://apcsn.jnvweb.in/admin/fix_http500.php
 */

require_once __DIR__ . '/../db.php';

$errors = [];
$status = 'NOT_FIXED';
$dbConnected = false;
$tableExists = false;
$planExists = false;

// Step 1: Check database connection
try {
    $testConn = fetch_one("SELECT 1 LIMIT 1");
    $dbConnected = true;
} catch (Exception $e) {
    $errors[] = "Database connection failed: " . $e->getMessage();
}

// Step 2: Check if membership_settings table exists
if ($dbConnected) {
    try {
        $tableCheck = fetch_one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_settings' LIMIT 1");
        $tableExists = $tableCheck ? true : false;
    } catch (Exception $e) {
        $errors[] = "Could not check table: " . $e->getMessage();
    }
}

// Step 3: Check if active plan exists
if ($tableExists) {
    try {
        $plan = get_master_membership_settings();
        $planExists = $plan ? true : false;
    } catch (Exception $e) {
        $errors[] = "Could not fetch plan: " . $e->getMessage();
    }
}

// Determine status
if ($tableExists && $planExists) {
    $status = 'FIXED';
} elseif ($tableExists && !$planExists) {
    $status = 'PARTIAL';
} else {
    $status = 'NOT_FIXED';
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix HTTP 500 Error</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-top: 0; }
        .status-card { padding: 20px; border-radius: 6px; margin: 20px 0; font-size: 16px; }
        .status-fixed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-partial { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-not-fixed { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .step { margin: 25px 0; padding: 20px; background: #f8f9fa; border-left: 4px solid #2196f3; border-radius: 4px; }
        .step h3 { margin-top: 0; color: #2196f3; }
        .step p { margin: 10px 0; }
        code { background: #e8e8e8; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .command-box { background: #2d3436; color: #00ff00; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; margin: 10px 0; overflow-x: auto; font-size: 13px; }
        .button { display: inline-block; background: #2196f3; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; margin: 10px 0; }
        .button:hover { background: #1976d2; }
        .button-success { background: #4caf50; }
        .button-success:hover { background: #388e3c; }
        .error-list { background: #ffebee; border: 1px solid #ef5350; padding: 15px; border-radius: 4px; color: #c62828; }
        .error-list li { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix HTTP 500 Error - APCSNSC Membership Settings</h1>

        <!-- STATUS -->
        <div class="status-card status-<?php echo strtolower($status); ?>">
            <strong>Current Status:</strong> 
            <?php if ($status === 'FIXED'): ?>
                ✅ FIXED - Membership settings page is working!
            <?php elseif ($status === 'PARTIAL'): ?>
                ⚠️ PARTIAL - Table exists but no plan yet
            <?php else: ?>
                ❌ NOT FIXED - Migration has not been imported
            <?php endif; ?>
        </div>

        <!-- ERRORS -->
        <?php if (!empty($errors)): ?>
        <div class="error-list">
            <strong>System Messages:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- DIAGNOSTIC TABLE -->
        <table>
            <tr>
                <th>Component</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>Database Connection</td>
                <td><?php echo $dbConnected ? '✅ Connected' : '❌ Failed'; ?></td>
            </tr>
            <tr>
                <td>membership_settings Table</td>
                <td><?php echo $tableExists ? '✅ Exists' : '❌ Missing'; ?></td>
            </tr>
            <tr>
                <td>Active Master Plan</td>
                <td><?php echo $planExists ? '✅ Exists' : '❌ Missing'; ?></td>
            </tr>
        </table>

        <?php if ($status === 'FIXED'): ?>
        <!-- SUCCESS -->
        <div class="step">
            <h3>✅ HTTP 500 Error is FIXED!</h3>
            <p>All systems are working correctly. You can now use the membership settings page without errors.</p>
            <p><a class="button button-success" href="../membership_settings.php">→ Go to Membership Settings</a></p>
        </div>

        <?php else: ?>
        <!-- INSTRUCTIONS -->
        <div class="step">
            <h3>Step 1: Import the Migration</h3>
            <p>You need to import the database migration file to create the membership_settings table.</p>
            
            <strong>Option A: Via cPanel phpMyAdmin (Easiest)</strong>
            <ol>
                <li>Login to <strong>cPanel</strong></li>
                <li>Click <strong>phpMyAdmin</strong></li>
                <li>In left sidebar, select database: <code>svaobtfy_apcsnsc</code></li>
                <li>Click <strong>"Import"</strong> tab</li>
                <li>Click <strong>"Choose File"</strong></li>
                <li>Select: <code>database/migration_master_plan.sql</code></li>
                <li>Scroll down and click <strong>"Go"</strong></li>
                <li>Wait for success message</li>
            </ol>

            <strong>Option B: Via SSH/Terminal</strong>
            <p>Run this command:</p>
            <div class="command-box">mysql -h localhost -u svaobtfy_apcsnsc -p svaobtfy_apcsnsc &lt; migration_master_plan.sql</div>
            <p>When prompted for password, enter: <code>apcsn@2026</code></p>
        </div>

        <div class="step">
            <h3>Step 2: Verify the Fix</h3>
            <p>After importing, <strong>refresh this page</strong> to verify the fix worked.</p>
            <p><a class="button" href="fix_http500.php">→ Refresh Status</a></p>
        </div>

        <div class="step">
            <h3>Step 3: Test the Membership Settings Page</h3>
            <p>Once the status shows ✅ FIXED, you can test the actual page:</p>
            <p><a class="button button-success" href="../membership_settings.php">→ Go to Membership Settings</a></p>
            <p>Try clicking "Save Plan Settings" - it should work without error!</p>
        </div>

        <?php endif; ?>

        <!-- TROUBLESHOOTING -->
        <div class="step">
            <h3>❓ Troubleshooting</h3>
            <p><strong>Import failed with error?</strong><br>
            Contact your hosting provider and show them this error. They can import the file for you.</p>
            
            <p><strong>Status still shows NOT FIXED after importing?</strong><br>
            Wait 30 seconds, then <a href="fix_http500.php">refresh this page</a>.</p>
            
            <p><strong>Getting HTTP 500 on membership_settings.php before importing?</strong><br>
            That's expected - it will show an error message until the migration is imported.</p>
        </div>

        <hr>
        <p style="font-size: 12px; color: #999;">
            Last checked: <?php echo date('Y-m-d H:i:s'); ?> | 
            Database: <?php echo $dbConnected ? 'Connected' : 'Disconnected'; ?>
        </p>
    </div>
</body>
</html>
