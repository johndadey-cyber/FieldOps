<?php
/**
 * public/job_delete.php — drop‑in
 * - Accepts id OR job_id
 * - Guarded, RBAC (dispatcher), CSRF
 * - JSON mode returns {"ok":true,"changed":N}
 * - In APP_ENV=test, includes exception message in `detail`
 */
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$APP_ENV = getenv('APP_ENV') ?: 'dev';

$wantsJson = (
  (isset($_GET['__return']) && $_GET['__return'] === 'json') ||
  (isset($_GET['__ajax']) && $_GET['__ajax'] === '1') ||
  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') ||
  (isset($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

function json_out(array $p, int $code=200): never {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($p, JSON_UNESCAPED_SLASHES);
  exit;
}

// RBAC: dispatcher only
$role = ($_SESSION['role'] ?? '') ?: ($_SESSION['user']['role'] ?? '');
if ($role !== 'dispatcher') {
  json_out(['ok'=>false,'error'=>'Forbidden','code'=>403], 403);
}

// CSRF
$csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!$csrf || !isset($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_SESSION['csrf_token'])) {
  // NOTE: hash_equals requires same token; typo fixed below
}
if (!$csrf || !isset($_SESSION['csrf_token']) || !hash_equals((string)$csrf, (string)$_SESSION['csrf_token'])) {
  json_out(['ok'=>false,'error'=>'Bad CSRF','code'=>400], 400);
}

// Accept both id and job_id
$id = 0;
foreach (['id','job_id'] as $k) {
  if (isset($_POST[$k]) && is_numeric($_POST[$k])) { $id = (int)$_POST[$k]; break; }
  if (isset($_GET[$k])  && is_numeric($_GET[$k]))  { $id = (int)$_GET[$k];  break; }
}
if ($id <= 0) {
  json_out(['ok'=>false,'error'=>'id is required','code'=>422], 422);
}

require_once __DIR__ . '/../config/database.php';

try {
  $pdo = getPDO();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $pdo->beginTransaction();

  $upd = $pdo->prepare('UPDATE jobs SET deleted_at = NOW() WHERE id = :id');
  $upd->execute([':id'=>$id]);
  $changed = $upd->rowCount();

  $pdo->commit();

  json_out(['ok'=>true, 'action'=>'deleted', 'changed'=>$changed, 'id'=>$id], 200);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
  $detail = ($APP_ENV === 'test') ? $e->getMessage() : null;
  json_out(['ok'=>false,'error'=>'Server error','code'=>500,'detail'=>$detail], 500);
}
