<?php
/**
 * public/dev_job_save_debug.php
 * Identical to job_save but ALWAYS returns exception message in `detail` for debugging.
 * Guarded + RBAC + CSRF like the real thing.
 * Call this endpoint manually with the same payload as smoke to see the root cause.
 */
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$wantsJson = true; // always JSON for debug

function json_out(array $payload, int $code = 200): never {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_SLASHES);
  exit;
}

// RBAC
$roleA = $_SESSION['role'] ?? '';
$roleB = $_SESSION['user']['role'] ?? '';
$role  = $roleA ?: $roleB;
if ($role !== 'dispatcher') {
  json_out(['ok'=>false,'error'=>'Forbidden','code'=>403], 403);
}

// CSRF
$csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!$csrf || !isset($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$csrf)) {
  json_out(['ok'=>false,'error'=>'Bad CSRF','code'=>400], 400);
}

// Inputs
$customerId      = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$description     = trim((string)($_POST['description'] ?? ''));
$scheduledDate   = trim((string)($_POST['scheduled_date'] ?? ''));
$scheduledTime   = trim((string)($_POST['scheduled_time'] ?? ''));
$durationMinutes = isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : 0;
$statusIn        = trim((string)($_POST['status'] ?? ''));

$allowedStatuses = [
  'draft','scheduled','assigned','in_progress','completed','closed','cancelled',
  'unassigned','Unassigned','Draft','Scheduled','Assigned','In Progress','Completed','Closed','Cancelled'
];
$status = $statusIn !== '' ? $statusIn : 'Unassigned';
if (!in_array($status, $allowedStatuses, true)) { $status = 'Unassigned'; }

$errors = [];
if ($customerId <= 0)      { $errors[] = 'customer_id required'; }
if ($description === '')   { $errors[] = 'description required'; }
if ($scheduledDate === '') { $errors[] = 'scheduled_date required'; }
if ($scheduledTime === '') { $errors[] = 'scheduled_time required'; }
if ($durationMinutes <= 0) { $errors[] = 'duration_minutes must be > 0'; }
if ($errors) { json_out(['ok'=>false,'error'=>'Validation','code'=>422,'fields'=>$errors], 422); }

require_once __DIR__ . '/../config/database.php';

try {
  $pdo = getPDO();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Confirm customer exists
  $chk = $pdo->prepare('SELECT id FROM customers WHERE id = :id');
  $chk->execute([':id'=>$customerId]);
  if (!$chk->fetchColumn()) {
    json_out(['ok'=>false,'error'=>'Unknown customer','code'=>404], 404);
  }

  $pdo->beginTransaction();

  $sql = "INSERT INTO jobs
          (customer_id, description, status, scheduled_date, scheduled_time, duration_minutes)
          VALUES (:cid, :desc, :status, :sdate, :stime, :dur)";
  $st = $pdo->prepare($sql);
  $st->execute([
    ':cid'   => $customerId,
    ':desc'  => $description,
    ':status'=> $status,
    ':sdate' => $scheduledDate,
    ':stime' => $scheduledTime,
    ':dur'   => $durationMinutes,
  ]);
  $jobId = (int)$pdo->lastInsertId();

  $pdo->commit();
  json_out(['ok'=>true,'id'=>$jobId,'customer_id'=>$customerId,'status'=>$status], 200);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
  // Always include message so we can see EXACT root cause
  json_out(['ok'=>false,'error'=>'Server error','code'=>500,'detail'=>$e->getMessage()], 500);
}
