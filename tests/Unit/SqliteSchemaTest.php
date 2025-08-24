<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SqliteSchemaTest extends TestCase
{
    public function testSchemaIncludesExpectedTablesAndColumns(): void
    {
        $dsn = getenv('FIELDOPS_TEST_DSN');
        if ($dsn !== false && strncmp((string) $dsn, 'sqlite', 6) !== 0) {
            $this->markTestSkipped('Sqlite schema test runs only with SQLite');
        }

        $pdo = createTestPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $this->markTestSkipped('Sqlite schema test runs only with SQLite');
        }

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('job_types', $tables);
        $this->assertContains('checklist_templates', $tables);
        $this->assertContains('employee_availability_overrides', $tables);

        $this->assertTrue($this->columnExists($pdo, 'customers', 'created_at'));
        $this->assertTrue($this->columnExists($pdo, 'employee_availability', 'start_date'));
        $this->assertTrue($this->columnExists($pdo, 'job_employee_assignment', 'assigned_at'));
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("PRAGMA table_info($table)");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        return in_array($column, $columns, true);
    }
}
