<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/AuditLog.php';

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
    $user = User::findByIdentifier($pdo, $identifier);
    if ($user === null || !password_verify($password, (string)$user['password'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid credentials'], JSON_UNESCAPED_SLASHES);
        try {
            $uid = $user['id'] ?? null;
            AuditLog::insert($pdo, $uid ? (int)$uid : null, 'login_failure', [
                'identifier' => $identifier,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        } catch (Throwable) {
            // ignore
        }
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_regenerate_id(true);
    $id   = (int)$user['id'];
    $role = (string)$user['role'];
    $_SESSION['user_id'] = $id;
    $_SESSION['role']    = $role;
    $_SESSION['user']    = ['id' => $id, 'role' => $role];

    User::updateLastLogin($pdo, $id);

    try {
        AuditLog::insert($pdo, $id, 'login_success', [
            'identifier' => $identifier,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable) {
        // ignore
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'role' => $role], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Server error'], JSON_UNESCAPED_SLASHES);
}
