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
            'checklist_templates',
            'job_employee_assignment',
            'employee_availability',
        ];

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        foreach ($tables as $t) {
            switch ($driver) {
                case 'mysql':
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
                    ");
                    break;
                case 'sqlite':
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM sqlite_master
                        WHERE type = 'table' AND name = :t
                    ");
                    break;
                default:
                    self::fail("Unsupported PDO driver: {$driver}");
            }

            $stmt->execute([':t' => $t]);
            $this->assertSame(1, (int)$stmt->fetchColumn(), "Missing table: {$t}");
        }
    }
}

