<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/Http.php';
require_once __DIR__ . '/../support/TestDataFactory.php';

#[Group('assignments')]
final class AssignmentsApiNegativeTest extends TestCase
{
    private PDO $pdo;
    private int $jobId;
    private int $employeeId;

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

        // Clean DB for isolation
        $this->pdo->exec('DELETE FROM job_employee_assignment');
        $this->pdo->exec('DELETE FROM employee_availability');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');
        $this->pdo->exec('DELETE FROM customers');

        // Seed minimal fixtures (positional args per factory signature)
        $customerId = TestDataFactory::createCustomer($this->pdo, 'Test', 'Customer');

        $date = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
        $this->jobId = TestDataFactory::createJob(
            $this->pdo,
            $customerId,
            'Test job (assignments negative)',
            $date,
            '10:00:00',
            60,
            'Unassigned'
        );

        $this->employeeId = TestDataFactory::createEmployee($this->pdo, 'Casey', 'Tester');
    }

    /** Call the assignments API using a relative path so Http helper builds the URL. */
    private function api(string $action, array $params): array
    {
        $payload = array_merge(['action' => $action], $params);
        return Http::postFormJson('assignment_process.php', $payload);
    }

    /** Fetch CSRF token from guarded test endpoint (requires APP_ENV=test). */
    private function getCsrfToken(): string
    {
        $json = @json_decode(Http::get('test_csrf.php'), true);
        return (string)($json['token'] ?? '');
    }

    public function testAssignRejectsMissingCsrf(): void
    {
        $resp = $this->api('assign', [
            'job_id'      => $this->jobId,
            'employee_id' => $this->employeeId,
            'csrf_token'  => '', // intentionally missing
        ]);

        $this->assertFalse($resp['ok'] ?? true, 'Expected ok=false for missing CSRF.');
        $this->assertArrayHasKey('error', $resp);
    }

    public function testAssignRejectsBadToken(): void
    {
        $resp = $this->api('assign', [
            'job_id'      => $this->jobId,
            'employee_id' => $this->employeeId,
            'csrf_token'  => 'not-a-real-token',
        ]);

        $this->assertFalse($resp['ok'] ?? true, 'Expected ok=false for invalid CSRF.');
        $this->assertArrayHasKey('error', $resp);
    }

    public function testNonExistentJobOrEmployee(): void
    {
        $token = $this->getCsrfToken();
        $this->assertNotSame('', $token, 'CSRF token was not retrieved; ensure APP_ENV=test.');

        // Non-existent job
        $resp1 = $this->api('assign', [
            'job_id'      => 999999,
            'employee_id' => $this->employeeId,
            'csrf_token'  => $token,
        ]);
        $this->assertFalse($resp1['ok'] ?? true);
        $this->assertArrayHasKey('error', $resp1);

        // Non-existent employee
        $resp2 = $this->api('assign', [
            'job_id'      => $this->jobId,
            'employee_id' => 999999,
            'csrf_token'  => $token,
        ]);
        $this->assertFalse($resp2['ok'] ?? true);
        $this->assertArrayHasKey('error', $resp2);
    }

    public function testIdempotentReassignReturnsChangedZero(): void
    {
        $token = $this->getCsrfToken();
        $this->assertNotSame('', $token, 'CSRF token was not retrieved; ensure APP_ENV=test.');

        // First assign
        $first = $this->api('assign', [
            'job_id'      => $this->jobId,
            'employee_id' => $this->employeeId,
            'csrf_token'  => $token,
        ]);
        $this->assertTrue($first['ok'] ?? false);
        $this->assertSame(1, (int)($first['changed'] ?? -1));

        // Second assign should be idempotent (no change)
        $second = $this->api('assign', [
            'job_id'      => $this->jobId,
            'employee_id' => $this->employeeId,
            'csrf_token'  => $token,
        ]);
        $this->assertTrue($second['ok'] ?? false);
        $this->assertSame(0, (int)($second['changed'] ?? -1), 'Repeated assign should return changed=0');
    }

    public function testStatusFlipOnFirstAndLastAssignee(): void
    {
        $token = $this->getCsrfToken();
        $this->assertNotSame('', $token, 'CSRF token was not retrieved; ensure APP_ENV=test.');

        // First assign → status should flip to Assigned
        $assign = $this->api('assign', [
            'job_id'      => $this->jobId,
            'employee_id' => $this->employeeId,
            'csrf_token'  => $token,
        ]);
        $this->assertTrue($assign['ok'] ?? false);

        $stmt = $this->pdo->prepare('SELECT status FROM jobs WHERE id = ?');
        $stmt->execute([$this->jobId]);
        $this->assertSame('Assigned', (string)$stmt->fetchColumn());

        // Unassign → last assignee removed → flip back to Unassigned
        $unassign = $this->api('unassign', [
            'job_id'      => $this->jobId,
            'employee_id' => $this->employeeId,
            'csrf_token'  => $token,
        ]);
        $this->assertTrue($unassign['ok'] ?? false);

        $stmt->execute([$this->jobId]);
        $this->assertSame('Unassigned', (string)$stmt->fetchColumn());
    }
}
