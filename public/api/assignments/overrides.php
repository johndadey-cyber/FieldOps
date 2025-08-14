<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$role = ($_SESSION['role'] ?? '') ?: ($_SESSION['user']['role'] ?? '');
if (!$role) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$jobId = (int)($_GET['job_id'] ?? 0);
if ($jobId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing job_id']); exit; }

require_once __DIR__ . '/../../../config/database.php';
$pdo = getPDO();

$stmt = $pdo->prepare("
  SELECT ao.id, ao.job_id, ao.employee_id, ao.reason, ao.created_at,
         p.first_name, p.last_name
  FROM assignment_overrides ao
  LEFT JOIN employees e ON e.id = ao.employee_id
  LEFT JOIN people p    ON p.id = e.person_id
  WHERE ao.job_id = ?
  ORDER BY ao.created_at DESC, ao.id DESC
");
$stmt->execute([$jobId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok'=>true,'items'=>$rows, 'count'=>count($rows)]);
