<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/JobType.php';

final class JobTypeAllTest extends TestCase
{
    public function testReturnsRowsWithoutDescriptionColumn(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE job_types (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO job_types (id, name) VALUES (1, 'Mock'), (2, 'Another')");

        $rows = JobType::all($pdo);

        $this->assertCount(2, $rows);
        $names = array_column($rows, 'name');
        $this->assertSame(['Another', 'Mock'], $names);
        $this->assertArrayNotHasKey('description', $rows[0]);
    }
}
