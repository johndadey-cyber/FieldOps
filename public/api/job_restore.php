<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require __DIR__ . '/../_auth.php';
require __DIR__ . '/../_csrf.php';
require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/JsonResponse.php';
require_once __DIR__ . '/../../helpers/ErrorCodes.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    JsonResponse::json(['ok'=>false,'error'=>'Method not allowed','code'=>405],405);
    return;
}

$raw = file_get_contents('php://input');
$data = $_POST;
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    csrf_log_failure_payload($raw, $data);
    JsonResponse::json(['ok'=>false,'error'=>'Invalid CSRF token','code'=>\ErrorCodes::CSRF_INVALID],400);
    return;
}

if (current_role() !== 'dispatcher') {
    JsonResponse::json(['ok'=>false,'error'=>'Forbidden','code'=>\ErrorCodes::FORBIDDEN],403);
    return;
}

$id = isset($data['job_id']) ? (int)$data['job_id'] : (isset($data['id']) ? (int)$data['id'] : 0);
$purge = !empty($data['purge']);
if ($id <= 0) {
    JsonResponse::json(['ok'=>false,'error'=>'Missing job id','code'=>\ErrorCodes::VALIDATION_ERROR],422);
    return;
}

try {
    $pdo = getPDO();
    if ($purge) {
        $st = $pdo->prepare('DELETE FROM jobs WHERE id = :id');
        $st->execute([':id'=>$id]);
        JsonResponse::json(['ok'=>true,'action'=>'purged','changed'=>$st->rowCount(),'id'=>$id]);
    } else {
        $st = $pdo->prepare('UPDATE jobs SET deleted_at = NULL WHERE id = :id');
        $st->execute([':id'=>$id]);
        JsonResponse::json(['ok'=>true,'action'=>'restored','changed'=>$st->rowCount(),'id'=>$id]);
    }
} catch (Throwable $e) {
    JsonResponse::json(['ok'=>false,'error'=>'Server error','code'=>500],500);
}
