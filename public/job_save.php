<?php
/**
 * public/dev_job_save_debug.php (normalized)
 * Same as before, but normalizes status to DB ENUM and ALWAYS returns exception detail.
 */
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function json_out(array $p, int $code=200): never {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($p, JSON_UNESCAPED_SLASHES);
  exit;
}

function log_error(string $msg): void {
  error_log(date('[Y-m-d H:i:s] ').$msg.PHP_EOL, 3, __DIR__ . '/../logs/job_errors.log');
}

// RBAC
$role = ($_SESSION['role'] ?? '') ?: ($_SESSION['user']['role'] ?? '');
if ($role !== 'dispatcher') { json_out(['ok'=>false,'error'=>'Forbidden','code'=>403], 403); }

// CSRF
$csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!$csrf || !isset($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$csrf)) {
  json_out(['ok'=>false,'error'=>'Bad CSRF','code'=>400], 400);
}

// Inputs
$id             = isset($_POST['job_id']) ? (int)$_POST['job_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$customerId      = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$description     = trim((string)($_POST['description'] ?? ''));
$scheduledDate   = trim((string)($_POST['scheduled_date'] ?? ''));
$scheduledTime   = trim((string)($_POST['scheduled_time'] ?? ''));
$durationMinutes = isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : 0;
$statusIn        = trim((string)($_POST['status'] ?? ''));

// Normalize status to canonical ENUM values (lowercase)
$map = [
  'draft'=>'draft','Draft'=>'draft',
  'scheduled'=>'scheduled','Scheduled'=>'scheduled',
  'assigned'=>'assigned','Assigned'=>'assigned',
  'in progress'=>'in_progress','In Progress'=>'in_progress','in_progress'=>'in_progress',
  'completed'=>'completed','Completed'=>'completed',
  'closed'=>'closed','Closed'=>'closed',
  'cancelled'=>'cancelled','Canceled'=>'cancelled','Cancelled'=>'cancelled',
];
$canonical = $map[$statusIn] ?? $map[str_replace('_',' ', $statusIn)] ?? 'draft';

// Validate
$errors=[];
if ($customerId<=0)      { $errors['customer_id']='Customer is required'; }
if ($description==='')   { $errors['description']='Description is required'; }
if ($scheduledDate==='') { $errors['scheduled_date']='Scheduled date is required'; }
else {
  $dt = DateTime::createFromFormat('Y-m-d', $scheduledDate);
  $errs = DateTime::getLastErrors() ?: ['warning_count' => 0, 'error_count' => 0];
  if (!$dt || $dt->format('Y-m-d') !== $scheduledDate || $errs['warning_count'] || $errs['error_count']) {
    $errors['scheduled_date']='Scheduled date is invalid';
    log_error("Invalid scheduled_date: $scheduledDate");
  }
}
if ($scheduledTime==='') { $errors['scheduled_time']='Scheduled time is required'; }
else {
  $tt = DateTime::createFromFormat('H:i', $scheduledTime);
  $errs = DateTime::getLastErrors() ?: ['warning_count' => 0, 'error_count' => 0];
  if (!$tt || $tt->format('H:i') !== $scheduledTime || $errs['warning_count'] || $errs['error_count']) {
    $errors['scheduled_time']='Scheduled time is invalid';
    log_error("Invalid scheduled_time: $scheduledTime");
  }
}
if ($durationMinutes<=0) { $errors['duration_minutes']='Duration minutes must be > 0'; }
if ($errors) {
  log_error('Validation failed: '.json_encode($errors));
  json_out(['ok'=>false,'errors'=>$errors,'code'=>422], 422);
}

require_once __DIR__ . '/../config/database.php';

try {
  $pdo = getPDO();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Ensure customer exists
  $chk = $pdo->prepare('SELECT id FROM customers WHERE id=:id');
  $chk->execute([':id'=>$customerId]);
  if (!$chk->fetchColumn()) { json_out(['ok'=>false,'error'=>'Unknown customer','code'=>404], 404); }

  $pdo->beginTransaction();

  if ($id > 0) {
    $sql = "UPDATE jobs SET customer_id=:cid, description=:desc, status=:status,"
         . " scheduled_date=:sdate, scheduled_time=:stime, duration_minutes=:dur"
         . " WHERE id=:id";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':cid'=>$customerId, ':desc'=>$description, ':status'=>$canonical,
      ':sdate'=>$scheduledDate, ':stime'=>$scheduledTime, ':dur'=>$durationMinutes,
      ':id'=>$id,
    ]);
    $jobId = $id;
  } else {
    $sql = "INSERT INTO jobs (customer_id, description, status, scheduled_date, scheduled_time, duration_minutes)"
         . " VALUES (:cid,:desc,:status,:sdate,:stime,:dur)";
    $st  = $pdo->prepare($sql);
    $st->execute([
      ':cid'=>$customerId, ':desc'=>$description, ':status'=>$canonical,
      ':sdate'=>$scheduledDate, ':stime'=>$scheduledTime, ':dur'=>$durationMinutes,
    ]);
    $jobId = (int)$pdo->lastInsertId();
  }

  $pdo->commit();
  $action = $id > 0 ? 'updated' : 'created';
  json_out(['ok'=>true,'id'=>$jobId,'customer_id'=>$customerId,'status'=>$canonical,'action'=>$action], 200);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
  log_error('Exception: '.$e->getMessage());
  json_out(['ok'=>false,'error'=>'Server error','code'=>500,'detail'=>$e->getMessage()], 500);
}
