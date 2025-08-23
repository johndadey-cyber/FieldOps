<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../../models/JobChecklistItem.php';

final class TechnicianJobFlowTest extends TestCase
{
    private PDO $pdo;
    private int $jobId;
    private int $techId;
    private int $futureJobId;
    /** @var list<array{id:int,description:string}> */
    private array $checklistItems = [];

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

        // Main job scheduled slightly in the past so it can be started
        $this->jobId = TestDataFactory::createJob(
            $this->pdo,
            $customerId,
            'Technician flow job',
            date('Y-m-d'),
            date('H:i:s', time() - 300),
            60,
            'assigned',
            $this->techId
        );

        // Future job used to test starting outside the scheduled window
        $this->futureJobId = TestDataFactory::createJob(
            $this->pdo,
            $customerId,
            'Future job',
            date('Y-m-d', time() + 86400),
            date('H:i:s'),
            60,
            'assigned',
            $this->techId
        );

        // Seed default checklist items so the technician has multiple tasks
        JobChecklistItem::seedDefaults($this->pdo, $this->jobId, 1);
        $this->checklistItems = array_map(
            static fn(array $r): array => ['id' => $r['id'], 'description' => $r['description']],
            JobChecklistItem::listForJob($this->pdo, $this->jobId)
        );
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
        // Attempting to start a job before its scheduled time should fail
        $early = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_start.php',
            [
                'job_id' => $this->futureJobId,
                'location_lat' => '1',
                'location_lng' => '2',
            ],
            ['role' => 'technician', 'user' => ['id' => $this->techId]]
        );
        $this->assertFalse($early['ok'] ?? true);
        $futureStatus = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->futureJobId)->fetchColumn();
        $this->assertSame('assigned', $futureStatus);

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
        $row = $this->pdo->query('SELECT status, started_at FROM jobs WHERE id=' . $this->jobId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('in_progress', $row['status']);
        $this->assertNotNull($row['started_at']);

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
        $this->assertSame('in_progress', $note['status'] ?? null);
        $noteCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_notes WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(1, $noteCount);
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('in_progress', $status);

        $f = $this->sampleUpload();
        $_FILES = ['photos' => [
            'name' => [$f['name']],
            'type' => [$f['type']],
            'tmp_name' => [$f['tmp_name']],
            'error' => [$f['error']],
            'size' => [$f['size']],
        ]];
        $photo = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_photos_upload.php',
            [
                'job_id' => $this->jobId,
                'technician_id' => $this->techId,
                'tags' => ['Before'],
            ],
            ['role' => 'technician']
        );
        unset($_FILES);
        $this->assertTrue($photo['ok'] ?? false);
        $this->assertSame('in_progress', $photo['status'] ?? null);
        $photoCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_photos WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(1, $photoCount);
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('in_progress', $status);

        // Fetch checklist items for the job
        $list = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_checklist.php',
            ['job_id' => $this->jobId],
            ['role' => 'technician']
        );
        $this->assertTrue($list['ok'] ?? false);
        $this->assertCount(count($this->checklistItems), $list['items'] ?? []);

        // Batch update two items (online)
        $batchItems = [];
        foreach (array_slice($this->checklistItems, 0, 2) as $item) {
            $batchItems[] = ['id' => $item['id'], 'completed' => true];
        }
        $batch = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_checklist_update.php',
            [
                'job_id' => $this->jobId,
                'items' => json_encode($batchItems),
            ],
            ['role' => 'technician']
        );
        $this->assertTrue($batch['ok'] ?? false);
        $this->assertSame('in_progress', $batch['status'] ?? null);
        foreach ($this->checklistItems as $i => $item) {
            $state = (int)$this->pdo->query('SELECT is_completed FROM job_checklist_items WHERE id=' . $item['id'])->fetchColumn();
            $this->assertSame($i < 2 ? 1 : 0, $state);
        }
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('in_progress', $status);

        // Simulate offline queue replay with further changes
        $replayItems = [];
        foreach ($this->checklistItems as $i => $item) {
            if ($i === 1) {
                $replayItems[] = ['id' => $item['id'], 'completed' => false];
            } elseif ($i === 2) {
                $replayItems[] = ['id' => $item['id'], 'completed' => true];
            }
        }
        $replay = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_checklist_update.php',
            [
                'job_id' => $this->jobId,
                'items' => json_encode($replayItems),
            ],
            ['role' => 'technician']
        );
        $this->assertTrue($replay['ok'] ?? false);
        $this->assertSame('in_progress', $replay['status'] ?? null);
        foreach ($this->checklistItems as $i => $item) {
            $state = (int)$this->pdo->query('SELECT is_completed FROM job_checklist_items WHERE id=' . $item['id'])->fetchColumn();
            $expected = $i === 0 || $i === 2 ? 1 : 0;
            $this->assertSame($expected, $state);
        }
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('in_progress', $status);

        // Completing with an incomplete checklist should be rejected
        $img = $this->sampleImage();
        $fail = EndpointHarness::run(
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
        $this->assertFalse($fail['ok'] ?? true);
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('in_progress', $status);
        $noteCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_notes WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(1, $noteCount);
        $photoCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_photos WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(1, $photoCount);
        $sigCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_completion WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(0, $sigCount);

        // Final sync to complete all checklist items
        $finalItems = [];
        foreach ($this->checklistItems as $item) {
            $finalItems[] = ['id' => $item['id'], 'completed' => true];
        }
        $final = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_checklist_update.php',
            [
                'job_id' => $this->jobId,
                'items' => json_encode($finalItems),
            ],
            ['role' => 'technician'],
        );
        $this->assertTrue($final['ok'] ?? false);
        $this->assertSame('in_progress', $final['status'] ?? null);
        foreach ($this->checklistItems as $item) {
            $state = (int)$this->pdo->query('SELECT is_completed FROM job_checklist_items WHERE id=' . $item['id'])->fetchColumn();
            $this->assertSame(1, $state);
        }
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('in_progress', $status);

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
        $completionCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_completion WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(1, $completionCount);

        foreach ($this->checklistItems as $item) {
            $state = (int)$this->pdo->query('SELECT is_completed FROM job_checklist_items WHERE id=' . $item['id'])->fetchColumn();
            $this->assertSame(1, $state);
        }

        $completedChecks = (int)$this->pdo->query('SELECT COUNT(*) FROM job_checklist_items WHERE job_id=' . $this->jobId . ' AND is_completed=1')->fetchColumn();
        $this->assertSame(count($this->checklistItems), $completedChecks);
    }
}
