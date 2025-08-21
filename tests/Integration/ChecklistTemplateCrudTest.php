<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/ChecklistTemplate.php';

final class ChecklistTemplateCrudTest extends TestCase
{
    private PDO $pdo;
    private int $jobTypeId = 9999;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->prepare('INSERT INTO job_types (id, name) VALUES (:id, :name) ON DUPLICATE KEY UPDATE name = VALUES(name)')
            ->execute([':id' => $this->jobTypeId, ':name' => 'Checklist CRUD Type']);
        $this->pdo->prepare('DELETE FROM checklist_templates WHERE job_type_id = :jt')
            ->execute([':jt' => $this->jobTypeId]);
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM checklist_templates WHERE job_type_id = :jt')
            ->execute([':jt' => $this->jobTypeId]);
        $this->pdo->prepare('DELETE FROM job_types WHERE id = :id')
            ->execute([':id' => $this->jobTypeId]);
    }

    public function testCrudOperations(): void
    {
        $id = ChecklistTemplate::create($this->pdo, $this->jobTypeId, 'Step A', 1);
        $this->assertGreaterThan(0, $id);

        $tpl = ChecklistTemplate::find($this->pdo, $id);
        $this->assertNotNull($tpl);
        $this->assertSame('Step A', $tpl['description']);
        $this->assertSame(1, $tpl['position']);

        $grouped = ChecklistTemplate::allByJobType($this->pdo);
        $this->assertArrayHasKey($this->jobTypeId, $grouped);
        $this->assertCount(1, $grouped[$this->jobTypeId]);

        $updated = ChecklistTemplate::update($this->pdo, $id, $this->jobTypeId, 'Step B', 2);
        $this->assertTrue($updated);
        $tpl2 = ChecklistTemplate::find($this->pdo, $id);
        $this->assertNotNull($tpl2);
        $this->assertSame('Step B', $tpl2['description']);
        $this->assertSame(2, $tpl2['position']);

        $deleted = ChecklistTemplate::delete($this->pdo, $id);
        $this->assertTrue($deleted);
        $this->assertNull(ChecklistTemplate::find($this->pdo, $id));
    }
}
