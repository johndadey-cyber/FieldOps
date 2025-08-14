<?php
declare(strict_types=1);
/**
 * GET /api/assignments/current.php?job_id=123
 * RBAC: dispatcher
 * Returns: { ok:true, job_id, employees:[id,...] }
 */
require __DIR__ . '/../../_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$role = ($_SESSION['role'] ?? '') ?: ($_SESSION['user']['role'] ?? '');
if ($role !== 'dispatcher') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'error'=>'Forbidden','code'=>403], JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if ($jobId <= 0) { echo json_encode(['ok'=>false,'error'=>'job_id required','code'=>422]); exit; }

require __DIR__ . '/../../../config/database.php';
try {
    $pdo = getPDO();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $st = $pdo->prepare("SELECT employee_id FROM job_employee_assignment WHERE job_id=:j");
    $st->execute([':j'=>$jobId]);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    echo json_encode(['ok'=>true,'job_id'=>$jobId,'employees'=>$ids], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $detail = (getenv('APP_ENV')==='test') ? $e->getMessage() : null;
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Server error','code'=>500,'detail'=>$detail], JSON_UNESCAPED_SLASHES);
}
