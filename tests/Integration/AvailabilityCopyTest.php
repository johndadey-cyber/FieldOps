<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestPdo.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class AvailabilityCopyTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('DELETE FROM employee_availability');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');
    }

    public function testCopyReplacesExistingWithStartDate(): void
    {
        $eid = TestDataFactory::createEmployee($this->pdo, 'Copy', 'Tester');
        $startDate = '2024-01-01';
        $this->pdo->prepare("INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time, start_date) VALUES (:e,'Monday','09:00:00','17:00:00',:sd)")
            ->execute([':e' => $eid, ':sd' => $startDate]);
        $st = $this->pdo->prepare("INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time, start_date) VALUES (:e,:d,'10:00:00','12:00:00',:sd)");
        $st->execute([':e' => $eid, ':d' => 'Tuesday', ':sd' => $startDate]);
        $tueId = (int)$this->pdo->lastInsertId();
        $st->execute([':e' => $eid, ':d' => 'Wednesday', ':sd' => $startDate]);
        $wedId = (int)$this->pdo->lastInsertId();

        $res = EndpointHarness::run(
            __DIR__ . '/../../public/api/availability/create.php',
            [
                'employee_id' => $eid,
                'day_of_week' => ['Tuesday','Wednesday'],
                'blocks' => [['start_time' => '09:00', 'end_time' => '17:00']],
                'start_date' => $startDate,
                'replace_ids' => [$tueId, $wedId],
            ],
            [],
            'POST',
            ['json' => true]
        );

        $this->assertTrue($res['ok'] ?? false, 'copy should succeed');

        $stmt = $this->pdo->prepare("SELECT day_of_week,start_time,end_time,start_date FROM employee_availability WHERE employee_id=:e AND day_of_week IN ('Tuesday','Wednesday') ORDER BY day_of_week");
        $stmt->execute([':e' => $eid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('09:00:00', $row['start_time']);
            $this->assertSame('17:00:00', $row['end_time']);
            $this->assertSame($startDate, $row['start_date']);
        }
    }
}
