<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pdo = getPDO();

/* ---- Read action & body (JSON or form) ---- */
$raw = file_get_contents('php://input');
$data = (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
  ? (json_decode($raw, true) ?? [])
  : ($_POST ?? []);

$action = $_GET['action'] ?? ($data['action'] ?? '');
$csrf = $data['csrf_token'] ?? '';

if (in_array($action, ['assign','unassign','list'], true) && (!isset($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token'])) {
  echo json_encode(['ok'=>false,'error'=>'Invalid CSRF token']); exit;
}

/* ---- helper: update status based on current crew ---- */
if (!function_exists('updateJobStatus')) {
  function updateJobStatus(PDO $pdo, int $jobId): string {
    // lock row
    $s = $pdo->prepare("SELECT status FROM jobs WHERE id=? FOR UPDATE");
    $s->execute([$jobId]);
    $cur = $s->fetchColumn();
    if ($cur === false) return 'unknown';

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM job_employee_assignment WHERE job_id = ".(int)$jobId)->fetchColumn();

    $locked = ['in_progress','completed','closed','cancelled'];
    $target = $cur;

    if (!in_array($cur, $locked, true)) {
      if ($cnt > 0 && ($cur === 'draft' || $cur === 'scheduled')) $target = 'assigned';
      elseif ($cnt === 0 && $cur === 'assigned') $target = 'scheduled';
    }

    if ($target !== $cur) {
      $u = $pdo->prepare("UPDATE jobs SET status=?, updated_at=NOW() WHERE id=?");
      $u->execute([$target, $jobId]);
    }
    return $target;
  }
}

try {
  switch ($action) {
    case 'assign': {
      $jobId = (int)($data['job_id'] ?? 0);
      $empIds = array_map('intval', (array)($data['employee_ids'] ?? ($data['employee_id'] ?? [])));
      $replace = !empty($data['replace']);
      if ($jobId <= 0 || empty($empIds)) throw new RuntimeException('Invalid job/employee');

      $pdo->beginTransaction();
      if ($replace) {
        $pdo->prepare('DELETE FROM job_employee_assignment WHERE job_id=?')->execute([$jobId]);
      }
      $ins = $pdo->prepare('INSERT IGNORE INTO job_employee_assignment (job_id, employee_id, assigned_at) VALUES (?,?,NOW())');
      $changed = 0;
      foreach ($empIds as $eid) {
        $ins->execute([$jobId, $eid]);
        $changed += $ins->rowCount();
      }
      $status = updateJobStatus($pdo, $jobId);
      $pdo->commit();

      echo json_encode(['ok'=>true, 'action'=>'assigned', 'changed'=>$changed, 'status'=>$status]);
      break;
    }

    case 'unassign': {
      $jobId = (int)($data['job_id'] ?? 0);
      $eid   = (int)($data['employee_id'] ?? 0);
      if ($jobId <= 0 || $eid <= 0) throw new RuntimeException('Invalid job/employee');

      $pdo->beginTransaction();
      $del = $pdo->prepare("DELETE FROM job_employee_assignment WHERE job_id=? AND employee_id=?");
      $del->execute([$jobId, $eid]);
      $status = updateJobStatus($pdo, $jobId);
      $pdo->commit();

      echo json_encode(['ok'=>true,'changed'=>$del->rowCount(),'status'=>$status]);
      break;
    }

    case 'list': {
      $jobId = (int)($_GET['job_id'] ?? 0);
      if ($jobId <= 0) throw new RuntimeException('Invalid job ID');

      $stmt = $pdo->prepare("
        SELECT e.id, p.first_name, p.last_name
        FROM job_employee_assignment jea
        JOIN employees e ON e.id = jea.employee_id
        JOIN people p ON p.id = e.person_id
        WHERE jea.job_id = ?
        ORDER BY p.last_name, p.first_name
      ");
      $stmt->execute([$jobId]);
      echo json_encode(['ok'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
      break;
    }

    default:
      echo json_encode(['ok'=>false,'error'=>'Unknown action']);
  }
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
