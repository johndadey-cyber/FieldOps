<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestDataFactory.php';

#[Group('integration')]
final class JobEmployeeViewTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn  = getenv('FIELDOPS_TEST_DSN')  ?: 'mysql:host=127.0.0.1;port=8889;dbname=fieldops_test;charset=utf8mb4';
        $user = getenv('FIELDOPS_TEST_USER') ?: 'root';
        $pass = getenv('FIELDOPS_TEST_PASS') ?: 'root';
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('DELETE FROM job_employee_assignment');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');
        $this->pdo->exec('DELETE FROM customers');
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

        $stmt = $this->pdo->prepare('INSERT INTO job_employee_assignment (job_id, employee_id, assigned_at) VALUES (?,?,NOW())');
        $stmt->execute([$jobId, $empId]);

        // Assert: row appears in view
        $q = $this->pdo->prepare('SELECT job_id, employee_id FROM job_employee WHERE job_id = ? AND employee_id = ?');
        $q->execute([$jobId, $empId]);
        $row = $q->fetch();

        $this->assertIsArray($row, 'Expected assignment row in job_employee view.');
        $this->assertSame($jobId, (int)$row['job_id']);
        $this->assertSame($empId, (int)$row['employee_id']);
    }
}
