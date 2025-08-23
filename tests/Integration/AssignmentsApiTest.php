<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestPdo.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class AssignmentsApiTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();

        // Ensure we have at least 3 employees (self-seed if not)
        $need = 3 - (int)$this->pdo->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();
        if ($need > 0) {
            // Test-only: allow inserting employees without a real person row
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = OFF');
            } else {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            }

            $ins = $this->pdo->prepare(
                "INSERT INTO employees (person_id, hire_date, status, is_active, role_id, created_at, updated_at)
                 VALUES (0, :hire_date, 'active', 1, NULL, :created_at, :updated_at)"
            );
            $today = date('Y-m-d');
            $now   = date('Y-m-d H:i:s');
            for ($i = 0; $i < $need; $i++) {
                $ins->execute([':hire_date' => $today, ':created_at' => $now, ':updated_at' => $now]);
            }

            if ($driver === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        // Seed a customer + job
        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        $custStmt = $this->pdo->prepare(
            "INSERT INTO customers (first_name,last_name,phone,created_at) VALUES ('Assign','Tester','555-2000',:created_at)"
        );
        $custStmt->execute([':created_at' => $now]);
        $custId = (int)$this->pdo->lastInsertId();

        $jobStmt = $this->pdo->prepare(
            "INSERT INTO jobs (customer_id,description,scheduled_date,scheduled_time,status,duration_minutes,created_at,updated_at)
             VALUES (:cust_id,'Assignment API Job',:scheduled_date,'08:30:00','scheduled',60,:created_at,:updated_at)"
        );
        $jobStmt->execute([
            ':cust_id'       => $custId,
            ':scheduled_date'=> $today,
            ':created_at'    => $now,
            ':updated_at'    => $now,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    /** @return int[] */
    private function pickEmployeeIds(int $n = 2): array
    {
        $stmt = $this->pdo->query("SELECT id FROM employees WHERE is_active = 1 ORDER BY id ASC LIMIT " . (int)$n);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testAssignEmployeesSuccess(): void
    {
        $jobId  = (int)$this->pdo->query("SELECT id FROM jobs ORDER BY id DESC LIMIT 1")->fetchColumn();
        $empIds = $this->pickEmployeeIds(2);

        $res = EndpointHarness::run(__DIR__ . '/../../public/assignment_process.php', [
            'action'       => 'assign',
            'job_id'       => $jobId,
            'employee_ids' => $empIds,
            'replace'      => 1,
        ], [
            'role' => 'dispatcher',
        ]);

        $this->assertTrue($res['ok'] ?? false, 'assign should succeed');
        $this->assertSame('assigned', $res['action'] ?? null);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM job_employee_assignment WHERE job_id = {$jobId}")->fetchColumn();
        $this->assertSame(2, $count, 'two employees should be assigned');
    }

    public function testAssignEmployeesInvalidJobId(): void
    {
        $empIds = $this->pickEmployeeIds(1);

        $res = EndpointHarness::run(__DIR__ . '/../../public/assignment_process.php', [
            'action'       => 'assign',
            'job_id'       => 999999, // unlikely
            'employee_ids' => $empIds,
            'replace'      => 1,
        ], [
            'role' => 'dispatcher',
        ]);

        $this->assertFalse($res['ok'] ?? true);
        $this->assertGreaterThanOrEqual(400, (int)($res['code'] ?? 0));
    }

    public function testReplaceAssignmentsForAJob(): void
    {
        $jobId = (int)$this->pdo->query("SELECT id FROM jobs ORDER BY id DESC LIMIT 1")->fetchColumn();

        $all  = $this->pickEmployeeIds(3);
        $firstTwo = array_slice($all, 0, 2);
        $lastOne  = [$all[2]];

        // initial two
        $res1 = EndpointHarness::run(__DIR__ . '/../../public/assignment_process.php', [
            'action'       => 'assign',
            'job_id'       => $jobId,
            'employee_ids' => $firstTwo,
            'replace'      => 1,
        ], ['role' => 'dispatcher']);
        $this->assertTrue($res1['ok'] ?? false);

        // replace with one
        $res2 = EndpointHarness::run(__DIR__ . '/../../public/assignment_process.php', [
            'action'       => 'assign',
            'job_id'       => $jobId,
            'employee_ids' => $lastOne,
            'replace'      => 1,
        ], ['role' => 'dispatcher']);
        $this->assertTrue($res2['ok'] ?? false);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM job_employee_assignment WHERE job_id = {$jobId}")->fetchColumn();
        $this->assertSame(1, $count, 'replace should leave exactly one assignment');
    }
}
