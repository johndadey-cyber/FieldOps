<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AssignmentsDataTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        // Build PDO from env (phpunit.xml)
        $this->pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: '127.0.0.1',
                getenv('DB_PORT') ?: '8889',
                getenv('DB_NAME') ?: 'fieldops_test'
            ),
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: 'root',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        // Start a transaction so we leave no residue
        $this->pdo->beginTransaction();

        // Minimal fixtures (idempotent UPSERT-ish)
        $this->pdo->exec("
            INSERT INTO customers (id, first_name, last_name, phone)
            VALUES (9001,'Test','Customer','000-000-0000')
            ON DUPLICATE KEY UPDATE first_name='Test';
        ");

        $this->pdo->exec("
            INSERT INTO jobs (id, customer_id, description, scheduled_date, scheduled_time, duration_minutes, status)
            VALUES (9101, 9001, 'Assign test job', '2030-01-01', '09:00:00', 60, 'scheduled')
            ON DUPLICATE KEY UPDATE description='Assign test job';
        ");

        $this->pdo->exec("
            INSERT INTO people (id, first_name, last_name)
            VALUES (9201,'Case','Worker'), (9202,'Alex','Helper')
            ON DUPLICATE KEY UPDATE first_name=VALUES(first_name);
        ");

        $this->pdo->exec("
            INSERT INTO employees (id, person_id)
            VALUES (9301,9201), (9302,9202)
            ON DUPLICATE KEY UPDATE person_id=VALUES(person_id);
        ");
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
        // Ensure we start clean for job 9101
        $del = $this->pdo->prepare("DELETE FROM job_employee_assignment WHERE job_id = :job_id");
        $del->execute([':job_id' => 9101]);

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
        $this->pdo->prepare("DELETE FROM job_employee_assignment WHERE job_id = :job_id")
                  ->execute([':job_id' => 9101]);

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
        $this->pdo->prepare("DELETE FROM job_employee_assignment WHERE job_id = :job_id")
                  ->execute([':job_id' => 9101]);
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

