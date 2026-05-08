<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

$memberId = clean($_GET['member_id'] ?? '');
if ($memberId === '') {
    echo json_encode(['exists' => false]);
    exit;
}

$row = fetch_one('SELECT id FROM members WHERE member_id = :member_id LIMIT 1', [
    ':member_id' => $memberId,
]);

echo json_encode(['exists' => $row !== null]);
