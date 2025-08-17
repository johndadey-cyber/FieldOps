<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class JobWriteValidationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../config/database.php';
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("DELETE FROM jobs");
        $this->pdo->exec("DELETE FROM customers");

        $this->pdo->exec("INSERT INTO customers (first_name,last_name,phone,created_at) VALUES ('Val','Customer','555-1111',NOW())");
    }

    public function testValidationErrorsAreReturned(): void
    {
        $res = EndpointHarness::run(__DIR__ . '/../../public/job_save.php', [
            // intentionally missing required fields
            'description'    => '',
            'scheduled_date' => '',
            'scheduled_time' => '',
            'status'         => 'scheduled',
        ], ['role' => 'dispatcher']);

        $this->assertFalse($res['ok'] ?? true);
        $this->assertSame(422, $res['code'] ?? 0);
        $this->assertArrayHasKey('errors', $res);
        $this->assertArrayHasKey('customer_id', $res['errors']);
        $this->assertArrayHasKey('description', $res['errors']);
        $this->assertArrayHasKey('scheduled_date', $res['errors']);
        $this->assertArrayHasKey('scheduled_time', $res['errors']);
    }

    public function testTimeWithSecondsAndAmPmFormatsAreAccepted(): void
    {
        $customerId = (int)$this->pdo->query("SELECT id FROM customers LIMIT 1")->fetchColumn();

        $withSeconds = EndpointHarness::run(__DIR__ . '/../../public/job_save.php', [
            'customer_id'    => $customerId,
            'description'    => 'Job with seconds',
            'scheduled_date' => '2025-08-23',
            'scheduled_time' => '09:00:00',
            'status'         => 'scheduled',
            'skills'         => [1],
        ], ['role' => 'dispatcher']);
        $this->assertTrue($withSeconds['ok'] ?? false, 'HH:MM:SS time rejected');

        $withAmPm = EndpointHarness::run(__DIR__ . '/../../public/job_save.php', [
            'customer_id'    => $customerId,
            'description'    => 'Job with AM/PM',
            'scheduled_date' => '2025-08-24',
            'scheduled_time' => '02:15 PM',
            'status'         => 'scheduled',
            'skills'         => [1],
        ], ['role' => 'dispatcher']);
        $this->assertTrue($withAmPm['ok'] ?? false, 'AM/PM time rejected');
    }
}
