<?php
require_once __DIR__ . '/../db.php';
require_admin();

$current = get_master_membership_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle toggle for union CTA separately
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_union_cta') {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            set_flash('error', 'Invalid request token.');
            redirect_to('admin/membership_settings.php');
        }

        $val = (isset($_POST['show_union_cta']) && (int)$_POST['show_union_cta'] === 1) ? '1' : '0';

        try {
            $exists = fetch_one("SELECT 1 FROM homepage_showcase_settings WHERE setting_key = 'show_union_cta' LIMIT 1");
            if ($exists) {
                execute_query("UPDATE homepage_showcase_settings SET setting_value = :v WHERE setting_key = 'show_union_cta'", [':v' => $val]);
            } else {
                execute_query("INSERT INTO homepage_showcase_settings (setting_key, setting_value) VALUES ('show_union_cta', :v)", [':v' => $val]);
            }
            set_flash('success', 'Homepage CTA visibility updated.');
        } catch (Throwable $e) {
            set_flash('error', 'Database error: ' . $e->getMessage());
        }

        redirect_to('admin/membership_settings.php');
    }
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid request token.');
        redirect_to('admin/membership_settings.php');
    }

    $planName = clean((string)($_POST['plan_name'] ?? ''));
    $planPrice = (float)($_POST['plan_price'] ?? 0);
    $renewalPrice = (float)($_POST['renewal_price'] ?? 0);
    $validityMonths = (int)($_POST['validity_months'] ?? 12);
    $lateFee = (float)($_POST['late_fee'] ?? 0);
    $description = trim((string)($_POST['description'] ?? ''));
    $autoReminderDays = (int)($_POST['auto_reminder_days'] ?? 30);
    $allowIdCard = (int)($_POST['allow_id_card_generation'] ?? 1) === 1 ? 1 : 0;
    $status = strtolower((string)($_POST['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active';

    if ($planName === '' || $planPrice < 0 || $renewalPrice < 0 || $validityMonths <= 0 || $autoReminderDays < 1 || $autoReminderDays > 120) {
        set_flash('error', 'Please enter valid plan values.');
        redirect_to('admin/membership_settings.php');
    }

    try {
        // Check if table exists first
        $tableCheck = fetch_one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_settings'");
        
        if (!$tableCheck) {
            set_flash('error', 'Database error: membership_settings table not found. Please run the database migration first (database/migration_master_plan.sql).');
            redirect_to('admin/membership_settings.php');
            exit;
        }

        // Build queries only with columns that exist in live schema.
        $columnRows = fetch_all("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membership_settings'");
        $availableColumns = [];
        foreach ($columnRows as $row) {
            $name = strtolower((string)($row['COLUMN_NAME'] ?? ''));
            if ($name !== '') {
                $availableColumns[$name] = true;
            }
        }

        $hasLateFee = isset($availableColumns['late_fee']);
        $hasAutoReminderDays = isset($availableColumns['auto_reminder_days']);
        $hasAllowIdCardGeneration = isset($availableColumns['allow_id_card_generation']);
        $hasUpdatedAt = isset($availableColumns['updated_at']);

        $existing = fetch_one('SELECT id FROM membership_settings ORDER BY id ASC LIMIT 1');

        if ($existing) {
            $setParts = [
                'plan_name = :plan_name',
                'plan_price = :plan_price',
                'renewal_price = :renewal_price',
                'validity_months = :validity_months',
                'description = :description',
                'status = :status',
            ];

            $params = [
                ':id' => (int)$existing['id'],
                ':plan_name' => $planName,
                ':plan_price' => $planPrice,
                ':renewal_price' => $renewalPrice,
                ':validity_months' => $validityMonths,
                ':description' => $description,
                ':status' => $status,
            ];

            if ($hasLateFee) {
                $setParts[] = 'late_fee = :late_fee';
                $params[':late_fee'] = $lateFee;
            }

            if ($hasAutoReminderDays) {
                $setParts[] = 'auto_reminder_days = :auto_reminder_days';
                $params[':auto_reminder_days'] = $autoReminderDays;
            }

            if ($hasAllowIdCardGeneration) {
                $setParts[] = 'allow_id_card_generation = :allow_id_card_generation';
                $params[':allow_id_card_generation'] = $allowIdCard;
            }

            if ($hasUpdatedAt) {
                $setParts[] = 'updated_at = NOW()';
            }

            execute_query(
                'UPDATE membership_settings SET ' . implode(', ', $setParts) . ' WHERE id = :id',
                $params
            );
        } else {
            $insertColumns = [
                'plan_name',
                'plan_price',
                'renewal_price',
                'validity_months',
                'description',
                'status',
            ];

            $insertValues = [
                ':plan_name',
                ':plan_price',
                ':renewal_price',
                ':validity_months',
                ':description',
                ':status',
            ];

            $params = [
                ':plan_name' => $planName,
                ':plan_price' => $planPrice,
                ':renewal_price' => $renewalPrice,
                ':validity_months' => $validityMonths,
                ':description' => $description,
                ':status' => $status,
            ];

            if ($hasLateFee) {
                $insertColumns[] = 'late_fee';
                $insertValues[] = ':late_fee';
                $params[':late_fee'] = $lateFee;
            }

            if ($hasAutoReminderDays) {
                $insertColumns[] = 'auto_reminder_days';
                $insertValues[] = ':auto_reminder_days';
                $params[':auto_reminder_days'] = $autoReminderDays;
            }

            if ($hasAllowIdCardGeneration) {
                $insertColumns[] = 'allow_id_card_generation';
                $insertValues[] = ':allow_id_card_generation';
                $params[':allow_id_card_generation'] = $allowIdCard;
            }

            execute_query(
                'INSERT INTO membership_settings (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')',
                $params
            );
        }

        set_flash('success', 'Master membership settings updated successfully.');
        redirect_to('admin/membership_settings.php');
    } catch (PDOException $e) {
        set_flash('error', 'Database error: ' . $e->getMessage());
        redirect_to('admin/membership_settings.php');
    }
}

$plan = get_master_membership_settings();
$pageTitle = 'Membership Settings';
$activeMenu = 'payments';
require_once __DIR__ . '/_top.php';
?>

<link rel="stylesheet" href="<?= esc(base_url('assets/css/payment-system.css')); ?>">

<section class="admin-card" style="margin-bottom: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="mb-1">Master Membership Plan Settings</h3>
            <p class="mb-0 text-secondary">Control the single membership plan used for new payments and renewals.</p>
        </div>
        <div class="quick-actions">
            <a class="quick-btn" href="<?= esc(base_url('admin/payments.php')); ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
</section>


<?php $ok = get_flash('success'); if ($ok): ?>
<section class="admin-card" style="margin-bottom: 12px; border-left: 4px solid #10a860;"><p class="mb-0" style="color:#0a6924;"><?= esc($ok); ?></p></section>
<?php endif; ?>
<?php $err = get_flash('error'); if ($err): ?>
<section class="admin-card" style="margin-bottom: 12px; border-left: 4px solid #dc3545;"><p class="mb-0" style="color:#842029;"><?= esc($err); ?></p></section>
<?php endif; ?>

<section class="admin-card" style="max-width: 860px;">
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

        <div class="form-group">
            <label for="plan_name">Plan Name</label>
            <input type="text" id="plan_name" name="plan_name" required value="<?= esc((string)($plan['plan_name'] ?? 'APCSNSC Membership Plan')); ?>">
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <option value="active" <?= (($plan['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?= (($plan['status'] ?? 'active') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <div class="form-group">
            <label for="plan_price">Price (₹)</label>
            <input type="number" id="plan_price" name="plan_price" step="0.01" min="0" required value="<?= esc(number_format((float)($plan['plan_price'] ?? 100), 2, '.', '')); ?>">
        </div>

        <div class="form-group">
            <label for="renewal_price">Renewal Price (₹)</label>
            <input type="number" id="renewal_price" name="renewal_price" step="0.01" min="0" required value="<?= esc(number_format((float)($plan['renewal_price'] ?? 100), 2, '.', '')); ?>">
        </div>

        <div class="form-group">
            <label for="validity_months">Validity Months</label>
            <input type="number" id="validity_months" name="validity_months" min="1" required value="<?= esc((string)($plan['validity_months'] ?? 12)); ?>">
        </div>

        <div class="form-group">
            <label for="late_fee">Late Fee (₹)</label>
            <input type="number" id="late_fee" name="late_fee" step="0.01" min="0" value="<?= esc(number_format((float)($plan['late_fee'] ?? 0), 2, '.', '')); ?>">
        </div>

        <div class="form-group">
            <label for="auto_reminder_days">Auto Renewal Reminder Days</label>
            <input type="number" id="auto_reminder_days" name="auto_reminder_days" min="1" max="120" required value="<?= esc((string)($plan['auto_reminder_days'] ?? 30)); ?>">
        </div>

        <div class="form-group">
            <label for="allow_id_card_generation">Allow ID Card Generation</label>
            <select id="allow_id_card_generation" name="allow_id_card_generation" required>
                <option value="1" <?= (int)($plan['allow_id_card_generation'] ?? 1) === 1 ? 'selected' : ''; ?>>Yes</option>
                <option value="0" <?= (int)($plan['allow_id_card_generation'] ?? 1) === 0 ? 'selected' : ''; ?>>No</option>
            </select>
        </div>

        <div class="form-group full">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Plan description for admins and members..."><?= esc((string)($plan['description'] ?? 'Valid membership with ID card and union benefits')); ?></textarea>
        </div>

        <div class="form-group full">
            <button type="submit" class="btn btn-success">
                <i class="fa-solid fa-floppy-disk me-2"></i>Save Plan Settings
            </button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/_bottom.php'; ?>
