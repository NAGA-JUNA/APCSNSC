<?php
// APCSNSC core bootstrap: session, DB connection, helpers.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'svaobtfy_apcsnsc';
const DB_USER = 'svaobtfy_apcsnsc';
const DB_PASS = 'apcsn@2026';

// Set this to '/apcsn' if deployed in a subfolder.
const BASE_URL = '';

function base_url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');

    if ($base === '') {
        return '/' . $path;
    }

    return $base . '/' . $path;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        $safe = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Database Connection Error</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;margin:0;padding:28px}';
        echo '.box{max-width:820px;margin:0 auto;background:#fff;border:1px solid #d7e3ef;border-radius:12px;padding:20px}';
        echo 'h1{margin-top:0;color:#1e3a5f}.hint{background:#eef7ff;border-left:4px solid #1f7a63;padding:10px 12px;border-radius:8px}';
        echo 'code{background:#f2f5f8;padding:2px 6px;border-radius:6px}</style></head><body><div class="box">';
        echo '<h1>Database Connection Error</h1>';
        echo '<p>Could not connect to MySQL. Please verify that MySQL is running and your credentials in <code>db.php</code> are correct.</p>';
        echo '<div class="hint"><strong>Checklist:</strong><br>';
        echo '1) Start MySQL in XAMPP/WAMP<br>';
        echo '2) Confirm host/port in <code>DB_HOST</code> and <code>DB_PORT</code><br>';
        echo '3) Import <code>sql/schema.sql</code> into database <code>' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '</code></div>';
        echo '<p style="margin-top:14px;color:#475569"><strong>Technical message:</strong> ' . $safe . '</p>';
        echo '</div></body></html>';
        exit;
    }

    return $pdo;
}

function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ?: null;
}

function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function execute_query(string $sql, array $params = []): bool
{
    $stmt = db()->prepare($sql);
    return $stmt->execute($params);
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function clean(string $value): string
{
    return trim(strip_tags($value));
}

function set_flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $message;
}

function redirect_to(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function current_lang(): string
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'te'])) {
        $_SESSION['lang'] = $_GET['lang'];
        return $_GET['lang'];
    }

    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['en', 'te'])) {
        return $_SESSION['lang'];
    }

    return 'en';
}

function get_translations(): array
{
    $lang = current_lang();
    $filePath = __DIR__ . "/translations/{$lang}.php";
    $fallbackPath = __DIR__ . '/translations/en.php';

    if (file_exists($filePath)) {
        return require $filePath;
    }
    
    return require $fallbackPath;
}

