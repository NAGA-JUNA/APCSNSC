<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request token']);
    exit;
}

$memberId = (int)($_POST['member_id'] ?? 0);
if ($memberId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Member ID is required']);
    exit;
}

$currentRole = admin_role();
$isSuperAdmin = $currentRole === 'super_admin';
$isStatePresident = $currentRole === 'state_president';
$isDistrictPresident = $currentRole === 'district_president';
$adminDistrict = trim(admin_district());

if (!$isSuperAdmin && !$isStatePresident && !$isDistrictPresident) {
    http_response_code(403);
    echo json_encode(['error' => 'You are not authorized to generate ID cards']);
    exit;
}

$memberSql = 'SELECT id, member_id, district FROM members WHERE id = :id';
$memberParams = [':id' => $memberId];
if ($isDistrictPresident) {
    if ($adminDistrict === '') {
        http_response_code(403);
        echo json_encode(['error' => 'District mapping missing for your account']);
        exit;
    }
    $memberSql .= ' AND district = :district';
    $memberParams[':district'] = $adminDistrict;
}
$memberSql .= ' LIMIT 1';

$member = fetch_one($memberSql, $memberParams);
if (!$member) {
    http_response_code(404);
    echo json_encode(['error' => 'Member not found']);
    exit;
}

$rawFile = $_POST['file_data'] ?? '';
if (!is_string($rawFile) || $rawFile === '') {
    http_response_code(422);
    echo json_encode(['error' => 'File data is required']);
    exit;
}

if (!preg_match('/^data:image\/(png|jpeg|webp);base64,/', $rawFile, $match)) {
    http_response_code(422);
    echo json_encode(['error' => 'Unsupported file format']);
    exit;
}

$extension = $match[1] === 'jpeg' ? 'jpg' : $match[1];
$payload = substr($rawFile, strpos($rawFile, ',') + 1);
$binary = base64_decode($payload, true);
if ($binary === false) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid file payload']);
    exit;
}

$targetDir = __DIR__ . '/../uploads/id_cards';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

$fileName = 'id-card-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$member['member_id']) . '-' . date('YmdHis') . '.' . $extension;
$filePath = $targetDir . '/' . $fileName;

if (file_put_contents($filePath, $binary) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to save file']);
    exit;
}

$relativePath = 'uploads/id_cards/' . $fileName;

execute_query('ALTER TABLE members ADD COLUMN IF NOT EXISTS id_card_path VARCHAR(255) DEFAULT NULL');
execute_query('ALTER TABLE members ADD COLUMN IF NOT EXISTS id_card_generated_at DATETIME DEFAULT NULL');

execute_query('UPDATE members SET id_card_path = :path, id_card_generated_at = NOW() WHERE id = :id', [
    ':path' => $relativePath,
    ':id' => $memberId,
]);

echo json_encode([
    'success' => true,
    'path' => $relativePath,
]);
