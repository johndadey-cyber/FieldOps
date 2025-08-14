<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Only allow in test/localhost
$envOk = (getenv('APP_ENV') === 'test') || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);

if (!$envOk) { http_response_code(403); echo 'Forbidden'; exit; }

$role = $_GET['role'] ?? 'dispatcher';
$role = in_array($role, ['dispatcher','field_tech'], true) ? $role : 'dispatcher';
$id   = (isset($_GET['id']) && is_numeric($_GET['id'])) ? (int)$_GET['id'] : 1;

// Seed both session shapes
$_SESSION['role']         = $role;
$_SESSION['user']         = $_SESSION['user'] ?? [];
$_SESSION['user']['role'] = $role;
$_SESSION['user']['id']   = $id;

// Always ensure a CSRF token exists
$_SESSION['csrf_token']   = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok'    => true,
  'role'  => $role,
  'id'    => $id,
  'token' => $_SESSION['csrf_token'],
]);
