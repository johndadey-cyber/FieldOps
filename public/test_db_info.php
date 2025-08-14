<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
require __DIR__ . '/../config/database.php';
$pdo = getPDO();
$db  = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'database' => $db], JSON_UNESCAPED_SLASHES);
