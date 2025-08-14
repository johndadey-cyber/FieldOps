<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok'        => true,
  'APP_ENV'   => getenv('APP_ENV') ?: null,
  'session'   => [
    'role' => $_SESSION['role'] ?? null,
    'user' => $_SESSION['user']['role'] ?? null,
    'csrf' => isset($_SESSION['csrf_token']),
  ],
  'server'    => [
    'PHP_SAPI' => PHP_SAPI,
    'host'     => $_SERVER['HTTP_HOST'] ?? null,
  ],
], JSON_UNESCAPED_SLASHES);
