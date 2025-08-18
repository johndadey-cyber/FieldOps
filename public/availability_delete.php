<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';

function wants_json(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $jsonQ  = isset($_GET['json']) && $_GET['json'] === '1';
    return $jsonQ || stripos($accept, 'application/json') !== false || strtolower($xhr) === 'xmlhttprequest';
}
/** @param array<string,mixed> $payload */
function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = getPDO();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST' && $method !== 'DELETE') {
    json_out(['ok'=>false,'error'=>'Method not allowed'], 405);
}

$raw   = file_get_contents('php://input');
$data  = array_merge($_GET, $_POST);
$token = (string)($data['csrf_token'] ?? '');
if (!csrf_verify($token)) {
    csrf_log_failure_payload($raw, $data);
    json_out(['ok'=>false,'error'=>'Invalid CSRF token'], 422);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($id <= 0) {
    json_out(['ok'=>false,'error'=>'id is required'], 422);
}

try {
    $rowSt = $pdo->prepare("SELECT employee_id, day_of_week, DATE_FORMAT(start_time,'%H:%i') AS st, DATE_FORMAT(end_time,'%H:%i') AS et FROM employee_availability WHERE id = :id");
    $rowSt->execute([':id'=>$id]);
    $prev = $rowSt->fetch(PDO::FETCH_ASSOC);

    $del = $pdo->prepare("DELETE FROM employee_availability WHERE id = :id");
    $ok  = $del->execute([':id'=>$id]);
    try {
        $uid = $_SESSION['user']['id'] ?? null;
        $det = json_encode(['id'=>$id,'day'=>$prev['day_of_week'] ?? null,'start'=>$prev['st'] ?? null,'end'=>$prev['et'] ?? null], JSON_UNESCAPED_UNICODE);
        $pdo->prepare('INSERT INTO availability_audit (employee_id, user_id, action, details) VALUES (:eid,:uid,:act,:det)')
            ->execute([':eid'=>$prev['employee_id'] ?? null, ':uid'=>$uid, ':act'=>'delete', ':det'=>$det]);
    } catch (Throwable $e) {
        // ignore audit errors
    }
    json_out(['ok'=> (bool)$ok]);
} catch (Throwable $e) {
    error_log('[availability_delete] ' . $e->getMessage());
    json_out(['ok'=>false,'error'=>'Delete failed'], 500);
}
