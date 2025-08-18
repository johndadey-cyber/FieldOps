<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';

function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$raw   = file_get_contents('php://input');
$data  = array_merge($_GET, $_POST);
$token = (string)($data['csrf_token'] ?? '');
if (!csrf_verify($token)) {
    csrf_log_failure_payload($raw, $data);
    json_out(['ok' => false, 'error' => 'Invalid CSRF token'], 422);
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));
$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) {
    $ids = [$ids];
}
$ids = array_values(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0));
if ($ids === []) {
    json_out(['ok' => false, 'error' => 'No IDs provided'], 422);
}

$isActive = null;
if ($action === 'activate') {
    $isActive = 1;
} elseif ($action === 'deactivate') {
    $isActive = 0;
} else {
    json_out(['ok' => false, 'error' => 'Invalid action'], 422);
}

try {
    $pdo = getPDO();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "UPDATE employees SET is_active = ? WHERE id IN ($placeholders)";
    $params = array_merge([$isActive], $ids);
    $st = $pdo->prepare($sql);
    $st->execute($params);
    json_out(['ok' => true, 'updated' => $st->rowCount()]);
} catch (Throwable $e) {
    error_log('[employee_bulk_update] ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Update failed'], 500);
}
