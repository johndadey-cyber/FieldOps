<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * AssignmentsTest
 * - Happy path: assign then unassign
 * - Overlap rejection: second conflicting assignment is prevented
 *
 * Prereqs:
 * - Dev server running (e.g. `php -S 127.0.0.1:8010 -t public`)
 * - FIELDOPS_BASE_URL set if not using 127.0.0.1:8010
 * - public/test_csrf.php present (the helper uses it to seed CSRF/session)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/support/Http.php';
require_once __DIR__ . '/support/TestDataFactory.php';

final class AssignmentsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** @group assignments */
    public function testSingleAssignAndUnassignHappyPath(): void
    {
        // Arrange
        $customerId = TestDataFactory::createCustomer($this->pdo);
        $employeeId = TestDataFactory::createEmployee($this->pdo);
        $date       = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $dow        = (int)date('w', strtotime($date));

        TestDataFactory::setAvailability($this->pdo, $employeeId, $dow, '09:00:00', '12:00:00');
        $jobId = TestDataFactory::createJob($this->pdo, $customerId, 'Assign happy path', $date, '10:00:00', 60, 'Unassigned');

        // 🔴 Important: the HTTP endpoint runs in another connection; commit fixtures so it can see them.
        $this->pdo->commit();

        $token = Http::fetchCsrfTokenFrom();

        // Act: Assign
        $resp = Http::postFormJson('assignment_process.php', [
            'csrf_token'  => $token,
            'action'      => 'assign',
            'job_id'      => $jobId,
            'employee_id' => $employeeId,
        ]);

        // Assert
        $this->assertTrue(($resp['ok'] ?? false) === true, 'Assign ok: ' . json_encode($resp, JSON_UNESCAPED_SLASHES));
        $this->assertTrue(TestDataFactory::hasAssignment($this->pdo, $jobId, $employeeId), 'Assignment row exists after assign');

        // Act: Unassign
        $resp2 = Http::postFormJson('assignment_process.php', [
            'csrf_token'  => $token,
            'action'      => 'unassign',
            'job_id'      => $jobId,
            'employee_id' => $employeeId,
        ]);

        // Assert
        $this->assertTrue(($resp2['ok'] ?? false) === true, 'Unassign ok: ' . json_encode($resp2, JSON_UNESCAPED_SLASHES));
        $this->assertFalse(TestDataFactory::hasAssignment($this->pdo, $jobId, $employeeId), 'Assignment removed after unassign');
    }

    /** @group assignments */
    public function testConflictAtCommitTimeIsRejected(): void
    {
        // Arrange: employee available 09–12 on chosen date
        $customerId = TestDataFactory::createCustomer($this->pdo, first: 'Case', last: 'Conflict');
        $employeeId = TestDataFactory::createEmployee($this->pdo, 'Casey', 'Conflict');
        $date       = (new DateTimeImmutable('next monday'))->format('Y-m-d');
        $dow        = (int)date('w', strtotime($date));

        TestDataFactory::setAvailability($this->pdo, $employeeId, $dow, '09:00:00', '12:00:00');

        // First job: 10:00–11:00 (should succeed)
        $jobA  = TestDataFactory::createJob($this->pdo, $customerId, 'Job A', $date, '10:00:00', 60, 'Unassigned');

        // Make fixtures visible to the endpoint
        $this->pdo->commit();

        $token = Http::fetchCsrfTokenFrom();

        $first = Http::postFormJson('assignment_process.php', [
            'csrf_token'  => $token,
            'action'      => 'assign',
            'job_id'      => $jobA,
            'employee_id' => $employeeId,
        ]);
        $this->assertTrue(($first['ok'] ?? false) === true, 'First assign ok: ' . json_encode($first, JSON_UNESCAPED_SLASHES));
        $this->assertTrue(TestDataFactory::hasAssignment($this->pdo, $jobA, $employeeId), 'Row exists for first assignment');

        // Second job: 10:30–11:30 (overlaps -> should NOT persist)
        // Start a new transaction for creating B, then commit before HTTP again.
        $this->pdo->beginTransaction();
        $jobB = TestDataFactory::createJob($this->pdo, $customerId, 'Job B', $date, '10:30:00', 60, 'Unassigned');
        $this->pdo->commit();

        $second = Http::postFormJson('assignment_process.php', [
            'csrf_token'  => $token,
            'action'      => 'assign',
            'job_id'      => $jobB,
            'employee_id' => $employeeId,
        ]);

        // Strict DB assertion: no assignment row for B
        $this->assertFalse(
            TestDataFactory::hasAssignment($this->pdo, $jobB, $employeeId),
            'Overlap prevented; response=' . json_encode($second, JSON_UNESCAPED_SLASHES)
        );

        // Lenient API assertion: either ok=false OR ok=true with changed=0
        if (isset($second['ok'])) {
            $this->assertTrue(
                $second['ok'] === false || ($second['ok'] === true && (int)($second['changed'] ?? 0) === 0),
                'Expected rejection/no-change for overlap: ' . json_encode($second, JSON_UNESCAPED_SLASHES)
            );
        }
    }
}
