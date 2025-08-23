<?php
declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../support/TestDataFactory.php';

use PHPUnit\Framework\TestCase;

final class JobStatusTransitionTest extends TestCase
{
    private PDO $pdo;
    private int $customerId;
    private int $techId;
    private int $jobId;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('DELETE FROM job_deletion_log');
        $this->pdo->exec('DELETE FROM job_completion');
        $this->pdo->exec('DELETE FROM job_checklist_items');
        $this->pdo->exec('DELETE FROM job_photos');
        $this->pdo->exec('DELETE FROM job_notes');
        $this->pdo->exec('DELETE FROM job_employee_assignment');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');
        $this->pdo->exec('DELETE FROM customers');

        $this->customerId = TestDataFactory::createCustomer($this->pdo);
        $this->techId     = TestDataFactory::createEmployee($this->pdo);
        $this->jobId      = TestDataFactory::createJob(
            $this->pdo,
            $this->customerId,
            'Status flow job',
            date('Y-m-d'),
            date('H:i:s'),
            60,
            'draft'
        );
    }

    protected function tearDown(): void
    {
        $base = __DIR__ . '/../../public/uploads';
        foreach (["jobs", "signatures"] as $sub) {
            $dir = $base . '/' . $sub;
            if (is_dir($dir)) {
                foreach (glob($dir . '/*') ?: [] as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($dir);
            }
        }
        if (is_dir($base)) {
            rmdir($base);
        }
    }

    public function testIllegalTransitionsFail(): void
    {
        $this->pdo->exec('UPDATE jobs SET status="scheduled" WHERE id=' . $this->jobId);

        $assign = EndpointHarness::run(
            __DIR__ . '/../../public/assignment_process.php',
            [
                'action' => 'assign',
                'job_id' => $this->jobId,
                'employee_id' => $this->techId,
            ],
            ['role' => 'dispatcher']
        );
        $this->assertTrue($assign['ok'] ?? false);
        $this->assertSame('assigned', $assign['status'] ?? null);

        $img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBAJRfB9kAAAAASUVORK5CYII=';
        $complete = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_complete.php',
            [
                'job_id' => $this->jobId,
                'technician_id' => $this->techId,
                'location_lat' => '1',
                'location_lng' => '2',
                'final_note' => 'done',
                'final_photos' => [$img],
                'signature' => $img,
            ],
            ['role' => 'technician', 'user' => ['id' => $this->techId]]
        );
        $this->assertFalse($complete['ok'] ?? true);
        $this->assertSame(422, $complete['code'] ?? 0);
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('assigned', $status);

        $start = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_start.php',
            [
                'job_id' => $this->jobId,
                'location_lat' => '1',
                'location_lng' => '2',
            ],
            ['role' => 'technician', 'user' => ['id' => $this->techId]]
        );
        $this->assertTrue($start['ok'] ?? false);
        $this->assertSame('in_progress', $start['status'] ?? null);

        $delete = EndpointHarness::run(
            __DIR__ . '/../../public/job_delete.php',
            ['id' => $this->jobId],
            ['role' => 'dispatcher']
        );
        $this->assertFalse($delete['ok'] ?? true);
        $deleted = $this->pdo->query('SELECT deleted_at FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertNull($deleted);
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('in_progress', $status);
    }

    public function testValidStatusSequence(): void
    {
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('draft', $status);

        $this->pdo->exec('UPDATE jobs SET status="scheduled" WHERE id=' . $this->jobId);
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('scheduled', $status);

        $assign = EndpointHarness::run(
            __DIR__ . '/../../public/assignment_process.php',
            [
                'action' => 'assign',
                'job_id' => $this->jobId,
                'employee_id' => $this->techId,
            ],
            ['role' => 'dispatcher']
        );
        $this->assertTrue($assign['ok'] ?? false);
        $this->assertSame('assigned', $assign['status'] ?? null);

        $start = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_start.php',
            [
                'job_id' => $this->jobId,
                'location_lat' => '1',
                'location_lng' => '2',
            ],
            ['role' => 'technician', 'user' => ['id' => $this->techId]]
        );
        $this->assertTrue($start['ok'] ?? false);
        $this->assertSame('in_progress', $start['status'] ?? null);

        $img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBAJRfB9kAAAAASUVORK5CYII=';
        $complete = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_complete.php',
            [
                'job_id' => $this->jobId,
                'technician_id' => $this->techId,
                'location_lat' => '1',
                'location_lng' => '2',
                'final_note' => 'done',
                'final_photos' => [$img],
                'signature' => $img,
            ],
            ['role' => 'technician', 'user' => ['id' => $this->techId]]
        );
        $this->assertTrue($complete['ok'] ?? false);
        $this->assertSame('completed', $complete['status'] ?? null);

        $this->pdo->exec('UPDATE jobs SET status="closed" WHERE id=' . $this->jobId);
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('closed', $status);
    }
}