function t(string $key, array $translations): string
{
    return htmlspecialchars($translations[$key] ?? $key, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function admin_logged_in(): bool
{
    return isset($_SESSION['admin_user_id']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect_to('admin/login.php');
    }

    $profile = admin_profile();
    if ($profile !== null && isset($profile['is_active']) && (int)$profile['is_active'] !== 1) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect_to('admin/login.php');
    }
}

function admin_profile(): ?array
{
    if (!admin_logged_in()) {
        return null;
    }

    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $adminId = (int)($_SESSION['admin_user_id'] ?? 0);
    if ($adminId <= 0) {
        return null;
    }

    try {
        $row = fetch_one('SELECT * FROM admin_users WHERE id = :id LIMIT 1', [':id' => $adminId]);
        if (!$row) {
            return null;
        }
    } catch (Throwable $e) {
        return null;
    }

    $cached = $row;
    return $cached;
}

function admin_role(): string
{
    $sessionRole = strtolower(trim((string)($_SESSION['admin_role'] ?? '')));
    if ($sessionRole !== '') {
        return $sessionRole;
    }

    $profile = admin_profile();
    $dbRole = strtolower(trim((string)($profile['role'] ?? '')));

    return $dbRole !== '' ? $dbRole : 'super_admin';
}

function admin_district(): string
{
    $sessionDistrict = trim((string)($_SESSION['admin_district'] ?? ''));
    if ($sessionDistrict !== '') {
        return $sessionDistrict;
    }

    $profile = admin_profile();
    return trim((string)($profile['district'] ?? ''));
}

function admin_role_label(): string
{
    $role = admin_role();
    $labels = [
        'super_admin' => 'Super Administrator',
        'state_president' => 'State President',
        'district_president' => 'District President',
        'admin' => 'Administrator',
    ];

    return $labels[$role] ?? 'Administrator';
}

function is_super_admin(): bool
{
    return admin_role() === 'super_admin';
}

function is_state_president(): bool
{
    return admin_role() === 'state_president';
}

function is_district_president(): bool
{
    return admin_role() === 'district_president';
}

function can_approve_payments(): bool
{
    return in_array(admin_role(), ['super_admin', 'state_president', 'district_president'], true);
}

function can_approve_id_cards(): bool
{
    return in_array(admin_role(), ['super_admin', 'state_president', 'district_president'], true);
}

function admin_can_access_district(?string $district): bool
{
    if (is_super_admin() || is_state_president()) {
        return true;
    }

    if (!is_district_president()) {
        return false;
    }

    $target = strtolower(trim((string)$district));
    $scope = strtolower(trim(admin_district()));

    if ($scope === '' || $target === '') {
        return false;
    }

    return $target === $scope;
}

function upload_image(array $file, string $folder = 'uploads'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }

    // Increased limit to 10MB to allow larger images before compression
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        return null;
    }

    $ext = $allowed[$mime];
    $filename = bin2hex(random_bytes(12)) . '.' . $ext;

    $targetDir = __DIR__ . '/' . trim($folder, '/');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $targetPath = $targetDir . '/' . $filename;

    // Auto compress and resize large images using GD Library
    $maxWidth = 1600;
    $sourceImage = null;

    if ($ext === 'jpg') {
        $sourceImage = @imagecreatefromjpeg($file['tmp_name']);
    } elseif ($ext === 'png') {
        $sourceImage = @imagecreatefrompng($file['tmp_name']);
    } elseif ($ext === 'webp') {
        $sourceImage = @imagecreatefromwebp($file['tmp_name']);
    }

    $saved = false;
    if ($sourceImage) {
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        $newWidth = $origWidth;
        $newHeight = $origHeight;

        if ($origWidth > $maxWidth || $origHeight > $maxWidth) {
            $ratio = $origWidth / $origHeight;
            if ($ratio > 1) {
                $newWidth = $maxWidth;
                $newHeight = (int)($maxWidth / $ratio);
            } else {
                $newHeight = $maxWidth;
                $newWidth = (int)($maxWidth * $ratio);
            }
        }

        $targetImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and WEBP
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefilledrectangle($targetImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        if ($ext === 'jpg') {
            $saved = imagejpeg($targetImage, $targetPath, 80); // 80% Quality
        } elseif ($ext === 'png') {
            $saved = imagepng($targetImage, $targetPath, 8); // Compression 0-9
        } elseif ($ext === 'webp') {
            $saved = imagewebp($targetImage, $targetPath, 80); // 80% Quality
        }

        imagedestroy($sourceImage);
        imagedestroy($targetImage);
    }

    // Fallback if GD fails to process
    if (!$saved && !move_uploaded_file($file['tmp_name'], $targetPath)) {
        return null;
    }

    return trim($folder, '/') . '/' . $filename;
}

function upload_multiple_images(array $files, string $folder = 'uploads'): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        $single = upload_image($files, $folder);
        return $single !== null ? [$single] : [];
    }

    $uploaded = [];
    $count = count($files['name']);

    for ($index = 0; $index < $count; $index++) {
        $file = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];

        $path = upload_image($file, $folder);
        if ($path !== null) {
            $uploaded[] = $path;
        }
    }

    return $uploaded;
}

function decode_update_images(?string $json, ?string $fallbackImage = null): array
{
    $images = [];

    if (is_string($json) && trim($json) !== '') {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $path = trim((string)$item);
                if ($path !== '') {
                    $images[] = $path;
                }
            }
        }
    }

    if (!$images && is_string($fallbackImage) && trim($fallbackImage) !== '') {
        $images[] = trim($fallbackImage);
    }

    return array_values(array_unique($images));
}

function encode_update_images(array $images): ?string
{
    $clean = [];
    foreach ($images as $image) {
        $path = trim((string)$image);
        if ($path !== '') {
            $clean[] = $path;
        }
    }

    $clean = array_values(array_unique($clean));

    if (!$clean) {
        return null;
    }

    return json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function get_update_cover_image(array $row, ?string $fallback = null): ?string
{
    $images = decode_update_images($row['images_json'] ?? null, $row['image'] ?? null);
    if ($images) {
        return $images[0];
    }

    if ($fallback !== null && trim($fallback) !== '') {
        return trim($fallback);
    }

    return null;
}

function get_update_images(array $row): array
{
    return decode_update_images($row['images_json'] ?? null, $row['image'] ?? null);
}

function get_update_url(array $row): string
{
    return base_url('pages/update.php?id=' . (int)($row['id'] ?? 0));
}

function resolve_image_src(?string $path, string $fallback = ''): string
{
    $path = is_string($path) ? trim($path) : '';

    if ($path === '') {
        return $fallback;
    }

    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }

    return base_url($path);
}

function is_public_update(array $row): bool
{
    $status = strtolower((string)($row['status'] ?? 'published'));
    $publishAt = (string)($row['publish_at'] ?? '');

    if ($status === 'draft') {
        return false;
    }

    if ($status === 'scheduled' && $publishAt !== '') {
        $publishTimestamp = strtotime($publishAt);
        if ($publishTimestamp !== false && $publishTimestamp > time()) {
            return false;
        }
    }

    return true;
}

