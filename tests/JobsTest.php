<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * JobsTest — exercises the server-rendered table partial (jobs_table.php)
 * Covers:
 *  - includes created job row
 *  - status filter = Unassigned
 *  - description search
 *  - customer-name search
 *  - days range window
 *
 * Prereqs:
 *  - Dev server running (e.g. `php -S 127.0.0.1:8010 -t public`)
 *  - FIELDOPS_BASE_URL set if not using 127.0.0.1:8010
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/support/Http.php';
require_once __DIR__ . '/support/TestDataFactory.php';

final class JobsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** @group jobs */
    public function testJobsTableIncludesCreatedJob(): void
    {
        $cid  = TestDataFactory::createCustomer($this->pdo, 'Table', 'Render');
        $date = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $jobId = TestDataFactory::createJob($this->pdo, $cid, 'Render test row', $date, '09:00:00', 45, 'Unassigned');

        $this->pdo->commit();

        $html = Http::get('jobs_table.php?days=365');

        $this->assertNotEmpty($html, 'jobs_table.php should return HTML rows');
        $this->assertStringContainsString((string)$jobId, $html, 'Contains job id');
        $this->assertStringContainsString('Render test row', $html, 'Contains job description');
    }

    /** @group jobs */
    public function testStatusFilterUnassigned(): void
    {
        $cid  = TestDataFactory::createCustomer($this->pdo, 'Filter', 'Unassigned');
        $date = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $j1 = TestDataFactory::createJob($this->pdo, $cid, 'Unassigned row', $date, '13:00:00', 30, 'Unassigned');
        $j2 = TestDataFactory::createJob($this->pdo, $cid, 'Completed row',  $date, '14:00:00', 30, 'Completed');

        $this->pdo->commit();

        $html = Http::get('jobs_table.php?status=Unassigned&days=365');

        $this->assertNotEmpty($html, 'jobs_table.php (filtered) should return HTML rows');
        $this->assertStringContainsString((string)$j1, $html, 'Shows Unassigned job');
        $this->assertStringNotContainsString((string)$j2, $html, 'Hides Completed job');
    }

    /** @group jobs */
    public function testSearchFiltersByDescription(): void
    {
        $cid  = TestDataFactory::createCustomer($this->pdo, 'Search', 'Case');
        $date = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');

        $token  = bin2hex(random_bytes(4));
        $needle = "Nebula-$token";

        $matchId    = TestDataFactory::createJob($this->pdo, $cid, "Install $needle Panels", $date, '10:00:00', 45, 'Unassigned');
        $nonMatchId = TestDataFactory::createJob($this->pdo, $cid, "Routine Maintenance",     $date, '11:00:00', 45, 'Unassigned');

        $this->pdo->commit();

        $html = Http::get('jobs_table.php?days=365&search=' . urlencode($needle));

        $this->assertNotEmpty($html, 'jobs_table.php (search) should return HTML');
        $this->assertStringContainsString((string)$matchId, $html, 'Search includes matching job id');
        $this->assertStringContainsString($needle, $html, 'Search includes matching description token');
        $this->assertStringNotContainsString((string)$nonMatchId, $html, 'Search excludes non-matching job id');
    }

    /** @group jobs */
    public function testSearchFiltersByCustomerName(): void
    {
        $token = bin2hex(random_bytes(4));
        $first = "Orion-$token";
        $last  = "Client";
        $date  = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');

        $cidMatch   = TestDataFactory::createCustomer($this->pdo, $first, $last);
        $matchId    = TestDataFactory::createJob($this->pdo, $cidMatch, 'Any description', $date, '10:00:00', 45, 'Unassigned');

        $cidOther   = TestDataFactory::createCustomer($this->pdo, 'Comet', 'Customer');
        $nonMatchId = TestDataFactory::createJob($this->pdo, $cidOther, 'Irrelevant job',  $date, '11:00:00', 45, 'Unassigned');

        $this->pdo->commit();

        $html = Http::get('jobs_table.php?days=365&search=' . urlencode($first));

        $this->assertNotEmpty($html, 'jobs_table.php (customer-name search) should return HTML');
        $this->assertStringContainsString((string)$matchId, $html, 'Includes matching customer’s job id');
        $this->assertStringContainsString($first, $html, 'Shows matching customer name');
        $this->assertStringNotContainsString((string)$nonMatchId, $html, 'Excludes non-matching customer’s job id');
    }

    /** @group jobs */
    public function testDaysRangeFiltersOutDistantJobs(): void
    {
        $cid = TestDataFactory::createCustomer($this->pdo, 'Range', 'Filter');

        $insideDate  = (new DateTimeImmutable('today +3 days'))->format('Y-m-d');
        $outsideDate = (new DateTimeImmutable('today +30 days'))->format('Y-m-d');

        $insideId  = TestDataFactory::createJob($this->pdo, $cid, 'Inside 7-day window',  $insideDate,  '09:00:00', 60, 'Unassigned');
        $outsideId = TestDataFactory::createJob($this->pdo, $cid, 'Outside 7-day window', $outsideDate, '09:30:00', 60, 'Unassigned');

        $this->pdo->commit();

        $html = Http::get('jobs_table.php?days=7');

        $this->assertNotEmpty($html, 'jobs_table.php (days filter) should return HTML');
        $this->assertStringContainsString((string)$insideId, $html, '7-day filter includes inside job');
        $this->assertStringNotContainsString((string)$outsideId, $html, '7-day filter excludes outside job');
    }
}
