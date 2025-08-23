<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../../models/JobChecklistItem.php';

final class TechnicianOfflineSyncTest extends TestCase
{
    private PDO $pdo;
    private int $jobId;
    private int $techId;
    /** @var list<array{id:int,description:string}> */
    private array $checklistItems = [];

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();

        $customerId   = TestDataFactory::createCustomer($this->pdo);
        $this->techId = TestDataFactory::createEmployee($this->pdo);

        // Schedule slightly in the past so it can be started immediately
        $this->jobId = TestDataFactory::createJob(
            $this->pdo,
            $customerId,
            'Offline sync job',
            date('Y-m-d'),
            date('H:i:s', time() - 300),
            60,
            'assigned',
            $this->techId
        );

        JobChecklistItem::seedDefaults($this->pdo, $this->jobId, 1);
        $this->checklistItems = array_map(
            static fn(array $r): array => ['id' => $r['id'], 'description' => $r['description']],
            JobChecklistItem::listForJob($this->pdo, $this->jobId)
        );
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    private function replayQueue(array $queue): void
    {
        foreach ($queue as $payload) {
            $res = EndpointHarness::run(
                __DIR__ . '/../../public/api/job_checklist_update.php',
                [
                    'job_id' => $payload['job_id'],
                    'items' => json_encode($payload['items']),
                ],
                ['role' => 'technician']
            );
            $this->assertTrue($res['ok'] ?? false);
        }
    }

    public function testOfflineChecklistSyncIsIdempotent(): void
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

        $queue = [];
        $queue[] = [
            'job_id' => $this->jobId,
            'items' => [
                ['id' => $this->checklistItems[0]['id'], 'completed' => true],
            ],
        ];
        $queue[] = [
            'job_id' => $this->jobId,
            'items' => [
                ['id' => $this->checklistItems[1]['id'], 'completed' => true],
            ],
        ];
        $queue[] = [
            'job_id' => $this->jobId,
            'items' => [
                ['id' => $this->checklistItems[1]['id'], 'completed' => false],
            ],
        ];

        $this->replayQueue($queue);

        $state1 = [];
        foreach ($this->checklistItems as $i => $item) {
            $state1[$i] = (int)$this->pdo->query('SELECT is_completed FROM job_checklist_items WHERE id=' . $item['id'])->fetchColumn();
        }
        $this->assertSame(1, $state1[0]);
        $this->assertSame(0, $state1[1]);

        $this->replayQueue($queue);

        $state2 = [];
        foreach ($this->checklistItems as $i => $item) {
            $state2[$i] = (int)$this->pdo->query('SELECT is_completed FROM job_checklist_items WHERE id=' . $item['id'])->fetchColumn();
        }
        $this->assertSame($state1, $state2);

        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('in_progress', $status);
    }
}
