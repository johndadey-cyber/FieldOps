<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$ip = $_SERVER['REMOTE_ADDR'] ?? '(none)';
$env1 = getenv('APP_ENV') ?: '(empty)';
$env2 = $_SESSION['APP_ENV'] ?? '(not set)';

// Try reading config/local.env.php too
$fromFile = '(no local.env.php)';
$local = __DIR__ . '/../config/local.env.php';
if (is_file($local)) {
    $ret = require $local;
    if (is_array($ret)) {
        $fromFile = $ret['APP_ENV'] ?? '(no APP_ENV key)';
    } elseif (isset($APP_ENV)) {
        $fromFile = $APP_ENV;
    }
}

require __DIR__ . '/../config/database.php';
$pdo = getPDO();
$db  = $pdo->query('SELECT DATABASE()')->fetchColumn();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok' => true,
  'REMOTE_ADDR' => $ip,
  'APP_ENV:getenv' => $env1,
  'APP_ENV:session' => $env2,
  'APP_ENV:local.env.php' => $fromFile,
  'database' => $db,
], JSON_UNESCAPED_SLASHES);
