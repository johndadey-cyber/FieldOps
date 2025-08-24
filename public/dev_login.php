<?php

/**
 * DEV-ONLY login shim
 * /dev_login.php?role=dispatcher
 * /dev_login.php?role=field_tech&id=123
 * Add &loose=1 to bypass APP_ENV=dev requirement if on localhost.
 */

declare(strict_types=1);

require __DIR__ . '/_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($ip, ['127.0.0.1','::1'], true);

// Resolve APP_ENV from getenv() OR config/local.env.php
$appEnv = getenv('APP_ENV') ?: ($_SESSION['APP_ENV'] ?? null);
if ($appEnv === null) {
    $local = __DIR__ . '/../config/local.env.php';
    if (is_file($local)) {
        $ret = require $local;
        if (is_array($ret)) {
            $appEnv = $ret['APP_ENV'] ?? 'dev';
        } elseif (isset($APP_ENV)) {
            $appEnv = $APP_ENV;
        }
    }
}
$appEnv = $appEnv ?: 'dev';

$loose = isset($_GET['loose']) && $_GET['loose'] === '1';

header('Content-Type: application/json; charset=utf-8');

// Enforce localhost always
if (!$isLocal) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Forbidden: not localhost',
        'remote_addr' => $ip,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Require dev env unless loose=1
if ($appEnv !== 'dev' && !$loose) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Forbidden: APP_ENV must be dev (or pass loose=1)',
        'app_env' => $appEnv,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Seed role
$role = $_GET['role'] ?? 'dispatcher';
if (!in_array($role, ['dispatcher','field_tech','admin'], true)) {
    $role = 'dispatcher';
}
$id = (isset($_GET['id']) && ctype_digit($_GET['id'])) ? (int)$_GET['id'] : null;

$_SESSION['APP_ENV'] = $appEnv;
$_SESSION['role'] = $role;
$_SESSION['user'] = ['role' => $role];
if ($role === 'field_tech' && $id) {
    $_SESSION['user']['id'] = $id; // convention: employee id for field_tech
}
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));

echo json_encode([
  'ok'    => true,
  'role'  => $role,
  'id'    => $_SESSION['user']['id'] ?? null,
  'token' => $_SESSION['csrf_token'],
  'app_env' => $appEnv,
  'remote_addr' => $ip,
], JSON_UNESCAPED_SLASHES);
