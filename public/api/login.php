<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';

require __DIR__ . '/../_csrf.php';
require_once __DIR__ . '/../../helpers/json_out.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/AuditLog.php';

$ip         = $_SERVER['REMOTE_ADDR'] ?? '';
$identifier = '';
$csrfOk     = null;
$userFound  = null;
$context    = [
    'identifier' => $identifier,
    'ip' => $ip,
    'csrf_valid' => $csrfOk,
    'user_found' => $userFound,
    'password' => '[redacted]',
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    $context['error'] = 'method_not_allowed';
    error_log(print_r($context, true), 3, __DIR__.'/../../logs/login_debug.log');
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
if (isset($data['username']) && is_string($data['username'])) {
    $identifier = trim($data['username']);
} elseif (isset($data['email']) && is_string($data['email'])) {
    $identifier = trim($data['email']);
}
$context['identifier'] = $identifier;
$password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
$debugLogin = getenv('DEBUG_LOGIN');

$csrfOk = csrf_verify($token);
$context['csrf_valid'] = $csrfOk;
if (!$csrfOk) {
    csrf_log_failure_payload($raw, $data);
    error_log("CSRF validation failed\n", 3, __DIR__.'/../../logs/login_debug.log');
    $context['error'] = 'invalid_csrf';
    error_log(print_r($context, true), 3, __DIR__.'/../../logs/login_debug.log');
    return json_out(['ok' => false, 'error' => 'Invalid CSRF token'], 422);
}

if ($identifier === '' || $password === '') {
    $context['error'] = 'missing_fields';
    error_log(print_r($context, true), 3, __DIR__.'/../../logs/login_debug.log');
    return json_out(['ok' => false, 'error' => 'Missing fields'], 400);
}

try {
    $pdo  = getPDO();

    $user = User::findByIdentifier($pdo, $identifier);
    $userFound = $user !== null;
    $context['user_found'] = $userFound;
    error_log('user_found=' . ($userFound ? 'yes' : 'no'), 3, __DIR__.'/../../logs/login_debug.log');

    $passwordMatch = false;
    if ($user !== null) {
        $passwordMatch = password_verify($password, (string)$user['password']);
        error_log('password match=' . ($passwordMatch ? 'yes' : 'no'), 3, __DIR__.'/../../logs/login_debug.log');
    }

    if ($user === null) {
        try {
            AuditLog::insert($pdo, null, 'login_failure', [
                'identifier' => $identifier,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        } catch (Throwable) {
            // ignore
        }
        $context['error'] = 'invalid_credentials';
        error_log(print_r($context, true), 3, __DIR__.'/../../logs/login_debug.log');
        $resp = [
            'ok' => false,
            'message' => $debugLogin ? 'Unknown username' : 'Invalid credentials',
        ];
        if ($debugLogin) {
            $resp['username_ok'] = false;
        }
        return json_out($resp, 401);
    }

    if (!$passwordMatch) {
        try {
            AuditLog::insert($pdo, (int)$user['id'], 'login_failure', [
                'identifier' => $identifier,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        } catch (Throwable) {
            // ignore
        }
        $context['error'] = 'invalid_credentials';
        error_log(print_r($context, true), 3, __DIR__.'/../../logs/login_debug.log');
        $resp = [
            'ok' => false,
            'message' => $debugLogin ? 'Incorrect password' : 'Invalid credentials',
        ];
        if ($debugLogin) {
            $resp['username_ok'] = true;
            $resp['password_ok'] = false;
        }
        return json_out($resp, 401);
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
    $context['user_id'] = $id;
    $context['role']    = $role;

    User::updateLastLogin($pdo, $id);

    try {
        AuditLog::insert($pdo, $id, 'login_success', [
            'identifier' => $identifier,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable) {
        // ignore
    }

    $context['event'] = 'login_success';
    error_log(print_r($context, true), 3, __DIR__.'/../../logs/login_debug.log');

    $resp = [
        'ok' => true,
        'role' => $role,
        'message' => 'Login successful',
    ];
    if ($debugLogin) {
        $resp['username_ok'] = true;
        $resp['password_ok'] = true;
    }
    return json_out($resp);

} catch (Throwable $e) {
    $context['error'] = 'exception: ' . $e->getMessage();
    error_log(print_r($context, true), 3, __DIR__.'/../../logs/login_debug.log');
    return json_out(['ok' => false, 'error' => 'Server error'], 500);
}
