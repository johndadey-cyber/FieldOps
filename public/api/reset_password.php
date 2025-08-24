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
if ($data === [] && is_string($raw) && $raw !== '') {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $data = $json;
    }
}

$token    = isset($data['token']) && is_string($data['token']) ? trim($data['token']) : '';
$password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
if ($token === '' || $password === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Missing fields'], JSON_UNESCAPED_SLASHES);
    return;
}

try {
    $pdo  = getPDO();
    $hash = hash('sha256', $token);
    $st   = $pdo->prepare('SELECT user_id, expires_at FROM password_resets WHERE token_hash = :hash LIMIT 1');
    if ($st === false) {
        throw new RuntimeException('Query failed');
    }
    $st->execute([':hash' => $hash]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false || strtotime((string)$row['expires_at']) < time()) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid token'], JSON_UNESCAPED_SLASHES);
        return;
    }

    $userId = (int)$row['user_id'];
    $res    = User::updatePassword($pdo, $userId, $password);
    if (!$res['ok']) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'Invalid password'], JSON_UNESCAPED_SLASHES);
        return;
    }

    $pdo->prepare('DELETE FROM password_resets WHERE token_hash = :hash')->execute([':hash' => $hash]);
    AuditLog::insert($pdo, $userId, 'reset_password');

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Server error'], JSON_UNESCAPED_SLASHES);
}
