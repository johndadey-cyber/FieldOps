<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestPdo.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class AuditLogTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();

        $this->pdo->exec("INSERT INTO persons (first_name,last_name,created_at,updated_at) VALUES ('Aud','Emp',NOW(),NOW())");
        $personId = (int)$this->pdo->lastInsertId();

        $need = 1 - (int)$this->pdo->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();
        if ($need > 0) {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = OFF');
            } else {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            }

            $ins = $this->pdo->prepare(
                "INSERT INTO employees (person_id, hire_date, status, is_active, role_id, created_at, updated_at)
                 VALUES (:person_id, :hire_date, 'active', 1, NULL, :created_at, :updated_at)"
            );
            $today = date('Y-m-d');
            $now   = date('Y-m-d H:i:s');
            for ($i = 0; $i < $need; $i++) {
                $ins->execute([
                    ':person_id' => $personId,
                    ':hire_date' => $today,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
            }

            if ($driver === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $custStmt = $this->pdo->prepare(
            "INSERT INTO customers (first_name,last_name,phone,created_at) VALUES ('Aud','Test','555',:created_at)"
        );
        $custStmt->execute([':created_at' => $now]);
        $custId = (int)$this->pdo->lastInsertId();

        $jobStmt = $this->pdo->prepare(
            "INSERT INTO jobs (customer_id,description,scheduled_date,scheduled_time,status,duration_minutes,created_at,updated_at)
             VALUES (:cust_id,'Audit Job',:scheduled_date,'09:00:00','scheduled',60,:created_at,:updated_at)"
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

    public function testAuditLogCapturesAssignmentAndRbacDenial(): void
    {
        $jobId = (int)$this->pdo->query('SELECT id FROM jobs ORDER BY id DESC LIMIT 1')->fetchColumn();
        $employeeId = (int)$this->pdo->query('SELECT id FROM employees WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
        $userId = $employeeId;

        EndpointHarness::run(__DIR__ . '/../../public/api/assignments/assign.php', [
            'jobId' => $jobId,
            'employeeIds' => [$employeeId],
            'force' => false,
        ], [
            'role' => 'dispatcher',
            'user' => ['id' => $userId],
        ], 'POST', ['json' => true, 'inject_csrf' => false]);

        EndpointHarness::run(__DIR__ . '/../../public/admin/skill_list.php', [], [
            'role' => 'dispatcher',
            'user' => ['id' => $userId],
        ], 'GET', ['inject_csrf' => false]);

        $rows = $this->pdo->query('SELECT user_id, action, details, created_at FROM audit_log ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(2, $rows);
        $this->assertSame($userId, (int)$rows[0]['user_id']);
        $this->assertSame('assign', $rows[0]['action']);
        $this->assertNotEmpty($rows[0]['created_at']);
        $det = json_decode((string)$rows[0]['details'], true);
        $this->assertSame($jobId, (int)($det['job_id'] ?? 0));

        $this->assertSame($userId, (int)$rows[1]['user_id']);
        $this->assertSame('rbac_denied', $rows[1]['action']);
        $this->assertNotEmpty($rows[1]['created_at']);
    }
}
