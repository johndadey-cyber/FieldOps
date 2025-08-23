<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../support/TestDataFactory.php';

final class JobStartAssignmentFallbackTest extends TestCase
{
    private PDO $pdo;
    private int $jobId;
    private int $techId;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();

        $customerId = TestDataFactory::createCustomer($this->pdo);
        $this->techId = TestDataFactory::createEmployee($this->pdo);

        // Insert job with technician_id NULL but assignment via join table
        $stmt = $this->pdo->prepare("INSERT INTO jobs (customer_id, description, status, scheduled_date, scheduled_time, duration_minutes, technician_id) VALUES (:c,:d,'assigned','2025-01-01','09:00:00',60,NULL)");
        $stmt->execute([':c' => $customerId, ':d' => 'Fallback job']);
        $this->jobId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO job_employee_assignment (job_id, employee_id) VALUES (:j,:e)')
            ->execute([':j' => $this->jobId, ':e' => $this->techId]);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    public function testFallbackToAssignmentTableWhenTechnicianIdColumnEmpty(): void
    {
        $res = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_start.php',
            [
                'job_id' => $this->jobId,
                'location_lat' => '1',
                'location_lng' => '2',
            ],
            ['role' => 'technician', 'user' => ['id' => $this->techId]]
        );

        $this->assertTrue($res['ok'] ?? false);
        $this->assertSame('in_progress', $res['status'] ?? null);
    }
}
