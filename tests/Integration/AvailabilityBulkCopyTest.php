<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestPdo.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class AvailabilityBulkCopyTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();
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

    public function testCopiesStartDateWhenColumnExists(): void
    {
        try {
            $this->pdo->query('SELECT start_date FROM employee_availability LIMIT 0');
        } catch (Throwable $e) {
            $this->markTestSkipped('start_date column not present');
        }

        $src = TestDataFactory::createEmployee($this->pdo, 'Src', 'Emp');
        $target = TestDataFactory::createEmployee($this->pdo, 'Dest', 'Emp');

        $ins = $this->pdo->prepare('INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time, start_date) VALUES (:e,:d,:st,:et,:sd)');
        $ins->execute([':e'=>$src, ':d'=>1, ':st'=>'09:00:00', ':et'=>'10:00:00', ':sd'=>'2024-10-01']);
        $ins->execute([':e'=>$src, ':d'=>2, ':st'=>'13:00:00', ':et'=>'15:00:00', ':sd'=>'2024-10-02']);

        $srcRows = $this->pdo->query('SELECT day_of_week,start_time,end_time,start_date FROM employee_availability WHERE employee_id=' . (int)$src . ' ORDER BY day_of_week')->fetchAll(PDO::FETCH_ASSOC);

        $res = EndpointHarness::run(__DIR__ . '/../../public/api/availability/bulk_copy.php', [
            'source_employee_id' => $src,
            'target_employee_ids' => [$target],
        ]);
        $this->assertTrue($res['ok'] ?? false, 'bulk copy should succeed');

        $destRows = $this->pdo->query('SELECT day_of_week,start_time,end_time,start_date FROM employee_availability WHERE employee_id=' . (int)$target . ' ORDER BY day_of_week')->fetchAll(PDO::FETCH_ASSOC);

        $normalize = static function (array $rows): array {
            return array_map(static fn(array $r): array => [
                'day_of_week' => (string)$r['day_of_week'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time'],
                'start_date' => $r['start_date'],
            ], $rows);
        };

        $this->assertSame($normalize($srcRows), $normalize($destRows));
    }
}
