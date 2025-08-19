<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../../_csrf.php';

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

$ids = $data['employee_ids'] ?? [];
$week = (string)($data['week_start'] ?? '');
if (!is_array($ids) || $ids === []) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'invalid_input']);
    exit;
}

$ids = array_values(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0));
if ($ids === []) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'invalid_ids']);
    exit;
}

try {
    $pdo = getPDO();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("DELETE FROM employee_availability WHERE employee_id IN ($placeholders)");
    $st->execute($ids);
    echo json_encode(['ok'=>true,'cleared'=>$st->rowCount()]);
} catch (Throwable $e) {
    error_log('[bulk_reset] '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
