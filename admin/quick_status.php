<?php
/**
 * QUICK STATUS CHECK - Shows if HTTP 500 fix is working
 * Visit: https://apcsn.jnvweb.in/admin/quick_status.php
 */

require_once __DIR__ . '/../db.php';

// Get status
$tableExists = false;
$planExists = false;
$errorMsg = '';

try {
    $tableCheck = fetch_one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_settings' LIMIT 1");
    $tableExists = $tableCheck ? true : false;
    
    if ($tableExists) {
        $plan = get_master_membership_settings();
        $planExists = $plan ? true : false;
    }
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
}

$status = 'NOT_READY';
$message = 'Migration not imported yet';
$color = '#ff6b6b';

if ($tableExists && $planExists) {
    $status = 'READY';
    $message = 'HTTP 500 fix is working! ✅';
    $color = '#51cf66';
} elseif ($tableExists && !$planExists) {
    $status = 'PARTIAL';
    $message = 'Table exists but no plan - please reimport migration';
    $color = '#ffa94d';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>HTTP 500 Fix Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f0f0f0; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #333; }
        .status-box { background: <?php echo $color; ?>; color: white; padding: 20px; border-radius: 8px; margin: 20px 0; font-size: 18px; font-weight: bold; }
        .details { text-align: left; background: #f5f5f5; padding: 15px; border-radius: 4px; margin: 20px 0; font-family: monospace; font-size: 14px; }
        .next-step { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; border-radius: 4px; margin: 20px 0; text-align: left; }
        .next-step strong { color: #1565c0; }
        a { color: #2196f3; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 HTTP 500 Fix Status</h1>
        
        <div class="status-box">
            <?php echo htmlspecialchars($message); ?>
        </div>

        <div class="details">
            <strong>Migration Status:</strong><br>
            Table exists: <?php echo $tableExists ? '✅ YES' : '❌ NO'; ?><br>
            Plan exists: <?php echo $planExists ? '✅ YES' : '❌ NO'; ?><br>
            <?php if ($errorMsg): ?>
            Error: <?php echo htmlspecialchars($errorMsg); ?><br>
            <?php endif; ?>
        </div>

        <?php if ($status === 'READY'): ?>
            <div class="next-step">
                <strong>✅ SUCCESS!</strong><br>
                Go test it: <a href="../membership_settings.php">admin/membership_settings.php</a><br>
                Click "Save Plan Settings" - should work without HTTP 500 error!
            </div>
        <?php else: ?>
            <div class="next-step">
                <strong>⚠️ ACTION NEEDED</strong><br>
                Import the migration file:<br>
                <code>database/migration_master_plan.sql</code><br><br>
                <a href="../../DEPLOYMENT_INSTRUCTIONS.txt" target="_blank">View Import Instructions</a><br><br>
                Then refresh this page to verify.
            </div>
        <?php endif; ?>

        <p style="font-size: 12px; color: #666;">
            Last checked: <?php echo date('Y-m-d H:i:s'); ?>
        </p>
    </div>
</body>
</html>
