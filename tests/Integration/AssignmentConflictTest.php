<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestPdo.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class AssignmentConflictTest extends TestCase
{
    private PDO $pdo;
    private string $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = createTestPdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();

        $this->api = __DIR__ . '/../../public/api/assignments/assign.php';
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    public function testRejectsOverlappingAssignments(): void
    {
        $customerId = TestDataFactory::createCustomer($this->pdo);
        $employeeId = TestDataFactory::createEmployee($this->pdo, 'Olivia', 'Overlap');
        $date = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $dow = (int)date('w', strtotime($date)) + 1; // 1=Sun..7=Sat
        TestDataFactory::setAvailability($this->pdo, $employeeId, $dow, '08:00:00', '17:00:00');

        $job1 = TestDataFactory::createJob($this->pdo, $customerId, 'Morning job', $date, '09:00:00', 60);
        $job2 = TestDataFactory::createJob($this->pdo, $customerId, 'Overlap job', $date, '09:30:00', 60);

        $this->pdo->commit();

        $res1 = EndpointHarness::run($this->api, [
            'jobId' => $job1,
            'employeeIds' => [$employeeId],
        ], [], 'POST', ['json' => true]);
        $this->assertTrue($res1['ok'] ?? false, 'initial assignment should succeed');

        $res2 = EndpointHarness::run($this->api, [
            'jobId' => $job2,
            'employeeIds' => [$employeeId],
        ], [], 'POST', ['json' => true]);
        $this->assertFalse($res2['ok'] ?? true, 'overlapping job should be rejected');
        $this->assertSame(409, $res2['code'] ?? 0);
        $issues = $res2['details'][0]['issues'] ?? [];
        $this->assertArrayHasKey('time_conflict', $issues);
    }

    public function testRejectsOverlappingAssignmentsAcrossMidnight(): void
    {
        $customerId = TestDataFactory::createCustomer($this->pdo);
        $employeeId = TestDataFactory::createEmployee($this->pdo, 'Nia', 'Night');
        $startDate = new DateTimeImmutable('tomorrow');
        $nextDate = $startDate->modify('+1 day');
        $date1 = $startDate->format('Y-m-d');
        $date2 = $nextDate->format('Y-m-d');
        $dow1 = (int)$startDate->format('w') + 1;
        $dow2 = (int)$nextDate->format('w') + 1;
        TestDataFactory::setAvailability($this->pdo, $employeeId, $dow1, '00:00:00', '23:59:59');
        TestDataFactory::setAvailability($this->pdo, $employeeId, $dow2, '00:00:00', '23:59:59');

        $job1 = TestDataFactory::createJob($this->pdo, $customerId, 'Late shift', $date1, '23:00:00', 120);
        $job2 = TestDataFactory::createJob($this->pdo, $customerId, 'Early shift', $date2, '00:30:00', 60);

        $this->pdo->commit();

        $res1 = EndpointHarness::run($this->api, [
            'jobId' => $job1,
            'employeeIds' => [$employeeId],
        ], [], 'POST', ['json' => true]);
        $this->assertTrue($res1['ok'] ?? false, 'initial assignment should succeed');

        $res2 = EndpointHarness::run($this->api, [
            'jobId' => $job2,
            'employeeIds' => [$employeeId],
        ], [], 'POST', ['json' => true]);
        $this->assertFalse($res2['ok'] ?? true, 'overlapping job across midnight should be rejected');
        $this->assertSame(409, $res2['code'] ?? 0);
        $issues = $res2['details'][0]['issues'] ?? [];
        $this->assertArrayHasKey('time_conflict', $issues);
    }

    public function testRejectsInvalidTimeRange(): void
    {
        $customerId = TestDataFactory::createCustomer($this->pdo);
        $employeeId = TestDataFactory::createEmployee($this->pdo, 'Ivan', 'Invalid');
        $date = (new DateTimeImmutable('+2 days'))->format('Y-m-d');
        $job = TestDataFactory::createJob($this->pdo, $customerId, 'Invalid time job', $date, '10:00:00', 60);

        $this->pdo->commit();

        $res = EndpointHarness::run($this->api, [
            'jobId' => $job,
            'employeeIds' => [$employeeId],
            'start' => '12:00:00',
            'end' => '11:00:00',
        ], [], 'POST', ['json' => true]);

        $this->assertFalse($res['ok'] ?? true);
        $this->assertGreaterThanOrEqual(400, (int)($res['code'] ?? 0));
    }

    public function testRejectsSoftDeletedEmployee(): void
    {
        $customerId = TestDataFactory::createCustomer($this->pdo);
        $employeeId = TestDataFactory::createEmployee($this->pdo, 'Sofie', 'Soft');
        $this->pdo->prepare('UPDATE employees SET is_active = 0 WHERE id = ?')->execute([$employeeId]);
        $date = (new DateTimeImmutable('+3 days'))->format('Y-m-d');
        $job = TestDataFactory::createJob($this->pdo, $customerId, 'Soft-deleted test job', $date, '09:00:00', 60);

        $this->pdo->commit();

        $res = EndpointHarness::run($this->api, [
            'jobId' => $job,
            'employeeIds' => [$employeeId],
        ], [], 'POST', ['json' => true]);

        $this->assertFalse($res['ok'] ?? true);
        $this->assertGreaterThanOrEqual(400, (int)($res['code'] ?? 0));
    }

    public function testRejectsAssignmentOutsideAvailability(): void
    {
        $customerId = TestDataFactory::createCustomer($this->pdo);
        $employeeId = TestDataFactory::createEmployee($this->pdo, 'Ava', 'Absent');
        $date = (new DateTimeImmutable('+4 days'))->format('Y-m-d');
        $dow = (int)date('w', strtotime($date)) + 1;
        TestDataFactory::setAvailability($this->pdo, $employeeId, $dow, '08:00:00', '09:00:00');
        $job = TestDataFactory::createJob($this->pdo, $customerId, 'Outside availability job', $date, '10:00:00', 60);

        $this->pdo->commit();

        $res = EndpointHarness::run($this->api, [
            'jobId' => $job,
            'employeeIds' => [$employeeId],
        ], [], 'POST', ['json' => true]);

        $this->assertFalse($res['ok'] ?? true);
        $this->assertSame(409, $res['code'] ?? 0);
        $issues = $res['details'][0]['issues'] ?? [];
        $this->assertArrayHasKey('unavailable_for_job_window', $issues);
    }
}
