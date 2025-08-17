<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/Http.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../support/TestPdo.php';

#[Group('rbac')]
final class RbacStatusLocksTest extends TestCase
{
    private PDO $pdo;
    private string $token;
    private int $jobCompleted;
    private int $jobCancelled;
    private int $techId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = createTestPdo();

        // Clean
        $this->pdo->exec('DELETE FROM job_employee_assignment');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');
        $this->pdo->exec('DELETE FROM customers');

        // Fixtures
        $custId = TestDataFactory::createCustomer($this->pdo, 'Status', 'Locks');
        $this->techId = TestDataFactory::createEmployee($this->pdo, 'Tess', 'Tech');
        $date = (new DateTimeImmutable('+1 day'))->format('Y-m-d');

        $this->jobCompleted = TestDataFactory::createJob($this->pdo, $custId, 'Completed job', $date, '10:00:00', 60, 'Completed');
        $this->jobCancelled = TestDataFactory::createJob($this->pdo, $custId, 'Cancelled job', $date, '11:00:00', 60, 'Cancelled');

        // CSRF
        $tok = json_decode(Http::get('test_csrf.php'), true);
        $this->token = (string)($tok['token'] ?? '');
        $this->assertNotSame('', $this->token, 'CSRF token missing — ensure APP_ENV=test.');
    }

    private function setUser(string $role, ?int $employeeId = null): void
    {
        $url = 'test_auth.php?role=' . urlencode($role);
        if ($employeeId !== null) $url .= '&employee_id=' . $employeeId . '&id=' . $employeeId;
        $res = json_decode(Http::get($url), true);
        $this->assertSame($role, (string)($res['role'] ?? ''), 'Failed to set role');
        if ($employeeId !== null) $this->assertSame($employeeId, (int)($res['employee_id'] ?? 0), 'Failed to set employee_id');
    }

    private function assign(int $jobId, int $empId): array
    {
        return Http::postFormJson('assignment_process.php', [
            'action'      => 'assign',
            'job_id'      => $jobId,
            'employee_id' => $empId,
            'csrf_token'  => $this->token,
        ]);
    }

    private function unassign(int $jobId, int $empId): array
    {
        return Http::postFormJson('assignment_process.php', [
            'action'      => 'unassign',
            'job_id'      => $jobId,
            'employee_id' => $empId,
            'csrf_token'  => $this->token,
        ]);
    }

    private function listJob(int $jobId): array
    {
        return Http::postFormJson('assignment_process.php', [
            'action' => 'list',
            'job_id' => $jobId,
        ]);
    }

    public function testDispatcherBlockedOnCompletedAndCancelled(): void
    {
        $this->setUser('dispatcher');

        $r1 = $this->assign($this->jobCompleted, $this->techId);
        $this->assertFalse($r1['ok'] ?? true);
        $this->assertStringContainsString('locked', strtolower((string)($r1['error'] ?? '')));

        $r2 = $this->unassign($this->jobCompleted, $this->techId);
        $this->assertFalse($r2['ok'] ?? true);
        $this->assertStringContainsString('locked', strtolower((string)($r2['error'] ?? '')));

        $r3 = $this->assign($this->jobCancelled, $this->techId);
        $this->assertFalse($r3['ok'] ?? true);
        $this->assertStringContainsString('locked', strtolower((string)($r3['error'] ?? '')));

        $r4 = $this->unassign($this->jobCancelled, $this->techId);
        $this->assertFalse($r4['ok'] ?? true);
        $this->assertStringContainsString('locked', strtolower((string)($r4['error'] ?? '')));
    }

    public function testFieldTechBlockedOnCompletedAndCancelled(): void
    {
        // Pre-assign nothing; role shouldn’t matter—writes are locked by status
        $this->setUser('field_tech', $this->techId);

        $r1 = $this->assign($this->jobCompleted, $this->techId);
        $this->assertFalse($r1['ok'] ?? true);

        $r2 = $this->unassign($this->jobCompleted, $this->techId);
        $this->assertFalse($r2['ok'] ?? true);

        $r3 = $this->assign($this->jobCancelled, $this->techId);
        $this->assertFalse($r3['ok'] ?? true);

        $r4 = $this->unassign($this->jobCancelled, $this->techId);
        $this->assertFalse($r4['ok'] ?? true);
    }

    public function testListStillAllowed(): void
    {
        $this->setUser('field_tech', $this->techId);
        $lr1 = $this->listJob($this->jobCompleted);
        $this->assertTrue($lr1['ok'] ?? false);

        $lr2 = $this->listJob($this->jobCancelled);
        $this->assertTrue($lr2['ok'] ?? false);
    }
}
