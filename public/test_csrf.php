<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Only allow in test/localhost
$envOk = (getenv('APP_ENV') === 'test') || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);
if (!$envOk) { http_response_code(403); echo 'Forbidden'; exit; }

$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'token' => $_SESSION['csrf_token']]);
