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
        $this->pdo->exec('DELETE FROM audit_log');
        $this->pdo->exec('DELETE FROM job_employee_assignment');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM customers');

        $need = 1 - (int)$this->pdo->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();
        if ($need > 0) {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $ins = $this->pdo->prepare("INSERT INTO employees (person_id, hire_date, status, is_active, role_id, created_at, updated_at) VALUES (0, CURDATE(), 'active', 1, NULL, NOW(), NOW())");
            for ($i = 0; $i < $need; $i++) { $ins->execute(); }
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->pdo->exec("INSERT INTO customers (first_name,last_name,phone,created_at) VALUES ('Aud','Test','555',NOW())");
        $this->pdo->exec("INSERT INTO jobs (customer_id,description,scheduled_date,scheduled_time,status,duration_minutes,created_at,updated_at) VALUES (LAST_INSERT_ID(),'Audit Job',CURDATE(),'09:00:00','scheduled',60,NOW(),NOW())");
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
