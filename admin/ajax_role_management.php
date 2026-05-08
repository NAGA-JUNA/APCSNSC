<?php
require_once __DIR__ . '/../db.php';
require_admin();

header('Content-Type: application/json; charset=UTF-8');

if (!is_super_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

$permissionKeys = ['dashboard', 'members', 'complaints', 'media', 'reports', 'id_cards', 'settings', 'updates'];
$memberRoleOptions = ['member', 'district_coordinator', 'state_coordinator', 'union_leader', 'president', 'secretary', 'treasurer', 'advisor'];

$jsonOut = static function (bool $ok, array $payload = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $payload), JSON_UNESCAPED_UNICODE);
    exit;
};

$requestIp = static function (): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];
    foreach ($headers as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        if (strpos($value, ',') !== false) {
            $value = trim((string)explode(',', $value)[0]);
        }
        if ($value !== '') {
            return $value;
        }
    }
    return '0.0.0.0';
};

$sanitizeRole = static function (string $role): string {
    $allowed = ['super_admin', 'state_president', 'district_president', 'media_admin', 'complaint_admin'];
    $role = strtolower(trim($role));
    return in_array($role, $allowed, true) ? $role : 'super_admin';
};

$defaultPermissionsByRole = static function (string $role) use ($permissionKeys): array {
    $role = strtolower(trim($role));
    if ($role === 'super_admin') {
        return $permissionKeys;
    }
    if ($role === 'state_president' || $role === 'district_president') {
        return ['dashboard', 'members', 'complaints', 'reports', 'id_cards', 'updates'];
    }
    if ($role === 'media_admin') {
        return ['dashboard', 'media', 'updates'];
    }
    if ($role === 'complaint_admin') {
        return ['dashboard', 'complaints', 'reports'];
    }
    return ['dashboard'];
};

$getColumns = static function (): array {
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $rows = fetch_all('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "admin_users"');
    $set = [];
    foreach ($rows as $row) {
        $set[(string)$row['COLUMN_NAME']] = true;
    }
    $cached = $set;
    return $set;
};

$hasColumn = static function (string $columnName) use ($getColumns): bool {
    $set = $getColumns();
    return isset($set[$columnName]);
};

