<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/Http.php';
require_once __DIR__ . '/../support/TestDataFactory.php';

#[Group('jobs')]
final class JobsTableEdgeCasesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn  = getenv('FIELDOPS_TEST_DSN')  ?: 'mysql:host=127.0.0.1;port=8889;dbname=fieldops_test;charset=utf8mb4';
        $user = getenv('FIELDOPS_TEST_USER') ?: 'root';
        $pass = getenv('FIELDOPS_TEST_PASS') ?: 'root';
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Clean DB
        $this->pdo->exec('DELETE FROM job_employee_assignment');
        $this->pdo->exec('DELETE FROM employee_availability');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');
        $this->pdo->exec('DELETE FROM customers');
    }

    /** Render jobs table via relative path (Http helper builds the full URL). */
    private function renderJobsTable(array $params = []): string
    {
        $path = 'jobs_table.php';
        $q = http_build_query($params);
        if ($q) {
            $path .= '?' . $q;
        }
        return Http::get($path);
    }

    public function testEscapesDescriptionAndCustomerNames(): void
    {
        $cid = TestDataFactory::createCustomer($this->pdo, '<script>Bad</script>', 'User');

        $date = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
        TestDataFactory::createJob(
            $this->pdo,
            $cid,
            'Hello <script>alert(1)</script>',
            $date,
            '09:00:00',
            30,
            'scheduled'
        );

        $html = $this->renderJobsTable(['days' => 365]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString(htmlspecialchars('Hello <script>alert(1)</script>', ENT_QUOTES), $html);
        $this->assertStringNotContainsString('<script>Bad</script>', $html);
        $this->assertStringContainsString(htmlspecialchars('<script>Bad</script>', ENT_QUOTES), $html);
    }

    public function testDaysParamZeroNegativeAndLarge(): void
    {
        $cid = TestDataFactory::createCustomer($this->pdo, 'D', 'Range');

        // Today
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        TestDataFactory::createJob($this->pdo, $cid, 'Today job', $today, '10:00:00', 30, 'scheduled');

        // Far future
        $future = (new DateTimeImmutable('+400 days'))->format('Y-m-d');
        TestDataFactory::createJob($this->pdo, $cid, 'Far future job', $future, '10:00:00', 30, 'scheduled');

        // days = 0 -> should not error
        $html0 = $this->renderJobsTable(['days' => 0]);
        $this->assertIsString($html0);

        // days = -5 -> should not fatally error; predictable behavior (likely empty)
        $htmlNeg = $this->renderJobsTable(['days' => -5]);
        $this->assertIsString($htmlNeg);

        // days = very large -> should include far future job
        $htmlBig = $this->renderJobsTable(['days' => 3650]);
        $this->assertStringContainsString('Far future job', $htmlBig);
    }

    public function testSearchWithPercentUnderscoreAndSpaces(): void
    {
        $cid = TestDataFactory::createCustomer($this->pdo, 'Alex', 'Percent');

        $date = (new DateTimeImmutable('+2 days'))->format('Y-m-d');
        TestDataFactory::createJob($this->pdo, $cid, 'Wash 100% exterior', $date, '11:00:00', 60, 'scheduled');
        TestDataFactory::createJob($this->pdo, $cid, 'Underscore_name_job',  $date, '12:00:00', 60, 'scheduled');

        // Search that includes % sign
        $html1 = $this->renderJobsTable(['search' => '100% exterior', 'days' => 30]);
        $this->assertStringContainsString('Wash 100% exterior', $html1);
        $this->assertStringNotContainsString('Underscore_name_job', $html1);

        // Search that includes underscore
        $html2 = $this->renderJobsTable(['search' => 'Underscore_', 'days' => 30]);
        $this->assertStringContainsString('Underscore_name_job', $html2);
        $this->assertStringNotContainsString('Wash 100% exterior', $html2);

        // Search with leading/trailing spaces
        $html3 = $this->renderJobsTable(['search' => '  exterior  ', 'days' => 30]);
        $this->assertStringContainsString('Wash 100% exterior', $html3);
    }
}
