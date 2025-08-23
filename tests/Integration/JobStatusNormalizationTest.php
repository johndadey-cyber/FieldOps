<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class JobStatusNormalizationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->beginTransaction();
        $this->pdo->exec("INSERT INTO customers (first_name,last_name,phone,created_at) VALUES ('Val','Customer','555-1111',NOW())");
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    /**
     * @dataProvider provideStatuses
     */
    public function testStatusStoredLowercase(string $input, string $expected): void
    {
        $customerId = (int)$this->pdo->query('SELECT id FROM customers LIMIT 1')->fetchColumn();
        $res = EndpointHarness::run(
            __DIR__ . '/../../public/dev_job_save_debug.php',
            [
                'customer_id'    => $customerId,
                'description'    => 'Case test',
                'scheduled_date' => '2025-09-01',
                'scheduled_time' => '10:00',
                'duration_minutes' => 30,
                'status'         => $input,
            ],
            ['role' => 'dispatcher']
        );

        $this->assertTrue($res['ok'] ?? false, 'Endpoint failed: ' . ($res['error'] ?? 'unknown'));
        $this->assertSame($expected, $res['status'] ?? null);

        $id = (int)($res['id'] ?? 0);
        $dbStatus = $this->pdo->query("SELECT status FROM jobs WHERE id = $id")->fetchColumn();
        $this->assertSame($expected, $dbStatus);
    }

    public static function provideStatuses(): array
    {
        return [
            ['Scheduled', 'scheduled'],
            ['In Progress', 'in_progress'],
        ];
    }
}