$ensureSchema = static function () use ($hasColumn): void {
    $alterMap = [
        'full_name' => 'ALTER TABLE admin_users ADD COLUMN full_name VARCHAR(150) DEFAULT NULL AFTER username',
        'email' => 'ALTER TABLE admin_users ADD COLUMN email VARCHAR(180) DEFAULT NULL AFTER full_name',
        'role' => 'ALTER TABLE admin_users ADD COLUMN role VARCHAR(40) DEFAULT "super_admin" AFTER password',
        'district' => 'ALTER TABLE admin_users ADD COLUMN district VARCHAR(150) DEFAULT NULL AFTER role',
        'state' => 'ALTER TABLE admin_users ADD COLUMN state VARCHAR(120) DEFAULT "Andhra Pradesh" AFTER district',
        'is_active' => 'ALTER TABLE admin_users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER state',
        'can_approve_payments' => 'ALTER TABLE admin_users ADD COLUMN can_approve_payments TINYINT(1) DEFAULT 1 AFTER is_active',
        'can_approve_id_cards' => 'ALTER TABLE admin_users ADD COLUMN can_approve_id_cards TINYINT(1) DEFAULT 1 AFTER can_approve_payments',
        'mobile' => 'ALTER TABLE admin_users ADD COLUMN mobile VARCHAR(30) DEFAULT NULL',
        'last_login' => 'ALTER TABLE admin_users ADD COLUMN last_login DATETIME DEFAULT NULL',
    ];

    foreach ($alterMap as $columnName => $sql) {
        if ($hasColumn($columnName)) {
            continue;
        }

        try {
            execute_query($sql);
        } catch (Throwable $e) {
            // Ignore unsupported alter in limited environments.
        }
    }

    execute_query(
        'CREATE TABLE IF NOT EXISTS admin_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_key VARCHAR(50) NOT NULL UNIQUE,
            role_name VARCHAR(120) NOT NULL,
            scope_level ENUM("global","state","district","module") NOT NULL DEFAULT "global",
            is_system TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    execute_query(
        'CREATE TABLE IF NOT EXISTS admin_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            permission_key VARCHAR(80) NOT NULL,
            is_allowed TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_admin_permission (admin_id, permission_key),
            INDEX idx_admin_permission_admin (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    execute_query(
        'CREATE TABLE IF NOT EXISTS admin_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            actor_admin_id INT NOT NULL,
            target_admin_id INT DEFAULT NULL,
            action VARCHAR(120) NOT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_logs_target (target_admin_id),
            INDEX idx_admin_logs_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $seedRoles = [
        ['super_admin', 'Super Admin', 'global'],
        ['state_president', 'State President', 'state'],
        ['district_president', 'District President', 'district'],
        ['media_admin', 'Media Admin', 'module'],
        ['complaint_admin', 'Complaint Admin', 'module'],
    ];
    foreach ($seedRoles as $seed) {
        execute_query(
            'INSERT INTO admin_roles (role_key, role_name, scope_level, is_system)
             VALUES (:key, :name, :scope, 1)
             ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), scope_level = VALUES(scope_level)',
            [
                ':key' => $seed[0],
                ':name' => $seed[1],
                ':scope' => $seed[2],
            ]
        );
    }
};

$logAction = static function (string $action, ?int $targetAdminId = null, ?array $details = null) use ($requestIp): void {
    $actorId = (int)($_SESSION['admin_user_id'] ?? 0);
    if ($actorId <= 0) {
        return;
    }

    execute_query(
        'INSERT INTO admin_logs (actor_admin_id, target_admin_id, action, details, ip_address)
         VALUES (:actor, :target, :action, :details, :ip)',
        [
            ':actor' => $actorId,
            ':target' => $targetAdminId,
            ':action' => $action,
            ':details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            ':ip' => $requestIp(),
        ]
    );
};

$normalizePermissions = static function (array $permissionList) use ($permissionKeys): array {
    $result = [];
    foreach ($permissionList as $permission) {
        $key = strtolower(trim((string)$permission));
        if (in_array($key, $permissionKeys, true)) {
            $result[$key] = true;
        }
    }
    return array_keys($result);
};

$savePermissions = static function (int $adminId, array $permissions): void {
    execute_query('DELETE FROM admin_permissions WHERE admin_id = :id', [':id' => $adminId]);
    foreach ($permissions as $perm) {
        execute_query(
            'INSERT INTO admin_permissions (admin_id, permission_key, is_allowed)
             VALUES (:admin_id, :permission_key, 1)',
            [
                ':admin_id' => $adminId,
                ':permission_key' => $perm,
            ]
        );
    }
};

$fetchPermissionsMap = static function (array $adminIds): array {
    if ($adminIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($adminIds as $idx => $id) {
        $key = ':id' . $idx;
        $placeholders[] = $key;
        $params[$key] = (int)$id;
    }

    $rows = fetch_all(
        'SELECT admin_id, permission_key FROM admin_permissions
         WHERE is_allowed = 1 AND admin_id IN (' . implode(', ', $placeholders) . ')',
        $params
    );

    $map = [];
    foreach ($rows as $row) {
        $adminId = (int)($row['admin_id'] ?? 0);
        $perm = strtolower((string)($row['permission_key'] ?? ''));
        if ($adminId <= 0 || $perm === '') {
            continue;
        }
        if (!isset($map[$adminId])) {
            $map[$adminId] = [];
        }
        $map[$adminId][] = $perm;
    }
    return $map;
};

$ensureSchema();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST' && !verify_csrf($_POST['csrf_token'] ?? null)) {
    $jsonOut(false, ['message' => 'Invalid request token.'], 419);
}

$action = strtolower(trim((string)($_REQUEST['action'] ?? 'list')));

try {
    if ($action === 'list') {
        $search = trim((string)($_GET['search'] ?? ''));
        $roleFilter = strtolower(trim((string)($_GET['role'] ?? '')));
        $stateFilter = trim((string)($_GET['state'] ?? ''));
        $districtFilter = trim((string)($_GET['district'] ?? ''));
        $statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));

        $select = [
            'id',
            'username',
            ($hasColumn('full_name') ? 'full_name' : 'username AS full_name'),
            ($hasColumn('email') ? 'email' : 'NULL AS email'),
            ($hasColumn('mobile') ? 'mobile' : 'NULL AS mobile'),
            ($hasColumn('role') ? 'role' : '"super_admin" AS role'),
            ($hasColumn('state') ? 'state' : '"Andhra Pradesh" AS state'),
            ($hasColumn('district') ? 'district' : '"" AS district'),
            ($hasColumn('is_active') ? 'is_active' : '1 AS is_active'),
            ($hasColumn('last_login') ? 'last_login' : 'NULL AS last_login'),
        ];

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '((full_name LIKE :search) OR (username LIKE :search) OR (email LIKE :search))';
            $params[':search'] = '%' . $search . '%';
        }
        if ($roleFilter !== '' && $hasColumn('role')) {
            $where[] = 'role = :role';
            $params[':role'] = $roleFilter;
        }
        if ($stateFilter !== '' && $hasColumn('state')) {
            $where[] = 'state LIKE :state';
            $params[':state'] = '%' . $stateFilter . '%';
        }
        if ($districtFilter !== '' && $hasColumn('district')) {
            $where[] = 'district LIKE :district';
            $params[':district'] = '%' . $districtFilter . '%';
        }
        if ($statusFilter !== '' && $hasColumn('is_active')) {
            $where[] = $statusFilter === 'active' ? 'is_active = 1' : 'is_active = 0';
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM admin_users';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC';

        $rows = fetch_all($sql, $params);
        $adminIds = [];
        foreach ($rows as $row) {
            $adminIds[] = (int)$row['id'];
        }

        $permMap = $fetchPermissionsMap($adminIds);
        $admins = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $role = strtolower((string)($row['role'] ?? 'super_admin'));
            $permissions = $permMap[$id] ?? $defaultPermissionsByRole($role);
            $admins[] = [
                'id' => $id,
                'full_name' => (string)($row['full_name'] ?? $row['username'] ?? ''),
                'username' => (string)($row['username'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'mobile' => (string)($row['mobile'] ?? ''),
                'role' => $role,
                'state' => (string)($row['state'] ?? 'Andhra Pradesh'),
                'district' => (string)($row['district'] ?? ''),
                'status' => ((int)($row['is_active'] ?? 1) === 1 ? 'active' : 'disabled'),
                'last_login' => (string)($row['last_login'] ?? ''),
                'permissions' => $permissions,
            ];
        }

        $summarySql = 'SELECT
            COUNT(*) AS total_admins,
            SUM(CASE WHEN role = "super_admin" THEN 1 ELSE 0 END) AS super_admins,
            SUM(CASE WHEN role = "state_president" THEN 1 ELSE 0 END) AS state_presidents,
            SUM(CASE WHEN role = "district_president" THEN 1 ELSE 0 END) AS district_presidents,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_admins
            FROM admin_users';
        if (!$hasColumn('role') || !$hasColumn('is_active')) {
            $summary = [
                'total_admins' => count($admins),
                'super_admins' => count($admins),
                'state_presidents' => 0,
                'district_presidents' => 0,
                'active_admins' => count($admins),
            ];
        } else {
            $summary = fetch_one($summarySql) ?: [];
        }

        $jsonOut(true, [
            'admins' => $admins,
            'summary' => [
                'total_admins' => (int)($summary['total_admins'] ?? 0),
                'super_admins' => (int)($summary['super_admins'] ?? 0),
                'state_presidents' => (int)($summary['state_presidents'] ?? 0),
                'district_presidents' => (int)($summary['district_presidents'] ?? 0),
                'active_admins' => (int)($summary['active_admins'] ?? 0),
            ],
        ]);
    }

    if ($action === 'member_list') {
        $search = trim((string)($_GET['search'] ?? ''));
        $districtFilter = trim((string)($_GET['district'] ?? ''));
        $roleFilter = trim((string)($_GET['role'] ?? ''));
        $statusFilter = trim((string)($_GET['status'] ?? ''));

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(name LIKE :search OR member_id LIKE :search OR phone LIKE :search OR district LIKE :search OR role LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($districtFilter !== '') {
            $where[] = 'district = :district';
            $params[':district'] = $districtFilter;
        }
        if ($roleFilter !== '') {
            $where[] = 'role = :role';
            $params[':role'] = $roleFilter;
        }
        if ($statusFilter !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $statusFilter;
        }

        $orderColumn = 'created_at';
        $memberColumns = fetch_all('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "members"');
        $memberColumnSet = [];
        foreach ($memberColumns as $column) {
            $memberColumnSet[(string)$column['COLUMN_NAME']] = true;
        }
        if (!isset($memberColumnSet[$orderColumn])) {
            $orderColumn = 'id';
        }

        $rows = fetch_all(
            'SELECT * FROM members' . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '') . '
             ORDER BY ' . $orderColumn . ' DESC LIMIT 200',
            $params
        );

        $members = [];
        foreach ($rows as $row) {
            $memberRole = trim((string)($row['role'] ?? ''));
            if ($memberRole === '') {
                $memberRole = 'member';
            }
            $members[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['full_name'] ?? $row['name'] ?? ''),
                'member_id' => (string)($row['member_id'] ?? ''),
                'district' => (string)($row['district'] ?? ''),
                'designation' => (string)($row['designation'] ?? ($row['role'] ?? '')),
                'role' => $memberRole,
                'phone' => (string)($row['phone'] ?? ($row['mobile'] ?? '')),
                'status' => strtolower((string)($row['status'] ?? 'pending')),
                'qualification' => (string)($row['qualification'] ?? ''),
                'working_place' => (string)($row['working_place'] ?? ($row['hospital'] ?? '')),
            ];
        }

        $jsonOut(true, ['members' => $members, 'role_options' => $memberRoleOptions]);
    }

    if ($action === 'save_admin') {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        $fullName = clean((string)($_POST['full_name'] ?? ''));
        $username = clean((string)($_POST['username'] ?? ''));
        $email = clean((string)($_POST['email'] ?? ''));
        $mobile = clean((string)($_POST['mobile'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = $sanitizeRole((string)($_POST['role'] ?? 'super_admin'));
        $state = clean((string)($_POST['state'] ?? 'Andhra Pradesh'));
        $district = clean((string)($_POST['district'] ?? ''));
        $status = strtolower(trim((string)($_POST['status'] ?? 'active'))) === 'disabled' ? 0 : 1;
        $permissions = $normalizePermissions((array)($_POST['permissions'] ?? []));

        if ($fullName === '' || $username === '') {
            $jsonOut(false, ['message' => 'Name and username are required.'], 422);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $jsonOut(false, ['message' => 'Please enter a valid email address.'], 422);
        }
        if ($role === 'district_president' && $district === '') {
            $jsonOut(false, ['message' => 'District is required for district president.'], 422);
        }
        if ($role !== 'district_president') {
            $district = '';
        }
        if ($state === '') {
            $state = 'Andhra Pradesh';
        }
        if ($permissions === []) {
            $permissions = $defaultPermissionsByRole($role);
        }

        if ($adminId <= 0) {
            if (strlen($password) < 6) {
                $jsonOut(false, ['message' => 'Password must be at least 6 characters.'], 422);
            }

            $fields = ['username', 'password'];
            $values = [':username' => $username, ':password' => password_hash($password, PASSWORD_DEFAULT)];
            if ($hasColumn('full_name')) {
                $fields[] = 'full_name';
                $values[':full_name'] = $fullName;
            }
            if ($hasColumn('email')) {
                $fields[] = 'email';
                $values[':email'] = $email;
            }
            if ($hasColumn('mobile')) {
                $fields[] = 'mobile';
                $values[':mobile'] = $mobile;
            }
            if ($hasColumn('role')) {
                $fields[] = 'role';
                $values[':role'] = $role;
            }
            if ($hasColumn('state')) {
                $fields[] = 'state';
                $values[':state'] = $state;
            }
            if ($hasColumn('district')) {
                $fields[] = 'district';
                $values[':district'] = $district;
            }
            if ($hasColumn('is_active')) {
                $fields[] = 'is_active';
                $values[':is_active'] = $status;
            }
            if ($hasColumn('can_approve_payments')) {
                $fields[] = 'can_approve_payments';
                $values[':can_approve_payments'] = in_array($role, ['super_admin', 'state_president', 'district_president'], true) ? 1 : 0;
            }
            if ($hasColumn('can_approve_id_cards')) {
                $fields[] = 'can_approve_id_cards';
                $values[':can_approve_id_cards'] = in_array($role, ['super_admin', 'state_president', 'district_president'], true) ? 1 : 0;
            }

            $placeholders = [];
            foreach ($fields as $field) {
                $placeholders[] = ':' . $field;
            }
            $sql = 'INSERT INTO admin_users (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';

            try {
                execute_query($sql, $values);
            } catch (Throwable $e) {
                $jsonOut(false, ['message' => 'Unable to create admin. Username may already exist.'], 409);
            }

            $newAdmin = fetch_one('SELECT id FROM admin_users WHERE username = :username ORDER BY id DESC LIMIT 1', [':username' => $username]);
            $newAdminId = (int)($newAdmin['id'] ?? 0);
            if ($newAdminId > 0) {
                $savePermissions($newAdminId, $permissions);
                $logAction('admin_create', $newAdminId, ['role' => $role, 'state' => $state, 'district' => $district]);
            }

            $jsonOut(true, ['message' => 'Admin created successfully.']);
        }

        $existing = fetch_one('SELECT id, username FROM admin_users WHERE id = :id LIMIT 1', [':id' => $adminId]);
        if (!$existing) {
            $jsonOut(false, ['message' => 'Admin not found.'], 404);
        }

        $updates = ['username = :username'];
        $params = [':id' => $adminId, ':username' => $username];
        if ($hasColumn('full_name')) {
            $updates[] = 'full_name = :full_name';
            $params[':full_name'] = $fullName;
        }
        if ($hasColumn('email')) {
            $updates[] = 'email = :email';
            $params[':email'] = $email;
        }
        if ($hasColumn('mobile')) {
            $updates[] = 'mobile = :mobile';
            $params[':mobile'] = $mobile;
        }
        if ($hasColumn('role')) {
            $updates[] = 'role = :role';
            $params[':role'] = $role;
        }
        if ($hasColumn('state')) {
            $updates[] = 'state = :state';
            $params[':state'] = $state;
        }
        if ($hasColumn('district')) {
            $updates[] = 'district = :district';
            $params[':district'] = $district;
        }
        if ($hasColumn('is_active')) {
            $updates[] = 'is_active = :is_active';
            $params[':is_active'] = $status;
        }
        if ($hasColumn('can_approve_payments')) {
            $updates[] = 'can_approve_payments = :can_approve_payments';
            $params[':can_approve_payments'] = in_array($role, ['super_admin', 'state_president', 'district_president'], true) ? 1 : 0;
        }
        if ($hasColumn('can_approve_id_cards')) {
            $updates[] = 'can_approve_id_cards = :can_approve_id_cards';
            $params[':can_approve_id_cards'] = in_array($role, ['super_admin', 'state_president', 'district_president'], true) ? 1 : 0;
        }
        if ($password !== '') {
            if (strlen($password) < 6) {
                $jsonOut(false, ['message' => 'Password must be at least 6 characters.'], 422);
            }
            $updates[] = 'password = :password';
            $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        try {
            execute_query('UPDATE admin_users SET ' . implode(', ', $updates) . ' WHERE id = :id', $params);
        } catch (Throwable $e) {
            $jsonOut(false, ['message' => 'Unable to update admin. Username may already exist.'], 409);
        }

        $savePermissions($adminId, $permissions);
        $logAction('admin_update', $adminId, ['role' => $role, 'state' => $state, 'district' => $district]);
        $jsonOut(true, ['message' => 'Admin updated successfully.']);
    }

    if ($action === 'save_member_role') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $memberRole = clean((string)($_POST['member_role'] ?? ''));
        $designation = clean((string)($_POST['designation'] ?? ''));

        if ($memberId <= 0) {
            $jsonOut(false, ['message' => 'Invalid member ID.'], 422);
        }

        if ($memberRole === '') {
            $jsonOut(false, ['message' => 'Select a role for the member.'], 422);
        }

        $updates = [];
        $params = [':id' => $memberId, ':role' => $memberRole];
        $columns = $getColumns();
        if (isset($columns['role'])) {
            $updates[] = 'role = :role';
        }
        if ($designation !== '' && isset($columns['designation'])) {
            $updates[] = 'designation = :designation';
            $params[':designation'] = $designation;
        }

        if ($updates === []) {
            $jsonOut(false, ['message' => 'No member role column available.'], 422);
        }

        execute_query('UPDATE members SET ' . implode(', ', $updates) . ' WHERE id = :id', $params);
        $logAction('member_role_update', $memberId, ['role' => $memberRole, 'designation' => $designation]);
        $jsonOut(true, ['message' => 'Member role updated successfully.']);
    }

    if ($action === 'reset_password') {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');
        if ($adminId <= 0 || strlen($newPassword) < 6) {
            $jsonOut(false, ['message' => 'Valid admin and password are required.'], 422);
        }
        execute_query('UPDATE admin_users SET password = :password WHERE id = :id', [
            ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => $adminId,
        ]);
        $logAction('admin_password_reset', $adminId, null);
        $jsonOut(true, ['message' => 'Password reset completed.']);
    }

    if ($action === 'set_status') {
        if (!$hasColumn('is_active')) {
            $jsonOut(false, ['message' => 'Status column not available.'], 422);
        }

        $adminId = (int)($_POST['admin_id'] ?? 0);
        $status = strtolower(trim((string)($_POST['status'] ?? 'active'))) === 'disabled' ? 0 : 1;
        $selfId = (int)($_SESSION['admin_user_id'] ?? 0);

        if ($adminId <= 0) {
            $jsonOut(false, ['message' => 'Invalid admin ID.'], 422);
        }
        if ($selfId > 0 && $selfId === $adminId && $status === 0) {
            $jsonOut(false, ['message' => 'You cannot disable your own account.'], 422);
        }

        execute_query('UPDATE admin_users SET is_active = :status WHERE id = :id', [':status' => $status, ':id' => $adminId]);
        $logAction('admin_status_change', $adminId, ['status' => $status === 1 ? 'active' : 'disabled']);
        $jsonOut(true, ['message' => 'Status updated.']);
    }

    if ($action === 'delete_admin') {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        $selfId = (int)($_SESSION['admin_user_id'] ?? 0);
        if ($adminId <= 0) {
            $jsonOut(false, ['message' => 'Invalid admin ID.'], 422);
        }
        if ($selfId > 0 && $selfId === $adminId) {
            $jsonOut(false, ['message' => 'You cannot delete your own account.'], 422);
        }

        execute_query('DELETE FROM admin_permissions WHERE admin_id = :id', [':id' => $adminId]);
        execute_query('DELETE FROM admin_users WHERE id = :id', [':id' => $adminId]);
        $logAction('admin_delete', $adminId, null);
        $jsonOut(true, ['message' => 'Admin deleted successfully.']);
    }

    if ($action === 'bulk_action') {
        $ids = (array)($_POST['selected_ids'] ?? []);
        $operation = strtolower(trim((string)($_POST['bulk_action'] ?? '')));
        $newRole = $sanitizeRole((string)($_POST['new_role'] ?? 'super_admin'));
        $selectedIds = [];
        foreach ($ids as $id) {
            $value = (int)$id;
            if ($value > 0) {
                $selectedIds[] = $value;
            }
        }
        $selectedIds = array_values(array_unique($selectedIds));

        if ($selectedIds === []) {
            $jsonOut(false, ['message' => 'Select at least one admin.'], 422);
        }

        $selfId = (int)($_SESSION['admin_user_id'] ?? 0);
        if ($selfId > 0) {
            $selectedIds = array_values(array_filter($selectedIds, static fn (int $id): bool => $id !== $selfId));
            if ($selectedIds === []) {
                $jsonOut(false, ['message' => 'Bulk action cannot target your own account.'], 422);
            }
        }

        $placeholders = [];
        $params = [];
        foreach ($selectedIds as $idx => $id) {
            $key = ':id' . $idx;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        if ($operation === 'activate' || $operation === 'disable') {
            if (!$hasColumn('is_active')) {
                $jsonOut(false, ['message' => 'Status column not available.'], 422);
            }
            $statusValue = $operation === 'activate' ? 1 : 0;
            execute_query(
                'UPDATE admin_users SET is_active = :status WHERE id IN (' . implode(', ', $placeholders) . ')',
                array_merge([':status' => $statusValue], $params)
            );
            $logAction('bulk_status_change', null, ['operation' => $operation, 'ids' => $selectedIds]);
            $jsonOut(true, ['message' => 'Bulk status updated.']);
        }

        if ($operation === 'delete') {
            execute_query('DELETE FROM admin_permissions WHERE admin_id IN (' . implode(', ', $placeholders) . ')', $params);
            execute_query('DELETE FROM admin_users WHERE id IN (' . implode(', ', $placeholders) . ')', $params);
            $logAction('bulk_delete', null, ['ids' => $selectedIds]);
            $jsonOut(true, ['message' => 'Selected admins deleted.']);
        }

        if ($operation === 'change_role') {
            if (!$hasColumn('role')) {
                $jsonOut(false, ['message' => 'Role column not available.'], 422);
            }
            execute_query(
                'UPDATE admin_users SET role = :role WHERE id IN (' . implode(', ', $placeholders) . ')',
                array_merge([':role' => $newRole], $params)
            );
            $newPermissions = $defaultPermissionsByRole($newRole);
            foreach ($selectedIds as $adminId) {
                $savePermissions($adminId, $newPermissions);
            }
            $logAction('bulk_role_change', null, ['role' => $newRole, 'ids' => $selectedIds]);
            $jsonOut(true, ['message' => 'Selected admins role updated.']);
        }

        $jsonOut(false, ['message' => 'Invalid bulk action.'], 422);
    }

    if ($action === 'logs') {
        $targetAdminId = (int)($_GET['admin_id'] ?? 0);
        $params = [];
        $where = '';
        if ($targetAdminId > 0) {
            $where = ' WHERE l.target_admin_id = :target OR l.actor_admin_id = :target';
            $params[':target'] = $targetAdminId;
        }

        $rows = fetch_all(
            'SELECT l.id, l.actor_admin_id, l.target_admin_id, l.action, l.details, l.ip_address, l.created_at,
                    actor.username AS actor_username,
                    target.username AS target_username
             FROM admin_logs l
             LEFT JOIN admin_users actor ON actor.id = l.actor_admin_id
             LEFT JOIN admin_users target ON target.id = l.target_admin_id
             ' . $where . '
             ORDER BY l.id DESC
             LIMIT 120',
            $params
        );

        $logs = [];
        foreach ($rows as $row) {
            $logs[] = [
                'id' => (int)($row['id'] ?? 0),
                'actor' => (string)($row['actor_username'] ?? ('#' . (int)($row['actor_admin_id'] ?? 0))),
                'target' => (string)($row['target_username'] ?? ($row['target_admin_id'] ? '#' . (int)$row['target_admin_id'] : '-')),
                'action' => (string)($row['action'] ?? ''),
                'details' => (string)($row['details'] ?? ''),
                'ip' => (string)($row['ip_address'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }

        $jsonOut(true, ['logs' => $logs]);
    }

    $jsonOut(false, ['message' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    $jsonOut(false, ['message' => 'Server error: ' . $e->getMessage()], 500);
}
