<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
/** DEV-ONLY logout shim (localhost + test) */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$appEnv = getenv('APP_ENV') ?: ($_SESSION['APP_ENV'] ?? 'dev');
if (!in_array($ip, ['127.0.0.1','::1'], true) || $appEnv !== 'test') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Forbidden";
  exit;
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true], JSON_UNESCAPED_SLASHES);
