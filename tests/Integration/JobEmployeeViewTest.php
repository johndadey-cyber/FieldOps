<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../support/TestPdo.php';

#[Group('integration')]
final class JobEmployeeViewTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = createTestPdo();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    public function testViewReflectsAssignments(): void
    {
        // Seed: customer, employee, job, then assignment
        $custId = TestDataFactory::createCustomer($this->pdo, 'View', 'Test');
        $empId  = TestDataFactory::createEmployee($this->pdo, 'Vera', 'Viewer');

        $date = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
        $jobId = TestDataFactory::createJob(
            $this->pdo, $custId, 'Check view mirrors assignment', $date, '10:00:00', 45, 'scheduled'
        );

        $stmt = $this->pdo->prepare('INSERT INTO job_employee_assignment (job_id, employee_id, assigned_at) VALUES (?,?,?)');
        $stmt->execute([$jobId, $empId, date('Y-m-d H:i:s')]);

        // Assert: row appears in view
        $q = $this->pdo->prepare('SELECT job_id, employee_id FROM job_employee WHERE job_id = ? AND employee_id = ?');
        $q->execute([$jobId, $empId]);
        $row = $q->fetch();

        $this->assertIsArray($row, 'Expected assignment row in job_employee view.');
        $this->assertSame($jobId, (int)$row['job_id']);
        $this->assertSame($empId, (int)$row['employee_id']);
    }
}
