<?php
// tests/db_sanity_check.php
declare(strict_types=1);

// Load env variables from phpunit.xml manually for CLI run
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '8889';
$db   = getenv('DB_NAME') ?: 'fieldops_test';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Connected to {$db} successfully.\n\n";
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Helper to print table samples
function printSample(PDO $pdo, string $table): void {
    echo "=== {$table} (first 5 rows) ===\n";
    $stmt = $pdo->query("SELECT * FROM {$table} LIMIT 5");
    $rows = $stmt->fetchAll();
    if (!$rows) {
        echo "No rows found.\n\n";
        return;
    }
    foreach ($rows as $row) {
        echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
    }
    echo "\n";
}

// Check key tables
$tables = ['jobs', 'employees', 'job_employee_assignment', 'customers'];
foreach ($tables as $table) {
    try {
        printSample($pdo, $table);
    } catch (PDOException $e) {
        echo "⚠️  Error querying {$table}: " . $e->getMessage() . "\n\n";
    }
}

echo "Sanity check complete.\n";
