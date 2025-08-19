<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/_csrf.php';

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

if (!csrf_verify($data['csrf_token'] ?? null)) {
    csrf_log_failure_payload($raw, $data);
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_csrf']);
    exit;
}

$eid   = (int)($data['employee_id'] ?? 0);
$date  = (string)($data['date'] ?? '');
$start = (string)($data['start_time'] ?? '');
$end   = (string)($data['end_time'] ?? '');
$reason = $data['reason'] ?? null;

$errors = [];
if ($eid <= 0) $errors[] = 'employee_id';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = 'date';
if (!preg_match('/^\d{2}:\d{2}$/', $start)) $errors[] = 'start_time';
if (!preg_match('/^\d{2}:\d{2}$/', $end)) $errors[] = 'end_time';
if ($start >= $end) $errors[] = 'range';
if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

$startUtc = $start . ':00';
$endUtc   = $end . ':00';

$hasType = true;
try {
    $col = $pdo->query("SHOW COLUMNS FROM employee_availability_overrides LIKE 'type'");
    $hasType = (bool)$col->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $hasType = true;
}

try {
    if ($hasType) {
        $st = $pdo->prepare("INSERT INTO employee_availability_overrides (employee_id, date, status, type, start_time, end_time, reason) VALUES (:eid,:d,'AVAILABLE','CUSTOM',:st,:et,:r)");
        $st->execute([':eid'=>$eid, ':d'=>$date, ':st'=>$startUtc, ':et'=>$endUtc, ':r'=>$reason]);
    } else {
        $st = $pdo->prepare("INSERT INTO employee_availability_overrides (employee_id, date, status, start_time, end_time, reason) VALUES (:eid,:d,'AVAILABLE',:st,:et,:r)");
        $st->execute([':eid'=>$eid, ':d'=>$date, ':st'=>$startUtc, ':et'=>$endUtc, ':r'=>$reason]);
    }
    $id = (int)$pdo->lastInsertId();

    try {
        $uid = $_SESSION['user']['id'] ?? null;
        $det = json_encode(['id'=>$id,'date'=>$date,'start'=>$start,'end'=>$end,'reason'=>$reason], JSON_UNESCAPED_UNICODE);
        $pdo->prepare('INSERT INTO availability_audit (employee_id, user_id, action, details) VALUES (:eid,:uid,:act,:det)')
            ->execute([':eid'=>$eid, ':uid'=>$uid, ':act'=>'override_create', ':det'=>$det]);
    } catch (Throwable $e) {
        // ignore audit errors
    }

    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
