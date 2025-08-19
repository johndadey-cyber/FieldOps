<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class JobWriteRbacTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Clean minimal fixtures
        $this->pdo->exec("DELETE FROM jobs");
        $this->pdo->exec("DELETE FROM customers");

        // Seed
        $this->pdo->exec("INSERT INTO customers (first_name,last_name,phone,created_at) VALUES ('Test','Customer','555-0000',NOW())");
    }

    public function testNonDispatcherCannotCreate(): void
    {
        $customerId = (int)$this->pdo->query("SELECT id FROM customers LIMIT 1")->fetchColumn();

        $res = EndpointHarness::run(__DIR__ . '/../../public/job_save.php', [
            'customer_id'    => $customerId,
            'description'    => 'Test Job',
            'scheduled_date' => '2025-08-20',
            'scheduled_time' => '10:00',
            'status'         => 'scheduled',
            'skills'         => [1],
        ], [
            'role' => 'field_tech',
        ]);

        $this->assertFalse($res['ok'] ?? true);
        $this->assertSame(403, $res['code'] ?? 0);
    }

    public function testMissingCsrfIsRejected(): void
    {
        $customerId = (int)$this->pdo->query("SELECT id FROM customers LIMIT 1")->fetchColumn();

        // Use harness but DISABLE CSRF injection
        $res = EndpointHarness::run(__DIR__ . '/../../public/job_save.php', [
            'customer_id'    => $customerId,
            'description'    => 'Test Job',
            'scheduled_date' => '2025-08-20',
            'scheduled_time' => '10:00',
            'status'         => 'scheduled',
            'skills'         => [1],
            // no csrf_token on purpose
        ], [
            'role' => 'dispatcher',
        ], 'POST', ['inject_csrf' => false]);

        $this->assertFalse($res['ok'] ?? true, 'missing CSRF should fail');
        $this->assertSame(400, $res['code'] ?? 0);
    }

    public function testDispatcherCanCreateUpdateAndDelete(): void
    {
        $customerId = (int)$this->pdo->query("SELECT id FROM customers LIMIT 1")->fetchColumn();

        // CREATE
        $create = EndpointHarness::run(__DIR__ . '/../../public/job_save.php', [
            'customer_id'      => $customerId,
            'description'      => 'Initial Job',
            'scheduled_date'   => '2025-08-21',
            'scheduled_time'   => '09:30',
            'status'           => 'scheduled',
            'duration_minutes' => 120,
            'skills'           => [1],
        ], ['role' => 'dispatcher']);

        $this->assertTrue($create['ok'] ?? false);
        $this->assertSame('created', $create['action'] ?? '');
        $jobId = (int)($create['id'] ?? 0);
        $this->assertGreaterThan(0, $jobId);


        // UPDATE
        $update = EndpointHarness::run(__DIR__ . '/../../public/job_save.php', [
            'id'               => $jobId,
            'customer_id'      => $customerId,
            'description'      => 'Updated Job Title',
            'scheduled_date'   => '2025-08-22',
            'scheduled_time'   => '14:15',
            'status'           => 'assigned',
            'duration_minutes' => 90,
            'skills'           => [1],
        ], ['role' => 'dispatcher']);

        $this->assertTrue($update['ok'] ?? false);
        $this->assertSame('updated', $update['action'] ?? '');


        // DELETE
        $delete = EndpointHarness::run(__DIR__ . '/../../public/job_delete.php', [
            'id' => $jobId,
        ], ['role' => 'dispatcher']);

        $this->assertTrue($delete['ok'] ?? false);
        $this->assertSame('deleted', $delete['action'] ?? '');
    }
}
