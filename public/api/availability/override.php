<?php
declare(strict_types=1);

/**
 * POST /api/availability/override.php
 * Create or update date-specific availability overrides.
 * Overrides take precedence over recurring availability windows.
 * JSON body: {id?, employee_id, date, status, type, start_time?, end_time?, reason?}
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../../_csrf.php';
require_once __DIR__ . '/../../../helpers/availability_error_logger.php';

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Check for conflicting overrides for the same date range.
 */
function override_conflict(PDO $pdo, int $eid, string $date, ?string $start, ?string $end, ?int $excludeId = null): bool {
    $params = [':eid'=>$eid, ':d'=>$date];
    $sql = "SELECT COUNT(*) AS cnt FROM employee_availability_overrides WHERE employee_id=:eid AND date=:d";
    if ($start !== null || $end !== null) {
        $sql .= " AND NOT (COALESCE(end_time,'24:00:00') <= :st OR COALESCE(start_time,'00:00:00') >= :et)";
        $params[':st'] = $start ?? '00:00:00';
        $params[':et'] = $end   ?? '24:00:00';
    }
    if ($excludeId !== null) {
        $sql .= " AND id <> :id";
        $params[':id'] = $excludeId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return ((int)($row['cnt'] ?? 0)) > 0;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_json']);
    exit;
}

if (!csrf_verify($data['csrf_token'] ?? null)) {
    csrf_log_failure_payload($raw, $data);
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'invalid_csrf']);
    exit;
}

$eid = (int)($data['employee_id'] ?? 0);
$date = (string)($data['date'] ?? '');
  $status = strtoupper((string)($data['status'] ?? ''));
  $type   = strtoupper((string)($data['type'] ?? ''));
  $start = $data['start_time'] ?? null;
  $end   = $data['end_time'] ?? null;
  $reason = $data['reason'] ?? null;
$id = isset($data['id']) ? (int)$data['id'] : 0;

$errors = [];
if ($eid <= 0) $errors[] = 'employee_id';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = 'date';
  if (!in_array($status, ['UNAVAILABLE','AVAILABLE','PARTIAL'], true)) $errors[] = 'status';
  if (!in_array($type, ['PTO','SICK','CUSTOM'], true)) $errors[] = 'type';
if ($start !== null && !preg_match('/^\d{2}:\d{2}$/', (string)$start)) $errors[] = 'start_time';
if ($end   !== null && !preg_match('/^\d{2}:\d{2}$/', (string)$end)) $errors[] = 'end_time';
if ($start !== null && $end !== null && $start >= $end) $errors[] = 'range';

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'errors'=>$errors]);
    exit;
}

  $startUtc = $start !== null ? $start . ':00' : null;
  $endUtc   = $end   !== null ? $end   . ':00' : null;

if (override_conflict($pdo, $eid, $date, $startUtc, $endUtc, $id > 0 ? $id : null)) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'override_conflict','message'=>'Override conflicts with existing override for this date/time. Overrides take precedence over recurring availability.']);
    exit;
}

// Simple conflict warning: check for existing assignments on that date
$warning = null;
$stJob = $pdo->prepare("SELECT j.id, j.scheduled_time, j.duration_minutes FROM job_employee_assignment a JOIN jobs j ON j.id=a.job_id WHERE a.employee_id=:eid AND j.scheduled_date=:d");
$stJob->execute([':eid'=>$eid, ':d'=>$date]);
$jobs = $stJob->fetchAll(PDO::FETCH_ASSOC);
if ($jobs) {
    $warning = 'assignment_conflict';
}

try {
    if ($id > 0) {
          $st = $pdo->prepare("UPDATE employee_availability_overrides SET employee_id=:eid, date=:d, status=:s, type=:t, start_time=:st, end_time=:et, reason=:r WHERE id=:id");
          $st->execute([':eid'=>$eid,':d'=>$date,':s'=>$status,':t'=>$type,':st'=>$startUtc,':et'=>$endUtc,':r'=>$reason,':id'=>$id]);
      } else {
          $st = $pdo->prepare("INSERT INTO employee_availability_overrides (employee_id, date, status, type, start_time, end_time, reason) VALUES (:eid,:d,:s,:t,:st,:et,:r)");
          $st->execute([':eid'=>$eid,':d'=>$date,':s'=>$status,':t'=>$type,':st'=>$startUtc,':et'=>$endUtc,':r'=>$reason]);
          $id = (int)$pdo->lastInsertId();
      }
} catch (Throwable $e) {
    availability_log_error($pdo, $eid, $data, $e);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'db_error','message'=>'Failed to save override.']);
    exit;
}


try {
    $uid = $_SESSION['user']['id'] ?? null;
      $det = json_encode(['id'=>$id,'date'=>$date,'status'=>$status,'type'=>$type,'start'=>$start,'end'=>$end,'reason'=>$reason], JSON_UNESCAPED_UNICODE);
    $act = $id > 0 ? 'override_update' : 'override_create';
    $pdo->prepare('INSERT INTO availability_audit (employee_id, user_id, action, details) VALUES (:eid,:uid,:act,:det)')
        ->execute([':eid'=>$eid, ':uid'=>$uid, ':act'=>$act, ':det'=>$det]);
} catch (Throwable $e) {
    // ignore audit errors
}
$resp = ['ok'=>true,'id'=>$id];
if ($warning) $resp['warning'] = $warning;
echo json_encode($resp);

