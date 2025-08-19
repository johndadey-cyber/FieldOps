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

$src = (int)($data['source_employee_id'] ?? 0);
$targets = $data['target_employee_ids'] ?? [];
$week = (string)($data['week_start'] ?? '');
if ($src <= 0 || !is_array($targets) || $targets === []) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'invalid_input']);
    exit;
}

$targets = array_values(array_filter(array_map('intval', $targets), static fn(int $v): bool => $v > 0 && $v !== $src));
if ($targets === []) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'invalid_targets']);
    exit;
}

$pdo = null;
try {
    $pdo = getPDO();
    $pdo->beginTransaction();
    $stSel = $pdo->prepare('SELECT day_of_week,start_time,end_time FROM employee_availability WHERE employee_id = :id');
    $stSel->execute([':id'=>$src]);
    $rows = $stSel->fetchAll(PDO::FETCH_ASSOC);

    $stDel = $pdo->prepare('DELETE FROM employee_availability WHERE employee_id = :eid');
    $stIns = $pdo->prepare('INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES (:eid,:dow,:st,:et)');

    foreach ($targets as $eid) {
        $stDel->execute([':eid'=>$eid]);
        foreach ($rows as $r) {
            $stIns->execute([
                ':eid'=>$eid,
                ':dow'=>$r['day_of_week'],
                ':st'=>$r['start_time'],
                ':et'=>$r['end_time']
            ]);
        }
    }
    $pdo->commit();
    echo json_encode(['ok'=>true,'updated'=>count($targets)]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[bulk_copy] '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
