<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/Http.php';
require_once __DIR__ . '/../support/TestDataFactory.php';
require_once __DIR__ . '/support/TestPdo.php';

#[Group('jobs')]
final class JobsTableEdgeCasesTest extends TestCase
{
    private string $baseUrl;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseUrl = rtrim(getenv('FIELDOPS_BASE_URL') ?: 'http://127.0.0.1:8010', '/');

        $this->pdo = createTestPdo();

        // Clean DB
        $this->pdo->exec('DELETE FROM job_employee_assignment');
        $this->pdo->exec('DELETE FROM employee_availability');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');
        $this->pdo->exec('DELETE FROM customers');
    }

    private function renderJobsTable(array $params = []): string
    {
        $url = $this->baseUrl . '/jobs_table.php';
        $q = http_build_query($params);
        if ($q) {
            $url .= '?' . $q;
        }
        return Http::get($url);
    }

    public function testEscapesDescriptionAndCustomerNames(): void
    {
        $cid = TestDataFactory::createCustomer($this->pdo, [
            'first_name' => '<script>Bad</script>',
            'last_name'  => 'User',
        ]);

        TestDataFactory::createJob($this->pdo, [
            'customer_id'      => $cid,
            'description'      => 'Hello <script>alert(1)</script>',
            'status'           => 'scheduled',
            'scheduled_date'   => (new DateTimeImmutable('+1 day'))->format('Y-m-d'),
            'scheduled_time'   => '09:00:00',
            'duration_minutes' => 30,
        ]);

        $html = $this->renderJobsTable(['days' => 365]);

        // Expect escaped strings (no raw <script> in output)
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString(htmlspecialchars('Hello <script>alert(1)</script>', ENT_QUOTES), $html);
        $this->assertStringNotContainsString('<script>Bad</script>', $html);
        $this->assertStringContainsString(htmlspecialchars('<script>Bad</script>', ENT_QUOTES), $html);
    }

    public function testDaysParamZeroNegativeAndLarge(): void
    {
        $cid = TestDataFactory::createCustomer($this->pdo, [
            'first_name' => 'D',
            'last_name'  => 'Range',
        ]);

        // Today
        TestDataFactory::createJob($this->pdo, [
            'customer_id'      => $cid,
            'description'      => 'Today job',
            'status'           => 'scheduled',
            'scheduled_date'   => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'scheduled_time'   => '10:00:00',
            'duration_minutes' => 30,
        ]);

        // Far future
        TestDataFactory::createJob($this->pdo, [
            'customer_id'      => $cid,
            'description'      => 'Far future job',
            'status'           => 'scheduled',
            'scheduled_date'   => (new DateTimeImmutable('+400 days'))->format('Y-m-d'),
            'scheduled_time'   => '10:00:00',
            'duration_minutes' => 30,
        ]);

        // days = 0 -> likely shows now/none depending on implementation, but should not error
        $html0 = $this->renderJobsTable(['days' => 0]);
        $this->assertIsString($html0);

        // days = -5 -> should not fatally error; should behave predictably (probably empty)
        $htmlNeg = $this->renderJobsTable(['days' => -5]);
        $this->assertIsString($htmlNeg);

        // days = very large -> should include far future job
        $htmlBig = $this->renderJobsTable(['days' => 3650]);
        $this->assertStringContainsString('Far future job', $htmlBig);
    }

    public function testSearchWithPercentUnderscoreAndSpaces(): void
    {
        $cid = TestDataFactory::createCustomer($this->pdo, [
            'first_name' => 'Alex',
            'last_name'  => 'Percent',
        ]);

        TestDataFactory::createJob($this->pdo, [
            'customer_id'      => $cid,
            'description'      => 'Wash 100% exterior',
            'status'           => 'scheduled',
            'scheduled_date'   => (new DateTimeImmutable('+2 days'))->format('Y-m-d'),
            'scheduled_time'   => '11:00:00',
            'duration_minutes' => 60,
        ]);

        TestDataFactory::createJob($this->pdo, [
            'customer_id'      => $cid,
            'description'      => 'Underscore_name_job',
            'status'           => 'scheduled',
            'scheduled_date'   => (new DateTimeImmutable('+2 days'))->format('Y-m-d'),
            'scheduled_time'   => '12:00:00',
            'duration_minutes' => 60,
        ]);

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
