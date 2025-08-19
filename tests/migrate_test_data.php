<?php
// tests/migrate_test_data.php
// Creates minimal schema for running tests in CI

declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'fieldops_integration';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: {$e->getMessage()}\n");
    exit(1);
}

// Define schema
$sql = [
    "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        phone VARCHAR(50) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS people (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        person_id INT NOT NULL,
        employment_type VARCHAR(50) NOT NULL,
        hire_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS employee_availability (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        day_of_week TINYINT NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        description VARCHAR(255) NOT NULL,
        status VARCHAR(20) NOT NULL,
        scheduled_date DATE NOT NULL,
        scheduled_time TIME NOT NULL,
        duration_minutes INT NOT NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS job_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS job_employee_assignment (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        employee_id INT NOT NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS job_employee (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        employee_id INT NOT NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS skills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS job_skill (
        job_id INT NOT NULL,
        skill_id INT NOT NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS employee_availability_overrides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        status VARCHAR(20) NOT NULL,
        start_time TIME NULL,
        end_time TIME NULL,
        reason VARCHAR(255) NULL
    ) ENGINE=InnoDB"
];

foreach ($sql as $query) {
    $pdo->exec($query);
}

// Seed minimal lookup data
$pdo->exec("INSERT INTO skills (id, name) VALUES (1, 'General') ON DUPLICATE KEY UPDATE name=VALUES(name)");
$pdo->exec("INSERT INTO job_types (id, name) VALUES (1, 'General') ON DUPLICATE KEY UPDATE name=VALUES(name)");

echo "✅ Migration complete\n";
