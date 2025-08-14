<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/Http.php';
require_once __DIR__ . '/../support/TestDataFactory.php';

#[Group('rbac')]
final class RbacAssignmentsTest extends TestCase
{
    private PDO $pdo;
    private string $token;
    private int $jobId;
    private int $empId;

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

        $custId = TestDataFactory::createCustomer($this->pdo, 'RBAC', 'Customer');
        $this->empId = TestDataFactory::createEmployee($this->pdo, 'Rita', 'Role');
        $date = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
        $this->jobId = TestDataFactory::createJob($this->pdo, $custId, 'RBAC job', $date, '10:00:00', 60, 'scheduled');

        $tok = json_decode(Http::get('test_csrf.php'), true);
        $this->token = (string)($tok['token'] ?? '');
        $this->assertNotSame('', $this->token, 'CSRF token missing — ensure APP_ENV=test.');
    }

    private function setRole(string $role, ?int $employeeId = null): void
    {
        $url = 'test_auth.php?role=' . urlencode($role);
        if ($employeeId !== null) $url .= '&employee_id=' . $employeeId;
        $res = json_decode(Http::get($url), true);
        $this->assertSame($role, (string)($res['role'] ?? ''), 'Failed to set role');
        if ($employeeId !== null) {
            $this->assertSame($employeeId, (int)($res['employee_id'] ?? 0), 'Failed to set employee_id');
        }
    }

    private function api(string $action, array $params): array
    {
        $payload = array_merge(['action' => $action], $params);
        return Http::postFormJson('assignment_process.php', $payload);
    }

    public function testDispatcherCanAssignAndUnassign(): void
    {
        $this->setRole('dispatcher');

        $assign = $this->api('assign', ['job_id'=>$this->jobId, 'employee_id'=>$this->empId, 'csrf_token'=>$this->token]);
        $this->assertTrue($assign['ok'] ?? false);

        $unassign = $this->api('unassign', ['job_id'=>$this->jobId, 'employee_id'=>$this->empId, 'csrf_token'=>$this->token]);
        $this->assertTrue($unassign['ok'] ?? false);
    }

    public function testFieldTechCannotAssignButCanUnassignSelf(): void
    {
        // Pre-assign by dispatcher
        $this->setRole('dispatcher');
        $this->assertTrue(($this->api('assign', ['job_id'=>$this->jobId, 'employee_id'=>$this->empId, 'csrf_token'=>$this->token])['ok'] ?? false));

        // Field tech cannot assign
        $this->setRole('field_tech', $this->empId);
        $assign = $this->api('assign', ['job_id'=>$this->jobId, 'employee_id'=>$this->empId, 'csrf_token'=>$this->token]);
        $this->assertFalse($assign['ok'] ?? true);
        $this->assertStringContainsString('forbidden', strtolower((string)($assign['error'] ?? '')));

        // Field tech CAN unassign themselves
        $unassign = $this->api('unassign', ['job_id'=>$this->jobId, 'employee_id'=>$this->empId, 'csrf_token'=>$this->token]);
        $this->assertTrue($unassign['ok'] ?? false, 'Field tech should be able to unassign themselves.');
    }

    public function testBothRolesCanList(): void
    {
        $this->setRole('dispatcher');
        $this->assertTrue(($this->api('list', ['job_id'=>$this->jobId])['ok'] ?? false));

        $this->setRole('field_tech', $this->empId);
        $this->assertTrue(($this->api('list', ['job_id'=>$this->jobId])['ok'] ?? false));
    }
}
