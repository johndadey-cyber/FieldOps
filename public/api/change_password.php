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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
    return;
}

$raw  = file_get_contents('php://input');
$data = $_POST;
if ($data === [] && is_string($raw) && $raw !== '') {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $data = $json;
    }
}

$password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
if ($password === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Missing password'], JSON_UNESCAPED_SLASHES);
    return;
}

try {
    $pdo = getPDO();
    $res = User::updatePassword($pdo, $userId, $password);
    if (!$res['ok']) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'Invalid password'], JSON_UNESCAPED_SLASHES);
        return;
    }
    try {
        AuditLog::insert($pdo, $userId, 'password_reset', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable) {
        // ignore
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Server error'], JSON_UNESCAPED_SLASHES);
}
