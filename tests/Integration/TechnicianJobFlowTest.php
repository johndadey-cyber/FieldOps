<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../support/TestDataFactory.php';

final class TechnicianJobFlowTest extends TestCase
{
    private PDO $pdo;
    private int $jobId;
    private int $techId;
    private int $checklistId;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('DELETE FROM job_completion');
        $this->pdo->exec('DELETE FROM job_checklist_items');
        $this->pdo->exec('DELETE FROM job_photos');
        $this->pdo->exec('DELETE FROM job_notes');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');
        $this->pdo->exec('DELETE FROM customers');

        $customerId   = TestDataFactory::createCustomer($this->pdo);
        $this->techId = TestDataFactory::createEmployee($this->pdo);
        $this->jobId = TestDataFactory::createJob(
            $this->pdo,
            $customerId,
            'Technician flow job',
            '2025-01-01',
            '09:00:00',
            60,
            'assigned',
            $this->techId
        );

        $st = $this->pdo->prepare('INSERT INTO job_checklist_items (job_id, description, is_completed) VALUES (:j,:d,0)');
        $st->execute([':j' => $this->jobId, ':d' => 'Initial task']);
        $this->checklistId = (int)$this->pdo->lastInsertId();
    }

    private function sampleImage(): string
    {
        return 'data:image/png;base64,' .
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBAJRfB9kAAAAASUVORK5CYII=';
    }

    private function sampleUpload(): array
    {
        $tmp  = tempnam(sys_get_temp_dir(), 'upl');
        $data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBAJRfB9kAAAAASUVORK5CYII=');
        file_put_contents($tmp, $data);
        return [
            'name' => 'sample.png',
            'type' => 'image/png',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
    }

    public function testTechnicianJobFlow(): void
    {
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
        $startedAt = $this->pdo->query('SELECT started_at FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertNotNull($startedAt);

        $note = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_notes_add.php',
            [
                'job_id' => $this->jobId,
                'technician_id' => $this->techId,
                'note' => 'Arrived on site',
            ],
            ['role' => 'technician']
        );
        $this->assertTrue($note['ok'] ?? false);
        $noteCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_notes WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(1, $noteCount);

        $_FILES = ['photo' => $this->sampleUpload()];
        $photo = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_photos_upload.php',
            [
                'job_id' => $this->jobId,
                'technician_id' => $this->techId,
                'label' => 'before',
            ],
            ['role' => 'technician']
        );
        unset($_FILES);
        $this->assertTrue($photo['ok'] ?? false);
        $photoCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_photos WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(1, $photoCount);

        $check = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_checklist_update.php',
            ['id' => $this->checklistId],
            ['role' => 'technician']
        );
        $this->assertTrue($check['ok'] ?? false);
        $this->assertTrue($check['is_completed'] ?? false);
        $isCompleted = (int)$this->pdo->query('SELECT is_completed FROM job_checklist_items WHERE id=' . $this->checklistId)->fetchColumn();
        $this->assertSame(1, $isCompleted);

        $img = $this->sampleImage();
        $complete = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_complete.php',
            [
                'job_id' => $this->jobId,
                'technician_id' => $this->techId,
                'location_lat' => '1',
                'location_lng' => '2',
                'final_note' => 'All done',
                'final_photos' => [$img],
                'signature' => $img,
            ],
            ['role' => 'technician']
        );
        $this->assertTrue($complete['ok'] ?? false);
        $this->assertSame('completed', $complete['status'] ?? null);

        $row = $this->pdo->query('SELECT status, started_at, completed_at FROM jobs WHERE id=' . $this->jobId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('completed', $row['status']);
        $this->assertNotNull($row['started_at']);
        $this->assertNotNull($row['completed_at']);

        $noteCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_notes WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(2, $noteCount);
        $finalCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_notes WHERE job_id=' . $this->jobId . ' AND is_final=1')->fetchColumn();
        $this->assertSame(1, $finalCount);

        $photoCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_photos WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(2, $photoCount);

        $completedChecks = (int)$this->pdo->query('SELECT COUNT(*) FROM job_checklist_items WHERE job_id=' . $this->jobId . ' AND is_completed=1')->fetchColumn();
        $this->assertSame(1, $completedChecks);
    }
}
