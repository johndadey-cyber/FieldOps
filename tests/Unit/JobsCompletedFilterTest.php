<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Provide a stub getPDO used by the endpoint
$GLOBALS['__jobs_api_test_pdo'] = null;
if (!function_exists('getPDO')) {
    function getPDO(): PDO {
        /** @var PDO $pdo */
        $pdo = $GLOBALS['__jobs_api_test_pdo'];
        return $pdo;
    }
}

final class JobsCompletedFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['__jobs_api_test_pdo'] = $pdo;

        // Minimal schema required by the API
        $pdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, address_line1 TEXT, city TEXT)');
        $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY, scheduled_date TEXT, scheduled_time TEXT, status TEXT, duration_minutes INTEGER, customer_id INTEGER, deleted_at TEXT NULL)');
        $pdo->exec('CREATE TABLE job_employee (job_id INTEGER, employee_id INTEGER)');
        $pdo->exec('CREATE TABLE job_employee_assignment (id INTEGER PRIMARY KEY AUTOINCREMENT, job_id INTEGER, employee_id INTEGER, assigned_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(job_id, employee_id))');
        $pdo->exec('CREATE TABLE employees (id INTEGER PRIMARY KEY, person_id INTEGER)');
        $pdo->exec('CREATE TABLE people (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');

        $pdo->exec("INSERT INTO customers (id, first_name, last_name, address_line1, city) VALUES (1,'Past','Customer','123 Street','Town')");
        $pastDate = (new DateTimeImmutable('-10 days'))->format('Y-m-d');
        $pdo->exec("INSERT INTO jobs (id, scheduled_date, scheduled_time, status, duration_minutes, customer_id) VALUES (1,'$pastDate',NULL,'completed',60,1)");
    }

    public function testCompletedFilterReturnsPastJobs(): void
    {
        if (!defined('FIELDOPS_ALLOW_ENDPOINT_EXECUTION')) {
            define('FIELDOPS_ALLOW_ENDPOINT_EXECUTION', true);
        }
        $GLOBALS['__FIELDOPS_TEST_CALL__'] = true;

        $_GET = ['status' => 'completed'];
        ob_start();
        require __DIR__ . '/../../public/api/jobs.php';
        $output = ob_get_clean();
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $ids = array_column($data, 'job_id');
        $this->assertContains(1, $ids, 'Past completed job should be returned');
    }
}

