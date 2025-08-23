<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../../models/JobChecklistItem.php';

final class JobChecklistSeedDefaultsTest extends TestCase
{
    private PDO $pdo;
    private int $jobTypeId = 1234;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();

        $this->pdo->prepare('INSERT INTO job_types (id, name) VALUES (:id, :name)')
            ->execute([':id' => $this->jobTypeId, ':name' => 'Seed Defaults Type']);
        $insTpl = $this->pdo->prepare('INSERT INTO checklist_templates (job_type_id, description, position) VALUES (:jt,:d,:p)');
        $insTpl->execute([':jt' => $this->jobTypeId, ':d' => 'DB Item 1', ':p' => 1]);
        $insTpl->execute([':jt' => $this->jobTypeId, ':d' => 'DB Item 2', ':p' => 2]);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    public function testSeedDefaultsFromDatabaseOnJobCreate(): void
    {
        $customerId = TestDataFactory::createCustomer($this->pdo);

        $res = EndpointHarness::run(
            __DIR__ . '/../../public/job_save.php',
            [
                'customer_id'    => $customerId,
                'description'    => 'Job with defaults',
                'scheduled_date' => '2025-02-01',
                'scheduled_time' => '10:00',
                'status'         => 'scheduled',
                'job_type_id'    => $this->jobTypeId,
                'skills'         => [1],
            ],
            ['role' => 'dispatcher']
        );

        $this->assertTrue($res['ok'] ?? false, 'Job save failed');
        $jobId = (int)($res['id'] ?? 0);
        $items = JobChecklistItem::listForJob($this->pdo, $jobId);
        $this->assertCount(2, $items);
        $this->assertSame('DB Item 1', $items[0]['description']);
        $this->assertSame('DB Item 2', $items[1]['description']);
    }
}
