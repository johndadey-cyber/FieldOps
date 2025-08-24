<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require_once __DIR__ . '/../../config/database.php';
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

$email = isset($data['email']) && is_string($data['email']) ? trim($data['email']) : '';

try {
    $pdo = getPDO();
    $userId = null;
    if ($email !== '') {
        $st = $pdo->prepare('SELECT id, email FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        if ($st !== false) {
            $st->execute([':email' => $email]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                $userId = (int)$row['id'];
                $token = bin2hex(random_bytes(16));
                $hash  = hash('sha256', $token);
                $exp   = date('Y-m-d H:i:s', time() + 3600);
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = :uid')->execute([':uid' => $userId]);
                $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:uid,:hash,:exp)')
                    ->execute([':uid' => $userId, ':hash' => $hash, ':exp' => $exp]);
                $host = $_SERVER['HTTP_HOST'] ?? 'example.com';
                $link = 'https://' . $host . '/password_reset.php?token=' . urlencode($token);
                @mail($email, 'Password Reset', 'Reset your password: ' . $link);
            }
        }
    }
    AuditLog::insert($pdo, $userId, 'forgot_password', ['email' => $email]);
} catch (Throwable $e) {
    // swallow errors to avoid leaking information
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
