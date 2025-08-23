<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestPdo.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class AvailabilityExportPrintTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function seed(): array
    {
        $eid = TestDataFactory::createEmployee($this->pdo, 'Eva', 'Export');
        $weekStart = (new DateTimeImmutable('next Monday'))->format('Y-m-d');
        $dow = (int)date('w', strtotime($weekStart)) + 1;
        TestDataFactory::setAvailability($this->pdo, $eid, $dow, '08:00:00', '16:00:00');
        $ovDate = (new DateTimeImmutable($weekStart))->modify('+2 days')->format('Y-m-d');
        TestDataFactory::createOverride($this->pdo, $eid, $ovDate, 'UNAVAILABLE', '13:00:00', '17:00:00', 'PTO', 'Vacation');

        $name = (string)$this->pdo->query("SELECT CONCAT(first_name,' ',last_name) FROM employees e JOIN people p ON p.id=e.person_id WHERE e.id={$eid}")->fetchColumn();
        $rows = [];
        $rs = $this->pdo->query("SELECT day_of_week, DATE_FORMAT(start_time,'%H:%i') AS start_time, DATE_FORMAT(end_time,'%H:%i') AS end_time FROM employee_availability WHERE employee_id={$eid} ORDER BY day_of_week,start_time")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rs as $r) {
            $rows[] = [$name, (string)$r['day_of_week'], $r['start_time'], $r['end_time'], '', '', ''];
        }
        $rs = $this->pdo->query("SELECT date, DATE_FORMAT(start_time,'%H:%i') AS start_time, DATE_FORMAT(end_time,'%H:%i') AS end_time, status, type, reason FROM employee_availability_overrides WHERE employee_id={$eid} ORDER BY date,start_time")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rs as $r) {
            $rows[] = [$name, $r['date'], $r['start_time'], $r['end_time'], $r['status'], $r['type'], $r['reason']];
        }
        return [$eid, $weekStart, $rows];
    }

    public function testCsvExportMatchesDatabase(): void
    {
        [$eid, $weekStart, $expected] = $this->seed();

        $res = EndpointHarness::run(__DIR__ . '/../../public/api/availability/export.php', [
            'employee_id' => $eid,
            'week_start' => $weekStart,
        ], [], 'GET', ['inject_csrf' => false]);

        $lines = array_map('str_getcsv', explode("\n", trim($res['raw'] ?? '')));
        $dataLines = array_slice($lines, 1);
        $this->assertSame($expected, $dataLines);
    }

    public function testPrintEndpointMatchesDatabase(): void
    {
        [$eid, $weekStart, $expected] = $this->seed();

        $res = EndpointHarness::run(__DIR__ . '/../../public/availability_print.php', [
            'employee_id' => $eid,
            'week_start' => $weekStart,
        ], [], 'GET', ['inject_csrf' => false]);

        $html = $res['raw'] ?? '';
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $rows = [];
        foreach ($xpath->query('//table/tbody/tr') as $tr) {
            $cells = [];
            foreach ($tr->getElementsByTagName('td') as $td) {
                $cells[] = trim($td->textContent);
            }
            if ($cells) {
                $rows[] = $cells;
            }
        }
        $this->assertSame($expected, $rows);
    }
}
