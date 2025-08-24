<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/AuditLog.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';
try {
    $pdo = getPDO();
    AuditLog::insert($pdo, $userId, 'logout', ['ip' => $ip]);
} catch (Throwable) {
    // ignore
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);

