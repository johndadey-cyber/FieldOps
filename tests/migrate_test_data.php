<?php
// tests/migrate_test_data.php
// Creates minimal schema for running tests in CI

declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'fieldops_integration';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '1234!@#$';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: {$e->getMessage()}\n");
    exit(1);
}

require_once __DIR__ . '/../scripts/migrate_test_db.php';
migrateTestDb($pdo);

// Seed minimal lookup data
$pdo->exec("INSERT INTO skills (id, name) VALUES (1, 'General') ON DUPLICATE KEY UPDATE name=VALUES(name)");
$pdo->exec("INSERT INTO job_types (id, name) VALUES (1, 'Basic Installation'), (2, 'Routine Maintenance') ON DUPLICATE KEY UPDATE name=VALUES(name)");
$pdo->exec("DELETE FROM checklist_templates");
$pdo->exec("INSERT INTO checklist_templates (job_type_id, description, position) VALUES
    (1, 'Review work order', 1),
    (1, 'Confirm materials on site', 2),
    (1, 'Perform installation', 3),
    (1, 'Test and verify operation', 4),
    (2, 'Inspect equipment condition', 1),
    (2, 'Perform routine maintenance', 2),
    (2, 'Update service log', 3)
");

echo "✅ Migration complete\n";
