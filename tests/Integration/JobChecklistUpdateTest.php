<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../support/TestDataFactory.php';

final class JobChecklistUpdateTest extends TestCase
{
    private PDO $pdo;
    private int $jobId;
    private int $item1;
    private int $item2;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('DELETE FROM job_checklist_items');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM customers');

        $customerId   = TestDataFactory::createCustomer($this->pdo);
        $this->jobId  = TestDataFactory::createJob($this->pdo, $customerId, 'Checklist job', '2025-01-01', '09:00:00');

        $st = $this->pdo->prepare('INSERT INTO job_checklist_items (job_id, description, is_completed) VALUES (:j,:d,0)');
        $st->execute([':j' => $this->jobId, ':d' => 'First']);
        $this->item1 = (int)$this->pdo->lastInsertId();
        $st->execute([':j' => $this->jobId, ':d' => 'Second']);
        $this->item2 = (int)$this->pdo->lastInsertId();
    }

    public function testBulkChecklistUpdate(): void
    {
        $payload = [
            'items' => json_encode([
                ['id' => $this->item1, 'completed' => true],
                ['id' => $this->item2, 'completed' => false],
            ]),
        ];

        $res = EndpointHarness::run(
            __DIR__ . '/../../public/api/job_checklist_update.php',
            $payload,
            ['role' => 'technician']
        );

        $this->assertTrue($res['ok'] ?? false);

        $row1 = $this->pdo->query('SELECT is_completed, completed_at FROM job_checklist_items WHERE id=' . $this->item1)
            ->fetch(PDO::FETCH_ASSOC);
        $row2 = $this->pdo->query('SELECT is_completed, completed_at FROM job_checklist_items WHERE id=' . $this->item2)
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(1, (int)$row1['is_completed']);
        $this->assertNotNull($row1['completed_at']);
        $this->assertSame(0, (int)$row2['is_completed']);
        $this->assertNull($row2['completed_at']);
    }
}
