<?php
// /public/api/availability/log_client_error.php
declare(strict_types=1);

header('Content-Type: application/json');

$raw = file_get_contents('php://input') ?: '';
$in = json_decode($raw, true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}
$eid   = (int)($in['employee_id'] ?? 0);
$name  = (string)($in['employee_name'] ?? '');
$msg   = (string)($in['message'] ?? '');
$stack = (string)($in['stack'] ?? '');

$logLine = sprintf(
    "[%s] eid=%d name=%s msg=%s stack=%s\n",
    date('c'),
    $eid,
    $name,
    $msg,
    $stack
);
error_log($logLine, 3, __DIR__ . '/../../../logs/availability_error.log');

echo json_encode(['ok' => true]);

