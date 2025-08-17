<?php
declare(strict_types=1);

/**
 * POST /api/availability/override.php
 * Create or update date-specific availability overrides.
 * JSON body: {id?, employee_id, date, status, start_time?, end_time?, reason?}
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_json']);
    exit;
}

$eid = (int)($data['employee_id'] ?? 0);
$date = (string)($data['date'] ?? '');
$status = strtoupper((string)($data['status'] ?? ''));
$start = $data['start_time'] ?? null;
$end   = $data['end_time'] ?? null;
$reason = $data['reason'] ?? null;
$id = isset($data['id']) ? (int)$data['id'] : 0;

$errors = [];
if ($eid <= 0) $errors[] = 'employee_id';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = 'date';
if (!in_array($status, ['UNAVAILABLE','AVAILABLE','PARTIAL'], true)) $errors[] = 'status';
if ($start !== null && !preg_match('/^\d{2}:\d{2}$/', (string)$start)) $errors[] = 'start_time';
if ($end   !== null && !preg_match('/^\d{2}:\d{2}$/', (string)$end)) $errors[] = 'end_time';
if ($start !== null && $end !== null && $start >= $end) $errors[] = 'range';

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'errors'=>$errors]);
    exit;
}

$startUtc = $start !== null ? $start . ':00' : null;
$endUtc   = $end   !== null ? $end . ':00'   : null;

// Simple conflict warning: check for existing assignments on that date
$warning = null;
$stJob = $pdo->prepare("SELECT j.id, j.scheduled_time, j.duration_minutes FROM job_employee_assignment a JOIN jobs j ON j.id=a.job_id WHERE a.employee_id=:eid AND j.scheduled_date=:d");
$stJob->execute([':eid'=>$eid, ':d'=>$date]);
$jobs = $stJob->fetchAll(PDO::FETCH_ASSOC);
if ($jobs) {
    $warning = 'assignment_conflict';
}

if ($id > 0) {
    $st = $pdo->prepare("UPDATE employee_availability_overrides SET employee_id=:eid, date=:d, status=:s, start_time=:st, end_time=:et, reason=:r WHERE id=:id");
    $st->execute([':eid'=>$eid,':d'=>$date,':s'=>$status,':st'=>$startUtc,':et'=>$endUtc,':r'=>$reason,':id'=>$id]);
} else {
    $st = $pdo->prepare("INSERT INTO employee_availability_overrides (employee_id, date, status, start_time, end_time, reason) VALUES (:eid,:d,:s,:st,:et,:r)");
    $st->execute([':eid'=>$eid,':d'=>$date,':s'=>$status,':st'=>$startUtc,':et'=>$endUtc,':r'=>$reason]);
    $id = (int)$pdo->lastInsertId();
}


try {
    $uid = $_SESSION['user']['id'] ?? null;
    $det = json_encode(['id'=>$id,'date'=>$date,'status'=>$status,'start'=>$start,'end'=>$end,'reason'=>$reason], JSON_UNESCAPED_UNICODE);
    $act = $id > 0 ? 'override_update' : 'override_create';
    $pdo->prepare('INSERT INTO availability_audit (employee_id, user_id, action, details) VALUES (:eid,:uid,:act,:det)')
        ->execute([':eid'=>$eid, ':uid'=>$uid, ':act'=>$act, ':det'=>$det]);
} catch (Throwable $e) {
    // ignore audit errors
}
$resp = ['ok'=>true,'id'=>$id];
if ($warning) $resp['warning'] = $warning;
echo json_encode($resp);

