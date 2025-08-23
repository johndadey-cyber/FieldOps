<?php

declare(strict_types=1);

require __DIR__ . '/migrate_test_db.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'fieldops_development';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '1234!@#$';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

migrateTestDb($pdo);
