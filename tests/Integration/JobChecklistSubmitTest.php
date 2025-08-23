<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../../models/JobChecklistItem.php';

final class JobChecklistSubmitTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    public function testChecklistItemsPersistOnJobSave(): void
    {
        $customerId = TestDataFactory::createCustomer($this->pdo);

        $res = EndpointHarness::run(
            __DIR__ . '/../../public/job_save.php',
            [
                'customer_id'    => $customerId,
                'description'    => 'Job with checklist',
                'scheduled_date' => '2025-04-01',
                'scheduled_time' => '09:00',
                'status'         => 'scheduled',
                'skills'         => [1],
                'checklist_items'=> ['First', 'Second'],
            ],
            ['role' => 'dispatcher']
        );

        $this->assertTrue($res['ok'] ?? false, 'Job save failed');
        $jobId = (int)($res['id'] ?? 0);
        $items = JobChecklistItem::listForJob($this->pdo, $jobId);
        $this->assertCount(2, $items);
        $this->assertSame('First', $items[0]['description']);
        $this->assertSame('Second', $items[1]['description']);
    }
}

