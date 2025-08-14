<?php
declare(strict_types=1);

/**
 * POST /api/availability/create.php
 * Save or update recurring availability windows.
 * Accepts JSON body: {id?, employee_id, day_of_week, start_time, end_time}
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_json']);
    exit;
}

$eid  = (int)($data['employee_id'] ?? 0);
$day  = (string)($data['day_of_week'] ?? '');
$start= (string)($data['start_time'] ?? '');
$end  = (string)($data['end_time'] ?? '');
$id   = isset($data['id']) ? (int)$data['id'] : 0;

$validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday','0','1','2','3','4','5','6'];
$err = [];
if ($eid <= 0) $err[] = 'employee_id';
if (!in_array($day, $validDays, true)) $err[] = 'day_of_week';
if (!preg_match('/^\d{2}:\d{2}$/', $start)) $err[] = 'start_time';
if (!preg_match('/^\d{2}:\d{2}$/', $end)) $err[] = 'end_time';
if ($start >= $end) $err[] = 'range';

if ($err) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'errors'=>$err]);
    exit;
}

$startUtc = $start . ':00';
$endUtc   = $end . ':00';

if ($id > 0) {
    $st = $pdo->prepare("UPDATE employee_availability SET day_of_week=:dow, start_time=:st, end_time=:et WHERE id=:id AND employee_id=:eid");
    $st->execute([':dow'=>$day,':st'=>$startUtc,':et'=>$endUtc,':id'=>$id,':eid'=>$eid]);
} else {
    $st = $pdo->prepare("INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES (:eid,:dow,:st,:et)");
    $st->execute([':eid'=>$eid,':dow'=>$day,':st'=>$startUtc,':et'=>$endUtc]);
    $id = (int)$pdo->lastInsertId();
}

echo json_encode(['ok'=>true,'id'=>$id]);

