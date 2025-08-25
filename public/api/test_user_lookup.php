<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require_once __DIR__ . '/../../config/database.php';

if (getenv('APP_ENV') !== 'test') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_SLASHES);
    return;
}

$username = isset($_GET['username']) && is_string($_GET['username']) ? trim($_GET['username']) : '';
if ($username === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Missing username'], JSON_UNESCAPED_SLASHES);
    return;
}

try {
    $pdo = getPDO();
    $st  = $pdo->prepare('SELECT id, username, email FROM users WHERE username = :u LIMIT 1');
    if ($st === false) {
        throw new RuntimeException('Prepare failed');
    }
    $st->execute([':u' => $username]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_SLASHES);
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'user' => $row], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Server error'], JSON_UNESCAPED_SLASHES);
}
