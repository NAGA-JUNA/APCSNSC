<?php
require_once __DIR__ . '/../db.php';

if (admin_logged_in()) {
    redirect_to('admin/dashboard.php');
}

$error = null;

$demoLogins = [
    ['label' => 'Super Admin', 'username' => 'admin', 'password' => 'admin123'],
    ['label' => 'State President', 'username' => 'state_admin', 'password' => 'state123'],
    ['label' => 'District President', 'username' => 'guntur_president', 'password' => 'district123'],
];

$demoCredentialMap = [
    'admin' => ['password' => 'admin123', 'role' => 'super_admin'],
    'state_admin' => ['password' => 'state123', 'role' => 'state_president'],
    'guntur_president' => ['password' => 'district123', 'role' => 'district_president'],
];

$demoRoleScope = [
    'super_admin' => '',
    'state_president' => '',
    'district_president' => 'Guntur',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token. Please refresh and try again.';
    }

    $quickLogin = isset($_POST['quick_login']);
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $admin = null;
    $usedDemoRoleFallback = false;
    if ($error === null) {
        if ($quickLogin) {
            $admin = fetch_one('SELECT * FROM admin_users WHERE username = :username LIMIT 1', [
                ':username' => 'admin',
            ]);

            if (!$admin) {
                $admin = fetch_one('SELECT * FROM admin_users ORDER BY id ASC LIMIT 1');
            }
        } else {
            $admin = fetch_one('SELECT * FROM admin_users WHERE username = :username LIMIT 1', [
                ':username' => $username,
            ]);

            // Demo fallback: allow role-based testing accounts even if exact username rows are not present.
            if (!$admin) {
                $demo = $demoCredentialMap[$username] ?? null;
                if (is_array($demo) && hash_equals((string)$demo['password'], (string)$password)) {
                    $usedDemoRoleFallback = true;

                    try {
                        if ((string)$demo['role'] === 'district_president') {
                            $admin = fetch_one(
                                'SELECT * FROM admin_users WHERE role = :role ORDER BY CASE WHEN district IS NULL OR district = "" THEN 1 ELSE 0 END, id ASC LIMIT 1',
                                [':role' => (string)$demo['role']]
                            );
                        } else {
                            $admin = fetch_one('SELECT * FROM admin_users WHERE role = :role ORDER BY id ASC LIMIT 1', [
                                ':role' => (string)$demo['role'],
                            ]);
                        }
                    } catch (Throwable $e) {
                        $admin = null;
                    }

                    if (!$admin) {
                        $admin = fetch_one('SELECT * FROM admin_users ORDER BY id ASC LIMIT 1');
                    }

                    if ($admin) {
                        $admin['role'] = (string)$demo['role'];
                        $admin['district'] = $demoRoleScope[(string)$demo['role']] ?? '';
                        $admin['username'] = $username;
                    }
                }
            }
        }
    }

    $valid = ($quickLogin || $usedDemoRoleFallback) && $admin !== null;
    if ($admin) {
        if (!$quickLogin && !$usedDemoRoleFallback && password_verify($password, (string)$admin['password'])) {
            $valid = true;
        } elseif (!$quickLogin && !$usedDemoRoleFallback && hash_equals((string)$admin['password'], $password)) {
            // Backward compatibility for plaintext password rows.
            $valid = true;
            execute_query('UPDATE admin_users SET password = :password WHERE id = :id', [
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => $admin['id'],
            ]);
        }
    }

    if ($valid) {
        if (isset($admin['is_active']) && (int)$admin['is_active'] !== 1) {
            $error = 'Your account is inactive. Contact super admin.';
            $valid = false;
        }
    }

    if ($valid) {
        $_SESSION['admin_user_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = strtolower(trim((string)($admin['role'] ?? 'super_admin')));
        $_SESSION['admin_district'] = trim((string)($admin['district'] ?? ''));
        redirect_to('admin/dashboard.php');
    } elseif ($error === null) {
        $error = $quickLogin
            ? 'No admin user found. Please run database seed first.'
            : 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - APCSNSC</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/style.css')); ?>">
</head>
<body>
    <div class="card login-card">
        <h2>APCSNSC Admin Login</h2>
        <p class="muted">Sign in to manage website content and member workflows.</p>
        <p class="muted" style="margin-top: 6px;">Testing access: use Quick Login below or credentials <strong>admin / admin123</strong>.</p>

        <div class="alert" style="background: #eef7ff; color: #16385b; border: 1px solid #c8ddf1; margin-top: 10px;">
            <strong style="display:block; margin-bottom: 8px;">Demo Logins (Testing)</strong>
            <div style="display: grid; gap: 8px;">
                <?php foreach ($demoLogins as $demo): ?>
                    <button
                        type="button"
                        class="btn btn-outline"
                        data-demo-username="<?= esc($demo['username']); ?>"
                        data-demo-password="<?= esc($demo['password']); ?>"
                        style="justify-content: space-between; text-align: left;"
                    >
                        <span><strong><?= esc($demo['label']); ?>:</strong> <?= esc($demo['username']); ?> / <?= esc($demo['password']); ?></span>
                        <span>Use</span>
                    </button>
                <?php endforeach; ?>
            </div>
            <small style="display:block; margin-top: 8px; color: #486a87;">If any demo account does not exist, create it in admin users first.</small>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= esc($error); ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
            <div class="form-group full">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group full">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group full">
                <button class="btn btn-primary" type="submit">Login</button>
            </div>
            <div class="form-group full">
                <button class="btn btn-outline" type="submit" name="quick_login" value="1">Quick Login (Testing)</button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            var usernameInput = document.querySelector('input[name="username"]');
            var passwordInput = document.querySelector('input[name="password"]');
            if (!usernameInput || !passwordInput) {
                return;
            }

            document.querySelectorAll('[data-demo-username]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    usernameInput.value = btn.getAttribute('data-demo-username') || '';
                    passwordInput.value = btn.getAttribute('data-demo-password') || '';
                    usernameInput.focus();
                });
            });
        })();
    </script>
</body>
</html>
