<?php
declare(strict_types=1);

function createTestPdo(): PDO {
    $dsn = getenv('FIELDOPS_TEST_DSN');
    if ($dsn) {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if (str_starts_with($dsn, 'sqlite:')) {
            seedSqliteSchema($pdo);
        }
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: '127.0.0.1',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_NAME') ?: 'fieldops_integration'
    );
    $user = getenv('FIELDOPS_TEST_USER') ?: getenv('DB_USER') ?: 'root';
    $pass = getenv('FIELDOPS_TEST_PASS') ?: getenv('DB_PASS') ?: '1234!@#$';

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function seedSqliteSchema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS people (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT, last_name TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS customers (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT, last_name TEXT, phone TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS employees (id INTEGER PRIMARY KEY AUTOINCREMENT, person_id INTEGER, employment_type TEXT, hire_date TEXT, status TEXT, is_active INTEGER)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER, description TEXT, status TEXT, scheduled_date TEXT, scheduled_time TEXT, duration_minutes INTEGER, deleted_at TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS employee_availability (id INTEGER PRIMARY KEY AUTOINCREMENT, employee_id INTEGER, day_of_week TEXT, start_time TEXT, end_time TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS job_employee_assignment (id INTEGER PRIMARY KEY AUTOINCREMENT, job_id INTEGER, employee_id INTEGER, UNIQUE(job_id, employee_id))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS job_employee (id INTEGER PRIMARY KEY AUTOINCREMENT, job_id INTEGER, employee_id INTEGER, UNIQUE(job_id, employee_id))');
}
