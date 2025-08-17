<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/Http.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../support/TestPdo.php';

#[Group('rbac')]
final class RbacAssignmentsApiV2Test extends TestCase
{
    private PDO $pdo;
    private string $token = '';
    private int $jobId;
    private int $techId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = createTestPdo();

        $this->pdo->exec('DELETE FROM job_employee_assignment');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');
        $this->pdo->exec('DELETE FROM customers');

        $custId       = TestDataFactory::createCustomer($this->pdo, 'RBAC', 'Customer');
        $this->techId = TestDataFactory::createEmployee($this->pdo, 'Felix', 'FieldTech');
        $date         = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
        $this->jobId  = TestDataFactory::createJob($this->pdo, $custId, 'RBAC v2 job', $date, '10:00:00', 60, 'scheduled');

        // CSRF (APP_ENV=test) — fetch via test_csrf.php
        $tok         = json_decode(Http::get('test_csrf.php'), true);
        $this->token = (string)($tok['token'] ?? '');
        $this->assertNotSame('', $this->token, 'CSRF token missing — ensure APP_ENV=test and test_csrf.php exists.');
    }

    /** Helper: set authenticated user via test-only endpoint */
    private function setUser(string $role, ?int $userId = null): void
    {
        $path = 'test_auth.php?role=' . urlencode($role);
        if ($userId !== null) {
            // seed both keys; test_auth.php will mirror to session
            $path .= '&employee_id=' . $userId . '&id=' . $userId;
        }
        $res = json_decode(Http::get($path), true);
        $this->assertSame($role, (string)($res['role'] ?? ''), 'Failed to set role');
        if ($userId !== null) {
            $this->assertSame($userId, (int)($res['employee_id'] ?? 0), 'Failed to set employee_id');
        }
    }

    /** Helper: flatten arrays as HTML forms do (employee_ids[0], employee_ids[1], …) */
    private function postFormWithArrays(string $path, array $payload): array
    {
        // Convert "employee_ids" => [1,2] to "employee_ids[0]" => 1, "employee_ids[1]" => 2
        $flat = [];
        foreach ($payload as $k => $v) {
            if ($k === 'employee_ids' && is_array($v)) {
                $i = 0;
                foreach ($v as $val) {
                    $flat["employee_ids[$i]"] = (string)$val;
                    $i++;
                }
            } else {
                $flat[$k] = $v;
            }
        }
        return Http::postFormJson($path, $flat);
    }

    public function testDispatcherCanAssignAndUnassign(): void
    {
        $this->setUser('dispatcher');

        // ASSIGN (use csrf_token and explicit array flattening)
        $assign = $this->postFormWithArrays('assignment_process.php', [
            'action'        => 'assign',
            'job_id'        => $this->jobId,
            'employee_ids'  => [$this->techId],
            'csrf_token'    => $this->token,
        ]);
        $this->assertTrue($assign['ok'] ?? false, 'Dispatcher should be able to assign. Error: ' . ($assign['error'] ?? 'n/a'));

        // UNASSIGN (single id)
        $unassign = Http::postFormJson('assignment_process.php', [
            'action'       => 'unassign',
            'job_id'       => $this->jobId,
            'employee_id'  => $this->techId,
            'csrf_token'   => $this->token,
        ]);
        $this->assertTrue($unassign['ok'] ?? false, 'Dispatcher should be able to unassign. Error: ' . ($unassign['error'] ?? 'n/a'));
    }

    public function testFieldTechCannotAssignButCanUnassignSelf(): void
    {
        // Pre-assign via dispatcher
        $this->setUser('dispatcher');
        $this->assertTrue(
            ($this->postFormWithArrays('assignment_process.php', [
                'action'       => 'assign',
                'job_id'       => $this->jobId,
                'employee_ids' => [$this->techId],
                'csrf_token'   => $this->token,
            ])['ok'] ?? false),
            'Pre-assign failed'
        );

        // Field tech cannot ASSIGN
        $this->setUser('field_tech', $this->techId);
        $assign = $this->postFormWithArrays('assignment_process.php', [
            'action'       => 'assign',
            'job_id'       => $this->jobId,
            'employee_ids' => [$this->techId],
            'csrf_token'   => $this->token,
        ]);
        $this->assertFalse($assign['ok'] ?? true, 'Field tech should NOT be able to assign.');

        // Field tech CAN UNASSIGN self
        $unassign = Http::postFormJson('assignment_process.php', [
            'action'       => 'unassign',
            'job_id'       => $this->jobId,
            'employee_id'  => $this->techId,
            'csrf_token'   => $this->token,
        ]);
        $this->assertTrue($unassign['ok'] ?? false, 'Field tech should be able to unassign themselves. Error: ' . ($unassign['error'] ?? 'n/a'));
    }

    public function testFieldTechCannotUnassignAnotherEmployee(): void
    {
        // Seed two techs and assign both
        $tech2 = TestDataFactory::createEmployee($this->pdo, 'Nora', 'Neighbor');

        $this->setUser('dispatcher');
        $this->assertTrue(
            ($this->postFormWithArrays('assignment_process.php', [
                'action'       => 'assign',
                'job_id'       => $this->jobId,
                'employee_ids' => [$this->techId, $tech2],
                'csrf_token'   => $this->token,
            ])['ok'] ?? false),
            'Pre-assign failed'
        );

        // Field tech tries to unassign the other tech -> forbidden
        $this->setUser('field_tech', $this->techId);
        $res = Http::postFormJson('assignment_process.php', [
            'action'       => 'unassign',
            'job_id'       => $this->jobId,
            'employee_id'  => $tech2,
            'csrf_token'   => $this->token,
        ]);
        $this->assertFalse($res['ok'] ?? true, 'Expected forbidden when unassigning another employee.');
    }
}
