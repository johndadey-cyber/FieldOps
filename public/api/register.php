<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

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

$username = isset($data['username']) && is_string($data['username']) ? trim($data['username']) : '';
$email    = isset($data['email']) && is_string($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';

if ($username === '' || $email === '' || $password === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Missing fields'], JSON_UNESCAPED_SLASHES);
    return;
}

try {
    $pdo = getPDO();
    $res = User::create($pdo, $username, $email, $password);
    if (!$res['ok']) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'Invalid password'], JSON_UNESCAPED_SLASHES);
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'id' => $res['id'] ?? 0], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Server error'], JSON_UNESCAPED_SLASHES);
}
