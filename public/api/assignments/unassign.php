<?php
// POST /api/assignments/unassign.php  body: jobId, employeeId, csrf_token

$ROOT = dirname(__DIR__, 2);  // public
$ROOT = dirname($ROOT, 1);    // repo root

require $ROOT . '/public/_cli_guard.php';

require_once $ROOT . '/config/database.php';
require_once $ROOT . '/helpers/JsonResponse.php';
require_once $ROOT . '/helpers/auth_helpers.php';
require_once $ROOT . '/helpers/ErrorCodes.php';

if (!require_role('dispatcher')) { JsonResponse::json(['ok'=>false,'error'=>'Forbidden','code'=>403], 403); return; }
$raw  = file_get_contents('php://input');
$data = array_merge($_GET, $_POST);
if (!verify_csrf_token($data['csrf_token'] ?? null)) { csrf_log_failure_payload($raw, $data); JsonResponse::json(['ok'=>false,'error'=>'Bad CSRF','code'=>403], 403); return; }

$jobId = isset($_POST['jobId']) ? (int)$_POST['jobId'] : 0;
$employeeId = isset($_POST['employeeId']) ? (int)$_POST['employeeId'] : 0;

if ($jobId <= 0 || $employeeId <= 0) {
    JsonResponse::json(['ok'=>false,'error'=>'Missing parameters','code'=>400], 400); return;
}

try {
    $pdo = get_pdo();
    $pdo->beginTransaction();

    $del = $pdo->prepare("DELETE FROM job_employee_assignment WHERE job_id=:j AND employee_id=:e");
    $del->execute([':j'=>$jobId, ':e'=>$employeeId]);

    // If no crew remains and job hasn't started, revert to scheduled
    $pdo->prepare(
        "UPDATE jobs j
         SET j.status='scheduled', j.status_updated_at=NOW()
         WHERE j.id=:j
           AND j.status='assigned'
           AND NOT EXISTS (SELECT 1 FROM job_employee_assignment a WHERE a.job_id=j.id)"
    )->execute([':j'=>$jobId]);

    $pdo->commit();
    JsonResponse::json(['ok'=>true,'jobId'=>$jobId,'unassigned'=>$employeeId]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    JsonResponse::json(['ok'=>false,'error'=>'Server error','code'=>500], 500);
}
