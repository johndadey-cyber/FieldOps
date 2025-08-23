<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestPdo.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class AvailabilityBulkCopyMultipleTest extends TestCase
{
    private ?PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = createTestPdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        }
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        parent::tearDown();
    }

    public function testSequentialRequestsCommitConnections(): void
    {
        $src = TestDataFactory::createEmployee($this->pdo, 'Src', 'Emp');
        $t1  = TestDataFactory::createEmployee($this->pdo, 'Dest1', 'Emp');
        $t2  = TestDataFactory::createEmployee($this->pdo, 'Dest2', 'Emp');

        $hasStart = true;
        try {
            $this->pdo->query('SELECT start_date FROM employee_availability LIMIT 0');
        } catch (Throwable $e) {
            $hasStart = false;
        }

        $insSql = 'INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time'
            . ($hasStart ? ', start_date' : '') . ') VALUES (:e,:d,:st,:et' . ($hasStart ? ',:sd' : '') . ')';
        $ins = $this->pdo->prepare($insSql);

        $params = [':e'=>$src, ':d'=>1, ':st'=>'09:00:00', ':et'=>'11:00:00'];
        if ($hasStart) { $params[':sd'] = '2024-10-01'; }
        $ins->execute($params);
        $params = [':e'=>$src, ':d'=>3, ':st'=>'14:00:00', ':et'=>'18:00:00'];
        if ($hasStart) { $params[':sd'] = '2024-10-03'; }
        $ins->execute($params);

        // seed targets with different rows to ensure they get replaced
        $params = [':e'=>$t1, ':d'=>2, ':st'=>'00:00:00', ':et'=>'01:00:00'];
        if ($hasStart) { $params[':sd'] = '2024-01-01'; }
        $ins->execute($params);
        $params = [':e'=>$t2, ':d'=>4, ':st'=>'12:00:00', ':et'=>'13:00:00'];
        if ($hasStart) { $params[':sd'] = '2024-01-01'; }
        $ins->execute($params);

        $res1 = EndpointHarness::run(
            __DIR__ . '/../../public/api/availability/bulk_copy.php',
            [
                'source_employee_id' => $src,
                'target_employee_ids' => [$t1],
            ],
            [],
            'POST',
            ['json' => true]
        );
        $this->assertTrue($res1['ok'] ?? false, 'first bulk copy should succeed');

        $res2 = EndpointHarness::run(
            __DIR__ . '/../../public/api/availability/bulk_copy.php',
            [
                'source_employee_id' => $src,
                'target_employee_ids' => [$t2],
            ],
            [],
            'POST',
            ['json' => true]
        );
        $this->assertTrue($res2['ok'] ?? false, 'second bulk copy should succeed');

        $selCols = 'day_of_week,start_time,end_time' . ($hasStart ? ',start_date' : '');
        $expected = $this->pdo->query("SELECT {$selCols} FROM employee_availability WHERE employee_id = {$src} ORDER BY day_of_week,start_time")->fetchAll(PDO::FETCH_ASSOC);
        $rows1 = $this->pdo->query("SELECT {$selCols} FROM employee_availability WHERE employee_id = {$t1} ORDER BY day_of_week,start_time")->fetchAll(PDO::FETCH_ASSOC);
        $rows2 = $this->pdo->query("SELECT {$selCols} FROM employee_availability WHERE employee_id = {$t2} ORDER BY day_of_week,start_time")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame($expected, $rows1);
        $this->assertSame($expected, $rows2);
    }
}
