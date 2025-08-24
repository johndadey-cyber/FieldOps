<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';

require_once __DIR__ . '/../../helpers/auth_helpers.php';


if (!function_exists('json_out')) {
    /** @param array<string,mixed> $payload */
    function json_out(array $payload, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    return;
}

$raw  = file_get_contents('php://input');
$data = $_POST;
if (empty($data) && is_string($raw) && $raw !== '') {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $data = $json;
    }
}

$token = (string)($data['csrf_token'] ?? '');
if (!csrf_verify($token)) {
    csrf_log_failure_payload($raw, $data);
    return json_out(['ok' => false, 'error' => 'Invalid CSRF token'], 422);
}

$identifier = '';
if (isset($data['username']) && is_string($data['username'])) {
    $identifier = trim($data['username']);
} elseif (isset($data['email']) && is_string($data['email'])) {
    $identifier = trim($data['email']);
}
$password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';

if ($identifier === '' || $password === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Missing fields'], JSON_UNESCAPED_SLASHES);
    return;
}

try {
    $pdo  = getPDO();
    $result = authenticate($pdo, $identifier, $password);
    if (!$result['ok']) {
        $status = ($result['error'] === 'Invalid credentials') ? 401 : 500;
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $result['error']], JSON_UNESCAPED_SLASHES);
        return;
    }

    $user = $result['user'];
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_regenerate_id(true);
    $id   = (int)$user['id'];
    $role = (string)$user['role'];
    $_SESSION['user_id'] = $id;
    $_SESSION['role']    = $role;
    $_SESSION['user']    = ['id' => $id, 'role' => $role];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'role' => $role], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Server error'], JSON_UNESCAPED_SLASHES);
}
