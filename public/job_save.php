<?php
/**
 * public/dev_job_save_debug.php (normalized)
 * Same as before, but normalizes status to DB ENUM and ALWAYS returns exception detail.
 */
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
require __DIR__ . '/_csrf.php';
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

// Log fatal errors that might bypass normal exception handling.
register_shutdown_function(static function (): void {
  $err = error_get_last();
  if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    log_error('Fatal error: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
  }
});

// Capture incoming request payload for debugging purposes.
if (!empty($_POST)) {
  log_error('Request payload: ' . json_encode($_POST, JSON_UNESCAPED_SLASHES));
}

// Ensure request method is POST to prevent unintended access.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
  log_error('Invalid request method: ' . $method);
  json_out(['ok'=>false,'error'=>'Method not allowed','code'=>405], 405);
}

/**
 * Attempt to normalize various time formats to HH:MM.
 */
function normalize_time(string $time): ?string {
  // Accepted input formats including 24h and 12h variants, with or without
  // seconds and with optional leading zeros.
  $formats = [
    'H:i',    'H:i:s',    // 24h with leading zero
    'G:i',    'G:i:s',    // 24h without leading zero
    'g:i A',  'g:i a',    // 12h no leading zero, AM/PM
    'h:i A',  'h:i a',    // 12h with leading zero, AM/PM
    'g:i:s A','g:i:s a',  // 12h with seconds
    'h:i:s A','h:i:s a',  // 12h with seconds and leading zero
  ];
  foreach ($formats as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $time);
    $errs = DateTime::getLastErrors() ?: ['warning_count' => 0, 'error_count' => 0];
    if ($dt && $errs['warning_count'] === 0 && $errs['error_count'] === 0) {
      return $dt->format('H:i');
    }
  }
  return null;
}

// RBAC
$role = ($_SESSION['role'] ?? '') ?: ($_SESSION['user']['role'] ?? '');
if ($role !== 'dispatcher') { json_out(['ok'=>false,'error'=>'Forbidden','code'=>403], 403); }

// CSRF
$token = $_POST['csrf_token'] ?? '';
if (!csrf_verify($token)) {
  csrf_log_failure_payload(file_get_contents('php://input'), $_POST);
  json_out(['ok'=>false,'error'=>'Invalid CSRF token','code'=>400], 400);
}

// Inputs
$id             = isset($_POST['job_id']) ? (int)$_POST['job_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$customerId      = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$description     = trim((string)($_POST['description'] ?? ''));
$scheduledDate   = trim((string)($_POST['scheduled_date'] ?? ''));
$scheduledTime   = trim((string)($_POST['scheduled_time'] ?? ''));
$durationMinutes = isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : 0;
$statusIn        = trim((string)($_POST['status'] ?? ''));
$skillIds        = isset($_POST['skills']) && is_array($_POST['skills'])
    ? array_values(array_filter(array_map('intval', $_POST['skills']), static fn($v) => $v > 0))
    : [];
$jobTypeIds = isset($_POST['job_type_ids']) && is_array($_POST['job_type_ids'])
    ? array_values(array_filter(array_map('intval', $_POST['job_type_ids']), static fn($v) => $v > 0))
    : [];
if (!$jobTypeIds && isset($_POST['job_type_id'])) {
    $jt = (int)$_POST['job_type_id'];
    if ($jt > 0) { $jobTypeIds = [$jt]; }
}
$checklistItems = isset($_POST['checklist_items']) && is_array($_POST['checklist_items'])
    ? array_values(array_filter(array_map(static fn($v) => trim((string)$v), $_POST['checklist_items']), static fn($v) => $v !== ''))
    : [];

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
  $normalized = normalize_time($scheduledTime);
  if ($normalized === null) {
    $errors['scheduled_time']='Scheduled time is invalid';
    log_error("Invalid scheduled_time: $scheduledTime");
  } else {
    $scheduledTime = $normalized;
  }
}
if ($durationMinutes<=0) { $errors['duration_minutes']='Duration minutes must be > 0'; }
if (!$skillIds) { $errors['skills']='Select at least one skill'; }
foreach ($checklistItems as $desc) {
  if ($desc === '' || mb_strlen($desc) > 255) {
    $errors['checklist_items'] = 'Checklist item descriptions must be 1-255 characters.';
    break;
  }
}
if ($errors) {
  log_error('Validation failed: '.json_encode($errors));
  json_out(['ok'=>false,'errors'=>$errors,'code'=>422], 422);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/JobChecklistItem.php';

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

  // Refresh job skills
  $pdo->prepare('DELETE FROM job_skill WHERE job_id = :jid')
      ->execute([':jid' => $jobId]);
  if (!empty($skillIds)) {
      $ins = $pdo->prepare('INSERT INTO job_skill (job_id, skill_id) VALUES (:jid, :sid)');
      foreach ($skillIds as $sid) {
          $ins->execute([':jid' => $jobId, ':sid' => $sid]);
      }
  }

  // Refresh job types
  $pdo->prepare('DELETE FROM job_job_type WHERE job_id = :jid')
      ->execute([':jid' => $jobId]);
  if (!empty($jobTypeIds)) {
      $insJt = $pdo->prepare('INSERT INTO job_job_type (job_id, job_type_id) VALUES (:jid, :tid)');
      foreach ($jobTypeIds as $tid) {
          $insJt->execute([':jid' => $jobId, ':tid' => $tid]);
      }
  }

  // Refresh checklist items
  $pdo->prepare('DELETE FROM job_checklist_items WHERE job_id = :jid')
      ->execute([':jid' => $jobId]);
  if (!empty($checklistItems)) {
      $insChk = $pdo->prepare('INSERT INTO job_checklist_items (job_id, description) VALUES (:jid, :desc)');
      foreach ($checklistItems as $desc) {
          $insChk->execute([':jid' => $jobId, ':desc' => $desc]);
      }
  } elseif ($id <= 0 && !empty($jobTypeIds)) {
      foreach ($jobTypeIds as $tid) {
          JobChecklistItem::seedDefaults($pdo, $jobId, $tid);
      }
  }

  $pdo->commit();
  $action = $id > 0 ? 'updated' : 'created';
  json_out(['ok'=>true,'id'=>$jobId,'customer_id'=>$customerId,'status'=>$canonical,'action'=>$action], 200);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
  $detail = 'Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
           . PHP_EOL . $e->getTraceAsString()
           . PHP_EOL . 'POST: ' . json_encode($_POST, JSON_UNESCAPED_SLASHES);
  log_error($detail);
  json_out(['ok'=>false,'error'=>'Server error','code'=>500,'detail'=>$e->getMessage()], 500);
}
