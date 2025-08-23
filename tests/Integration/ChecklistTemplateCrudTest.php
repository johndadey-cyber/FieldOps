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
        $this->pdo->beginTransaction();
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = 'INSERT INTO job_types (id, name) VALUES (:id, :name) '
                . 'ON CONFLICT (id) DO UPDATE SET name = excluded.name';
            $this->pdo->prepare($sql)
                ->execute([':id' => $this->jobTypeId, ':name' => 'Checklist CRUD Type']);
        } else {
            $st = $this->pdo->prepare('UPDATE job_types SET name = :name WHERE id = :id');
            $st->execute([':name' => 'Checklist CRUD Type', ':id' => $this->jobTypeId]);
            if ($st->rowCount() === 0) {
                $this->pdo->prepare('INSERT INTO job_types (id, name) VALUES (:id, :name)')
                    ->execute([':id' => $this->jobTypeId, ':name' => 'Checklist CRUD Type']);
            }
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
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

    public function testMultipleItemOrdering(): void
    {
        $items = [
            ['description' => 'First', 'position' => 1],
            ['description' => 'Second', 'position' => 2],
            ['description' => 'Third', 'position' => 3],
        ];
        foreach ($items as $it) {
            ChecklistTemplate::create($this->pdo, $this->jobTypeId, $it['description'], $it['position']);
        }

        $grouped = ChecklistTemplate::allByJobType($this->pdo);
        $this->assertArrayHasKey($this->jobTypeId, $grouped);
        $this->assertCount(3, $grouped[$this->jobTypeId]);
        $descs = array_column($grouped[$this->jobTypeId], 'description');
        $this->assertSame(['First', 'Second', 'Third'], $descs);
    }
}
