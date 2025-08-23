<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestPdo.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class PtoBlockAssignmentTest extends TestCase
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

    public function testRejectsAssignmentWhenEmployeeOnPto(): void
    {
        $customerId = TestDataFactory::createCustomer($this->pdo, 'Pto', 'Customer');
        $employeeId = TestDataFactory::createEmployee($this->pdo, 'Pat', 'Pto');
        $date = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
        $dow = (int)date('w', strtotime($date)) + 1; // 1=Sun..7=Sat
        TestDataFactory::setAvailability($this->pdo, $employeeId, $dow, '09:00:00', '17:00:00');
        TestDataFactory::createOverride($this->pdo, $employeeId, $date, 'UNAVAILABLE', null, null, 'PTO', 'Vacation');

        $jobId = TestDataFactory::createJob($this->pdo, $customerId, 'PTO test job', $date, '10:00:00', 60, 'scheduled');

        $res = EndpointHarness::run($this->api, [
            'jobId' => $jobId,
            'employeeIds' => [$employeeId],
        ], [], 'POST', ['json' => true]);

        $this->assertFalse($res['ok'] ?? true, 'assignment should be rejected');
        $this->assertSame(409, $res['code'] ?? 0);
        $issues = $res['details'][0]['issues'] ?? [];
        $this->assertArrayHasKey('not_available', $issues);
    }
}
