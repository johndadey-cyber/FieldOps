<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';

require __DIR__ . '/../_csrf.php';
require_once __DIR__ . '/../../helpers/json_out.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/AuditLog.php';


$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    return json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
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
    return json_out(['ok' => false, 'error' => 'Missing fields'], 400);
}

try {
    $pdo  = getPDO();

    $user = User::findByIdentifier($pdo, $identifier);
    if ($user === null || !password_verify($password, (string)$user['password'])) {
        try {
            $uid = $user['id'] ?? null;
            AuditLog::insert($pdo, $uid ? (int)$uid : null, 'login_failure', [
                'identifier' => $identifier,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        } catch (Throwable) {
            // ignore
        }
        return json_out(['ok' => false, 'error' => 'Invalid credentials'], 401);

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


    User::updateLastLogin($pdo, $id);

    try {
        AuditLog::insert($pdo, $id, 'login_success', [
            'identifier' => $identifier,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable) {
        // ignore
    }

    json_out(['ok' => true, 'role' => $role]);

} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Server error'], 500);
}
