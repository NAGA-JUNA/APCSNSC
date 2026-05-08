<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('admin/add_member.php');
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid request token. Please refresh and retry.');
    redirect_to('admin/add_member.php');
}

$districtMap = [
    'Anantapur' => 'ATP',
    'Kurnool' => 'KNL',
    'Nandyal' => 'NDL',
    'Kadapa' => 'KDP',
    'Chittoor' => 'CTR',
    'Tirupati' => 'TPT',
    'Nellore' => 'NLR',
    'Prakasam' => 'PKM',
    'Guntur' => 'GNT',
    'Bapatla' => 'BPT',
    'Palnadu' => 'PLD',
    'Krishna' => 'KRI',
    'NTR' => 'NTR',
    'Eluru' => 'ELR',
    'West Godavari' => 'WGD',
    'East Godavari' => 'EGD',
    'Kakinada' => 'KAK',
    'Konaseema' => 'KNS',
    'Vizianagaram' => 'VZM',
    'Visakhapatnam' => 'VZG',
    'Anakapalli' => 'AKP',
    'Alluri Sitharama Raju' => 'ASR',
    'Srikakulam' => 'SKM',
];

$ensureMembersTable = static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id VARCHAR(50) UNIQUE,
            full_name VARCHAR(150),
            district VARCHAR(100),
            phone VARCHAR(20),
            qualification VARCHAR(100),
            designation VARCHAR(100),
            working_place VARCHAR(150),
            photo VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $requiredColumns = [
        'full_name' => 'VARCHAR(150) DEFAULT NULL',
        'district' => 'VARCHAR(100) DEFAULT NULL',
        'phone' => 'VARCHAR(20) DEFAULT NULL',
        'qualification' => 'VARCHAR(100) DEFAULT NULL',
        'designation' => 'VARCHAR(100) DEFAULT NULL',
        'working_place' => 'VARCHAR(150) DEFAULT NULL',
        'photo' => 'VARCHAR(255) DEFAULT NULL',
    ];

    foreach ($requiredColumns as $column => $definition) {
        $check = fetch_one(
            'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "members" AND COLUMN_NAME = :column',
            [':column' => $column]
        );

        if ((int)($check['total'] ?? 0) === 0) {
            $pdo->exec('ALTER TABLE members ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
};

$generateMemberId = static function (PDO $pdo, string $district, array $map): string {
    $prefix = 'APCSNSC';
    $districtCode = $map[$district] ?? null;

    if ($districtCode === null) {
        throw new RuntimeException('Invalid district selected.');
    }

    $pattern = $prefix . '-' . $districtCode . '-%';

    $stmt = $pdo->prepare('SELECT member_id FROM members WHERE member_id LIKE :pattern ORDER BY member_id DESC LIMIT 1 FOR UPDATE');
    $stmt->execute([':pattern' => $pattern]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $nextNumber = 1;
    if ($row && isset($row['member_id']) && preg_match('/(\d{5})$/', (string)$row['member_id'], $match)) {
        $nextNumber = ((int)$match[1]) + 1;
    }

    return $prefix . '-' . $districtCode . '-' . sprintf('%05d', $nextNumber);
};

$fullName = clean($_POST['full_name'] ?? '');
$district = clean($_POST['district'] ?? '');
$memberIdInput = clean($_POST['member_id'] ?? '');
$phone = clean($_POST['phone'] ?? '');
$qualification = clean($_POST['qualification'] ?? '');
$designation = clean($_POST['designation'] ?? '');
$workingPlace = clean($_POST['working_place'] ?? '');

if ($fullName === '' || $district === '' || $phone === '' || $qualification === '' || $designation === '' || $workingPlace === '') {
    set_flash('error', 'Please fill all required fields.');
    redirect_to('admin/add_member.php');
}

if (!isset($districtMap[$district])) {
    set_flash('error', 'Invalid district selected.');
    redirect_to('admin/add_member.php');
}

$photoPath = upload_image($_FILES['photo'] ?? [], 'uploads/members/photos');

$pdo = db();
$ensureMembersTable($pdo);

$columns = fetch_all('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "members"');
$columnSet = [];
foreach ($columns as $col) {
    $columnSet[(string)$col['COLUMN_NAME']] = true;
}

try {
    $pdo->beginTransaction();

    $memberId = $memberIdInput;

    if ($memberId === '' || strpos($memberId, 'APCSNSC-') !== 0) {
        $memberId = $generateMemberId($pdo, $district, $districtMap);
    }

    $duplicate = fetch_one('SELECT id FROM members WHERE member_id = :member_id LIMIT 1 FOR UPDATE', [
        ':member_id' => $memberId,
    ]);

    if ($duplicate) {
        throw new RuntimeException('Member ID already exists. Please regenerate and submit again.');
    }

    $data = [];

    if (isset($columnSet['member_id'])) {
        $data['member_id'] = $memberId;
    }
    if (isset($columnSet['full_name'])) {
        $data['full_name'] = $fullName;
    }
    if (isset($columnSet['name'])) {
        $data['name'] = $fullName;
    }
    if (isset($columnSet['district'])) {
        $data['district'] = $district;
    }
    if (isset($columnSet['phone'])) {
        $data['phone'] = $phone;
    }
    if (isset($columnSet['qualification'])) {
        $data['qualification'] = $qualification;
    }
    if (isset($columnSet['designation'])) {
        $data['designation'] = $designation;
    }
    if (isset($columnSet['working_place'])) {
        $data['working_place'] = $workingPlace;
    }
    if (isset($columnSet['hospital'])) {
        $data['hospital'] = $workingPlace;
    }
    if (isset($columnSet['role'])) {
        $data['role'] = $designation;
    }
    if (isset($columnSet['photo'])) {
        $data['photo'] = $photoPath;
    }
    if (isset($columnSet['status'])) {
        $data['status'] = 'approved';
    }

    $insertColumns = array_keys($data);
    $placeholders = array_map(static fn(string $key): string => ':' . $key, $insertColumns);

    $sql = 'INSERT INTO members (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);

    foreach ($data as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }

    $stmt->execute();
    $pdo->commit();

    set_flash('success', 'Member saved successfully. Member ID: ' . $memberId);
    redirect_to('admin/members.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash('error', $e->getMessage());
    redirect_to('admin/add_member.php');
}
