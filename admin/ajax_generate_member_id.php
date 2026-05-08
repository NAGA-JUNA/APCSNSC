<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

$district = clean($_GET['district'] ?? '');
if ($district === '') {
    http_response_code(422);
    echo json_encode(['error' => 'District is required.']);
    exit;
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

$generateMemberId = static function (PDO $pdo, string $districtName, array $map): string {
    $prefix = 'APCSNSC';
    $districtCode = $map[$districtName] ?? null;

    if ($districtCode === null) {
        throw new RuntimeException('Invalid district selected.');
    }

    $like = $prefix . '-' . $districtCode . '-%';
    $stmt = $pdo->prepare('SELECT member_id FROM members WHERE member_id LIKE :like ORDER BY member_id DESC LIMIT 1');
    $stmt->execute([':like' => $like]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $next = 1;
    if ($row && isset($row['member_id'])) {
        if (preg_match('/(\d{5})$/', (string)$row['member_id'], $match)) {
            $next = ((int)$match[1]) + 1;
        }
    }

    return $prefix . '-' . $districtCode . '-' . sprintf('%05d', $next);
};

try {
    echo json_encode([
        'member_id' => $generateMemberId(db(), $district, $districtMap),
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
}
