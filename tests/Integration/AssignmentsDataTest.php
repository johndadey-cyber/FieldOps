<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AssignmentsDataTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        // Build PDO from env (phpunit.xml)
        require_once __DIR__ . '/../support/TestPdo.php';
        $this->pdo = createTestPdo();

        // Start a transaction so we leave no residue
        $this->pdo->beginTransaction();

        // Minimal fixtures (idempotent UPSERT-ish)
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->pdo->exec("
                INSERT INTO customers (id, first_name, last_name, phone)
                VALUES (9001,'Test','Customer','000-000-0000')
                ON CONFLICT (id) DO UPDATE SET
                    first_name=excluded.first_name,
                    last_name=excluded.last_name,
                    phone=excluded.phone;
            ");

            $this->pdo->exec("
                INSERT INTO jobs (id, customer_id, description, scheduled_date, scheduled_time, duration_minutes, status)
                VALUES (9101, 9001, 'Assign test job', '2030-01-01', '09:00:00', 60, 'scheduled')
                ON CONFLICT (id) DO UPDATE SET
                    customer_id=excluded.customer_id,
                    description=excluded.description,
                    scheduled_date=excluded.scheduled_date,
                    scheduled_time=excluded.scheduled_time,
                    duration_minutes=excluded.duration_minutes,
                    status=excluded.status;
            ");

            $this->pdo->exec("
                INSERT INTO people (id, first_name, last_name)
                VALUES (9201,'Case','Worker'), (9202,'Alex','Helper')
                ON CONFLICT (id) DO UPDATE SET
                    first_name=excluded.first_name,
                    last_name=excluded.last_name;
            ");

            $this->pdo->exec("
                INSERT INTO employees (id, person_id, employment_type, hire_date, status, is_active, created_at, updated_at)
                VALUES
                    (9301, 9201, 'full_time', CURRENT_DATE, 'active', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                    (9302, 9202, 'full_time', CURRENT_DATE, 'active', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT (id) DO UPDATE SET
                    person_id=excluded.person_id,
                    employment_type=excluded.employment_type,
                    hire_date=excluded.hire_date,
                    status=excluded.status,
                    is_active=excluded.is_active,
                    updated_at=excluded.updated_at;
            ");
        } else {
            // Customers
            $st = $this->pdo->prepare('UPDATE customers SET first_name = :fn, last_name = :ln, phone = :ph WHERE id = :id');
            $st->execute([':fn' => 'Test', ':ln' => 'Customer', ':ph' => '000-000-0000', ':id' => 9001]);
            if ($st->rowCount() === 0) {
                $this->pdo->prepare('INSERT INTO customers (id, first_name, last_name, phone) VALUES (:id, :fn, :ln, :ph)')
                    ->execute([':id' => 9001, ':fn' => 'Test', ':ln' => 'Customer', ':ph' => '000-000-0000']);
            }

            // Jobs
            $st = $this->pdo->prepare(
                "UPDATE jobs SET customer_id = :cid, description = :d, scheduled_date = :sd, scheduled_time = :st, duration_minutes = :dm, status = :s WHERE id = :id"
            );
            $st->execute([
                ':cid' => 9001,
                ':d' => 'Assign test job',
                ':sd' => '2030-01-01',
                ':st' => '09:00:00',
                ':dm' => 60,
                ':s' => 'scheduled',
                ':id' => 9101,
            ]);
            if ($st->rowCount() === 0) {
                $this->pdo->prepare(
                    'INSERT INTO jobs (id, customer_id, description, scheduled_date, scheduled_time, duration_minutes, status) VALUES (:id, :cid, :d, :sd, :st, :dm, :s)'
                )->execute([
                    ':id' => 9101,
                    ':cid' => 9001,
                    ':d' => 'Assign test job',
                    ':sd' => '2030-01-01',
                    ':st' => '09:00:00',
                    ':dm' => 60,
                    ':s' => 'scheduled',
                ]);
            }

            // People
            $people = [
                ['id' => 9201, 'first' => 'Case', 'last' => 'Worker'],
                ['id' => 9202, 'first' => 'Alex', 'last' => 'Helper'],
            ];
            foreach ($people as $p) {
                $st = $this->pdo->prepare('UPDATE people SET first_name = :fn, last_name = :ln WHERE id = :id');
                $st->execute([':fn' => $p['first'], ':ln' => $p['last'], ':id' => $p['id']]);
                if ($st->rowCount() === 0) {
                    $this->pdo->prepare('INSERT INTO people (id, first_name, last_name) VALUES (:id, :fn, :ln)')
                        ->execute([':id' => $p['id'], ':fn' => $p['first'], ':ln' => $p['last']]);
                }
            }

            // Employees
            $emps = [
                ['id' => 9301, 'pid' => 9201],
                ['id' => 9302, 'pid' => 9202],
            ];
            foreach ($emps as $e) {
                $st = $this->pdo->prepare(
                    "UPDATE employees SET person_id = :pid, employment_type = 'full_time', hire_date = CURRENT_DATE, status = 'active', is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
                );
                $st->execute([':pid' => $e['pid'], ':id' => $e['id']]);
                if ($st->rowCount() === 0) {
                    $this->pdo->prepare(
                        "INSERT INTO employees (id, person_id, employment_type, hire_date, status, is_active, created_at, updated_at) VALUES (:id, :pid, 'full_time', CURRENT_DATE, 'active', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
                    )->execute([':id' => $e['id'], ':pid' => $e['pid']]);
                }
            }
        }
    }

    protected function tearDown(): void
    {
        // Roll back any changes this test made
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testReplaceAssignmentsForAJob(): void
    {
        // Insert a single assignment
        $ins = $this->pdo->prepare("
            INSERT INTO job_employee_assignment (job_id, employee_id)
            VALUES (:job_id, :employee_id)
        ");
        $ins->execute([':job_id' => 9101, ':employee_id' => 9301]);

        // Assert one row exists
        $cnt = (int)$this->pdo
            ->query("SELECT COUNT(*) FROM job_employee_assignment WHERE job_id=9101")
            ->fetchColumn();
        $this->assertSame(1, $cnt, 'Should have one assignment after first insert');

        // Replace with a new set (simulate API behavior: delete then insert many)
        $this->pdo->prepare("DELETE FROM job_employee_assignment WHERE job_id = :job_id")
                  ->execute([':job_id' => 9101]);

        foreach ([9301, 9302] as $eid) {
            $ins->execute([':job_id' => 9101, ':employee_id' => $eid]);
        }

        // Assert two rows now
        $cnt2 = (int)$this->pdo
            ->query("SELECT COUNT(*) FROM job_employee_assignment WHERE job_id=9101")
            ->fetchColumn();
        $this->assertSame(2, $cnt2, 'Should have two assignments after replace');
    }

    public function testUniqueConstraintPreventsDuplicatePairs(): void
    {
        $ins = $this->pdo->prepare("
            INSERT INTO job_employee_assignment (job_id, employee_id)
            VALUES (:job_id, :employee_id)
        ");
        $ins->execute([':job_id' => 9101, ':employee_id' => 9301]);

        // Attempt duplicate insert should fail on uq_job_employee
        $this->expectException(PDOException::class);
        $ins->execute([':job_id' => 9101, ':employee_id' => 9301]);
    }

    public function testCascadeDeleteFromJobsRemovesAssignments(): void
    {
        // Seed an assignment
        $this->pdo->prepare("
            INSERT INTO job_employee_assignment (job_id, employee_id)
            VALUES (9101, 9302)
        ")->execute();

        // Delete the parent job (should cascade)
        $this->pdo->prepare("DELETE FROM jobs WHERE id = :id")
                  ->execute([':id' => 9101]);

        // The assignment should be gone
        $cnt = (int)$this->pdo
            ->query("SELECT COUNT(*) FROM job_employee_assignment WHERE job_id=9101")
            ->fetchColumn();
        $this->assertSame(0, $cnt, 'Assignments should cascade-delete when job is removed');
    }
}

