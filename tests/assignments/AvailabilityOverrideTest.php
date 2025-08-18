<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../support/TestPdo.php';
require_once __DIR__ . '/../../models/AssignmentEngine.php';

#[Group('assignments')]
final class AvailabilityOverrideTest extends TestCase
{
    private PDO $pdo;
    private int $employeeId;
    private int $customerId;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = createTestPdo();

        // Ensure overrides table exists for tests
          $this->pdo->exec("CREATE TABLE IF NOT EXISTS employee_availability_overrides (
              id INT AUTO_INCREMENT PRIMARY KEY,
              employee_id INT NOT NULL,
              date DATE NOT NULL,
              status VARCHAR(20) NOT NULL,
              type VARCHAR(20) NOT NULL DEFAULT 'CUSTOM',
              start_time TIME NULL,
              end_time TIME NULL,
              reason VARCHAR(255) NULL
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Clean tables used
        $tables = ['job_employee_assignment','employee_availability_overrides','employee_availability','jobs','employees','people','customers'];
        foreach ($tables as $t) { $this->pdo->exec("DELETE FROM {$t}"); }

        $this->customerId = TestDataFactory::createCustomer($this->pdo, 'Avail', 'Customer');
        $this->employeeId = TestDataFactory::createEmployee($this->pdo, 'Olivia', 'Override');

        $this->date = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
    }

    public function testUnavailableOverrideDisqualifies(): void
    {
        $dow = (int)(new DateTimeImmutable($this->date))->format('w');
        TestDataFactory::setAvailability($this->pdo, $this->employeeId, $dow, '09:00:00', '17:00:00');
        $jobId = TestDataFactory::createJob($this->pdo, $this->customerId, 'Day job', $this->date, '10:00:00', 60, 'scheduled');
        TestDataFactory::createOverride($this->pdo, $this->employeeId, $this->date, 'UNAVAILABLE');

        $engine = new AssignmentEngine($this->pdo);
        $res = $engine->eligibleEmployeesForJob($jobId, $this->date, '10:00:00');

        $this->assertCount(0, $res['qualified']);
        $this->assertSame('not_available', $res['notQualified'][0]['reasons'][0] ?? null);
    }

    public function testAvailableOverrideAllowsOutsideHours(): void
    {
        $jobId = TestDataFactory::createJob($this->pdo, $this->customerId, 'Night job', $this->date, '20:00:00', 60, 'scheduled');
        TestDataFactory::createOverride($this->pdo, $this->employeeId, $this->date, 'AVAILABLE', '20:00:00', '22:00:00');

        $engine = new AssignmentEngine($this->pdo);
        $res = $engine->eligibleEmployeesForJob($jobId, $this->date, '20:00:00');

        $this->assertCount(1, $res['qualified']);
        $this->assertSame($this->employeeId, $res['qualified'][0]['employee_id']);
    }
}

