<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DbSmokeTest extends TestCase
{
    public function testCanConnectAndSeeCoreTables(): void
    {
        // Recreate test PDO using env from phpunit.xml
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: '127.0.0.1',
                getenv('DB_PORT') ?: '8889',
                getenv('DB_NAME') ?: 'fieldops_test'
            ),
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: 'root',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        $tables = [
            'customers',
            'people',
            'employees',
            'jobs',
            'job_types',
            'job_employee_assignment',
            'employee_availability',
        ];

        foreach ($tables as $t) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
            ");
            $stmt->execute([':t' => $t]);
            $this->assertSame(1, (int)$stmt->fetchColumn(), "Missing table: {$t}");
        }
    }
}