function district_code(string $district): string
{
    $letters = preg_replace('/[^A-Za-z]/', '', strtoupper($district));
    $letters = $letters === null ? '' : $letters;
    return str_pad(substr($letters, 0, 3), 3, 'X');
}

function generate_member_id(string $district): string
{
    $code = district_code($district);
    $prefix = 'APCSNSC-' . $code . '-';

    $row = fetch_one('SELECT member_id FROM members WHERE member_id LIKE :prefix ORDER BY id DESC LIMIT 1', [
        ':prefix' => $prefix . '%',
    ]);

    $next = 1;
    if ($row && isset($row['member_id'])) {
        $parts = explode('-', $row['member_id']);
        $serial = (int)($parts[2] ?? 0);
        $next = $serial + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

// =====================================================
// PAYMENT & MEMBERSHIP FUNCTIONS
// =====================================================

function generate_transaction_id(): string
{
    return 'TXN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function get_master_membership_settings(): ?array
{
    try {
        $settings = fetch_one('SELECT * FROM membership_settings WHERE status = "active" ORDER BY updated_at DESC, id DESC LIMIT 1');

        if ($settings) {
            return $settings;
        }
    } catch (PDOException $e) {
        // Table doesn't exist yet - fall through to legacy plan
    }

    // Backward-compatible fallback from legacy membership_plans.
    try {
        $legacyPlan = fetch_one('SELECT id, plan_name, amount, validity_months, plan_description FROM membership_plans WHERE is_active = 1 ORDER BY amount ASC, id ASC LIMIT 1');
        if (!$legacyPlan) {
            return null;
        }

        return [
            'id' => 0,
            'source_plan_id' => (int)$legacyPlan['id'],
            'plan_name' => (string)$legacyPlan['plan_name'],
            'plan_price' => (float)$legacyPlan['amount'],
            'renewal_price' => (float)$legacyPlan['amount'],
            'validity_months' => (int)$legacyPlan['validity_months'],
            'description' => (string)($legacyPlan['plan_description'] ?? 'Union membership plan'),
            'late_fee' => 0.00,
            'status' => 'active',
            'auto_reminder_days' => 30,
            'allow_id_card_generation' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    } catch (PDOException $e) {
        return null;
    }
}

function get_membership_validity_label(int $validityMonths): string
{
    if ($validityMonths >= 1200) {
        return 'Lifetime';
    }

    if ($validityMonths % 12 === 0) {
        $years = (int)($validityMonths / 12);
        return $years . ' Year' . ($years > 1 ? 's' : '');
    }

    return $validityMonths . ' Month' . ($validityMonths > 1 ? 's' : '');
}

function calculate_membership_dates(array $member, int $validityMonths): array
{
    $today = date('Y-m-d');
    $currentExpiry = (string)($member['membership_expiry_date'] ?? '');
    $membershipStatus = strtolower((string)($member['membership_status'] ?? ''));
    $paymentStatus = strtolower((string)($member['payment_status'] ?? ''));

    $isActivePaid = $membershipStatus === 'active' && $paymentStatus === 'paid' && $currentExpiry !== '' && strtotime($currentExpiry) >= strtotime($today);
    $anchorDate = $isActivePaid ? $currentExpiry : $today;

    if ($validityMonths >= 1200) {
        $expiryDate = '2100-12-31';
    } else {
        $expiryDate = date('Y-m-d', strtotime($anchorDate . ' +' . $validityMonths . ' months'));
    }

    return [
        'start_date' => $today,
        'anchor_date' => $anchorDate,
        'expiry_date' => $expiryDate,
        'is_renewal' => $isActivePaid,
        'previous_expiry_date' => $currentExpiry !== '' ? $currentExpiry : null,
    ];
}

function get_receipt_no(array $transaction): string
{
    $datePart = date('Ymd', strtotime((string)($transaction['transaction_date'] ?? 'now')));
    $idPart = str_pad((string)((int)($transaction['id'] ?? 0)), 6, '0', STR_PAD_LEFT);
    return 'RCT-' . $datePart . '-' . $idPart;
}

function can_generate_id_card(?array $member): bool
{
    if (!$member) {
        return false;
    }

    $paymentStatus = strtolower((string)($member['payment_status'] ?? ''));
    $membershipStatus = strtolower((string)($member['membership_status'] ?? ''));

    if ($paymentStatus !== 'paid' || $membershipStatus !== 'active') {
        return false;
    }

    $expiryDate = $member['membership_expiry_date'] ?? null;
    if ($expiryDate === null || $expiryDate === '') {
        return false;
    }

    $masterSettings = get_master_membership_settings();
    if ($masterSettings && isset($masterSettings['allow_id_card_generation']) && (int)$masterSettings['allow_id_card_generation'] !== 1) {
        return false;
    }

    return strtotime((string)$expiryDate) >= time();
}

function get_payment_status_badge(string $status): string
{
    $status = strtolower($status);
    $badge = match ($status) {
        'paid' => '<span class="badge badge-success">Paid</span>',
        'pending' => '<span class="badge badge-warning">Pending</span>',
        'failed' => '<span class="badge badge-danger">Failed</span>',
        'unpaid' => '<span class="badge badge-secondary">Unpaid</span>',
        default => '<span class="badge badge-secondary">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>',
    };
    return $badge;
}

function get_membership_status_badge(string $status): string
{
    $status = strtolower($status);
    $badge = match ($status) {
        'active' => '<span class="badge badge-success">Active</span>',
        'unpaid' => '<span class="badge badge-danger">Unpaid</span>',
        'renewal_due' => '<span class="badge badge-warning">Renewal Due</span>',
        'expired' => '<span class="badge badge-secondary">Expired</span>',
        'suspended' => '<span class="badge badge-danger">Suspended</span>',
        default => '<span class="badge badge-secondary">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>',
    };
    return $badge;
}

function update_member_payment(int $memberId, string $planName, float $amount, string $paymentMode = 'Cash', ?string $remarks = null): bool
{
    $planRow = fetch_one('SELECT id FROM membership_plans WHERE plan_name = :plan_name LIMIT 1', [
        ':plan_name' => $planName,
    ]);

    if (!$planRow) {
        return false;
    }

    $planId = (int)$planRow['id'];

    // Get plan validity
    $plan = fetch_one('SELECT validity_months FROM membership_plans WHERE id = :id LIMIT 1', [':id' => $planId]);
    if (!$plan) {
        return false;
    }

    $validityMonths = (int)$plan['validity_months'];
    $startDate = date('Y-m-d');
    $expiryDate = $validityMonths >= 1200 ? '2100-12-31' : date('Y-m-d', strtotime("+$validityMonths months"));

    $transactionId = generate_transaction_id();
    $now = date('Y-m-d H:i:s');

    try {
        $member = fetch_one('SELECT renewal_count FROM members WHERE id = :id LIMIT 1', [':id' => $memberId]);
        if (!$member) {
            return false;
        }

        $renewalCount = ((int)($member['renewal_count'] ?? 0)) + 1;

        // Update member payment
        execute_query(
            'UPDATE members 
             SET membership_status = :membership_status,
                 payment_status = :payment_status,
                 plan_name = :plan_name,
                 plan_amount = :plan_amount,
                 membership_start_date = :start_date,
                 membership_expiry_date = :expiry_date,
                 renewal_count = :renewal_count,
                 transaction_id = :transaction_id,
                 last_payment_date = :last_payment_date,
                 payment_mode = :payment_mode,
                 payment_remarks = :remarks
             WHERE id = :id',
            [
                ':id' => $memberId,
                ':membership_status' => 'active',
                ':payment_status' => 'paid',
                ':plan_name' => $planName,
                ':plan_amount' => $amount,
                ':start_date' => $startDate,
                ':expiry_date' => $expiryDate,
                ':renewal_count' => $renewalCount,
                ':transaction_id' => $transactionId,
                ':last_payment_date' => $now,
                ':payment_mode' => $paymentMode,
                ':remarks' => $remarks,
            ]
        );

        // Log transaction
        execute_query(
            'INSERT INTO payment_transactions 
             (transaction_id, member_id, plan_id, amount, payment_status, payment_mode, approved_date, approved_by, remarks, is_renewal)
             VALUES (:txn_id, :member_id, :plan_id, :amount, :status, :mode, :approved_date, :approved_by, :remarks, :is_renewal)',
            [
                ':txn_id' => $transactionId,
                ':member_id' => $memberId,
                ':plan_id' => $planId,
                ':amount' => $amount,
                ':status' => 'approved',
                ':mode' => $paymentMode,
                ':approved_date' => $now,
                ':approved_by' => (int)($_SESSION['admin_user_id'] ?? 0),
                ':remarks' => $remarks,
                ':is_renewal' => 0,
            ]
        );

        return true;
    } catch (Exception $e) {
        error_log('Payment update error: ' . $e->getMessage());
        return false;
    }
}

function is_membership_expiring_soon(string $expiryDate, int $days = 30): bool
{
    $expiry = strtotime($expiryDate);
    $daysFromNow = (int)(($expiry - time()) / 86400);
    return $daysFromNow <= $days && $daysFromNow > 0;
}

function is_membership_expired(string $expiryDate): bool
{
    return strtotime($expiryDate) < time();
}
