<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Provide stub getPDO
$GLOBALS['__jobs_api_test_pdo'] = null;
if (!function_exists('getPDO')) {
    function getPDO(): PDO {
        /** @var PDO $pdo */
        $pdo = $GLOBALS['__jobs_api_test_pdo'];
        return $pdo;
    }
}

final class JobsDateFilterTest extends TestCase
{
    private string $start;
    private string $end;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['__jobs_api_test_pdo'] = $pdo;

        // Minimal schema
        $pdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, address_line1 TEXT, city TEXT)');
        $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY, scheduled_date TEXT, scheduled_time TEXT, status TEXT, duration_minutes INTEGER, customer_id INTEGER, deleted_at TEXT NULL)');
        $pdo->exec('CREATE TABLE job_employee (job_id INTEGER, employee_id INTEGER)');
        $pdo->exec('CREATE TABLE job_employee_assignment (id INTEGER PRIMARY KEY AUTOINCREMENT, job_id INTEGER, employee_id INTEGER, assigned_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(job_id, employee_id))');
        $pdo->exec('CREATE TABLE employees (id INTEGER PRIMARY KEY, person_id INTEGER)');
        $pdo->exec('CREATE TABLE people (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');

        $pdo->exec("INSERT INTO customers (id, first_name, last_name, address_line1, city) VALUES (1,'A','Cust','123','Town'), (2,'B','Cust','456','Town')");

        $today = new DateTimeImmutable();
        $this->start = $today->modify('+9 days')->format('Y-m-d');
        $this->end = $today->modify('+10 days')->format('Y-m-d');
        $pdo->exec("INSERT INTO jobs (id, scheduled_date, scheduled_time, status, duration_minutes, customer_id) VALUES (1,'{$this->start}','09:00','assigned',60,1),(2,'{$this->end}','09:00','assigned',60,2)");
    }

    public function testDateRangeFiltersJobs(): void
    {
        if (!defined('FIELDOPS_ALLOW_ENDPOINT_EXECUTION')) {
            define('FIELDOPS_ALLOW_ENDPOINT_EXECUTION', true);
        }
        $GLOBALS['__FIELDOPS_TEST_CALL__'] = true;

        $_GET = [
            'start' => $this->end,
            'end'   => $this->end,
            'status' => 'assigned'
        ];

        ob_start();
        require __DIR__ . '/../../public/api/jobs.php';
        $output = ob_get_clean();
        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $ids = array_column($data, 'job_id');
        $this->assertSame([2], $ids, 'Only job within date range should be returned');
    }
}
