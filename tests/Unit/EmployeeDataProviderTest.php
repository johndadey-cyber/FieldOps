<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/EmployeeDataProvider.php';

final class EmployeeDataProviderTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Provide MySQL-style CONCAT for SQLite
        $pdo->sqliteCreateFunction('CONCAT', function (...$args): string {
            return implode('', $args);
        });

        $pdo->exec('CREATE TABLE people (
            id INTEGER PRIMARY KEY,
            first_name TEXT,
            last_name TEXT,
            email TEXT,
            phone TEXT
        )');
        $pdo->exec('CREATE TABLE employees (
            id INTEGER PRIMARY KEY,
            person_id INT,
            is_active INT,
            status TEXT
        )');
        $pdo->exec('CREATE TABLE skills (
            id INTEGER PRIMARY KEY,
            name TEXT
        )');
        $pdo->exec('CREATE TABLE employee_skills (
            employee_id INT,
            skill_id INT,
            proficiency TEXT
        )');

        $pdo->exec("INSERT INTO people (id, first_name, last_name, email, phone) VALUES
            (1, 'John', 'Zulu', 'zulu@example.com', NULL),
            (2, 'Jane', 'Alpha', 'alpha@example.com', NULL),
            (3, 'Bob', 'Mike', 'mike@example.com', NULL)");

        $pdo->exec("INSERT INTO employees (id, person_id, is_active, status) VALUES
            (1, 1, 1, 'available'),
            (2, 2, 1, 'busy'),
            (3, 3, 0, 'available')");

        $pdo->exec("INSERT INTO skills (id, name) VALUES
            (1, 'HVAC'),
            (2, 'Plumbing'),
            (3, 'Electrical')");

        $pdo->exec("INSERT INTO employee_skills (employee_id, skill_id, proficiency) VALUES
            (1,1,NULL),(1,2,NULL),
            (2,2,NULL),(2,3,NULL),
            (3,3,NULL)");

        return $pdo;
    }

    public function testFiltersBySkillNames(): void
    {
        $pdo = $this->createPdo();
        $res = EmployeeDataProvider::getFiltered($pdo, ['Plumbing']);
        $ids = array_column($res['rows'], 'employee_id');
        sort($ids);
        $this->assertSame([1,2], $ids);

        $res2 = EmployeeDataProvider::getFiltered($pdo, ['Plumbing', 'Electrical']);
        $ids2 = array_column($res2['rows'], 'employee_id');
        $this->assertSame([2], $ids2);
    }

    public function testSearchMatchesFirstLastNameOrEmail(): void
    {
        $pdo = $this->createPdo();

        $r1 = EmployeeDataProvider::getFiltered($pdo, null, 1, 25, null, null, 'Jane');
        $this->assertSame([2], array_column($r1['rows'], 'employee_id'));

        $r2 = EmployeeDataProvider::getFiltered($pdo, null, 1, 25, null, null, 'Mike');
        $this->assertSame([3], array_column($r2['rows'], 'employee_id'));

        $r3 = EmployeeDataProvider::getFiltered($pdo, null, 1, 25, null, null, 'zulu@example.com');
        $this->assertSame([1], array_column($r3['rows'], 'employee_id'));
    }

    public function testSortingByLastNameAndEmployeeId(): void
    {
        $pdo = $this->createPdo();

        $byLast = EmployeeDataProvider::getFiltered($pdo, null, 1, 25, 'last_name', 'asc');
        $this->assertSame([2,3,1], array_column($byLast['rows'], 'employee_id'));

        $byIdDesc = EmployeeDataProvider::getFiltered($pdo, null, 1, 25, 'employee_id', 'desc');
        $this->assertSame([3,2,1], array_column($byIdDesc['rows'], 'employee_id'));
    }

    public function testPaginationLimitsResultsAndReportsTotal(): void
    {
        $pdo = $this->createPdo();
        $res = EmployeeDataProvider::getFiltered($pdo, null, 2, 2, 'employee_id', 'asc');
        $this->assertSame(3, $res['total']);
        $this->assertCount(1, $res['rows']);
        $this->assertSame(3, $res['rows'][0]['employee_id']);
    }
}
