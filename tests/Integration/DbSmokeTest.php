<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DbSmokeTest extends TestCase
{
    public function testCanConnectAndSeeCoreTables(): void
    {
        // Recreate test PDO using env from phpunit.xml
        require_once __DIR__ . '/../support/TestPdo.php';
        $pdo = createTestPdo();

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

