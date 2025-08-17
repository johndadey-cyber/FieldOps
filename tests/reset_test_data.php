<?php
declare(strict_types=1);

// Reset only the test DB
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '8889';
$db   = getenv('DB_NAME') ?: 'fieldops_integration';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// Ensure schema is up to date before clearing data
require_once __DIR__ . '/../scripts/migrate_test_db.php';
migrateTestDb($pdo);

// Wrap in a transaction for speed/safety
$pdo->beginTransaction();

// Clear volatile tables
$pdo->exec("DELETE FROM job_employee_assignment");

// Optionally reset job statuses that might have been toggled during tests
$pdo->exec("UPDATE jobs SET status = 'scheduled' WHERE id IN (2001,2002,2003)");

// (Optional) Re-seed a known assignment if you like:
// $pdo->exec("INSERT INTO job_employee_assignment (job_id, employee_id) VALUES (2003, 4002)");

$pdo->commit();

echo "✅ Test data reset complete.\n";
