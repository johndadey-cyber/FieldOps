<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/AssignmentEngine.php';

final class AssignmentEngineTest extends TestCase
{
    public function testQualificationAndSorting(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->sqliteCreateFunction('SEC_TO_TIME', function(int $secs): string {
            $h = intdiv($secs, 3600);
            $m = intdiv($secs % 3600, 60);
            $s = $secs % 60;
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }, 1);
        $pdo->sqliteCreateFunction('ADDTIME', function(string $time, string $interval): string {
            [$h, $m, $s] = array_map('intval', explode(':', $time));
            $base = $h*3600 + $m*60 + $s;
            [$h2, $m2, $s2] = array_map('intval', explode(':', $interval));
            $add = $h2*3600 + $m2*60 + $s2;
            $t = $base + $add;
            return sprintf('%02d:%02d:%02d', intdiv($t,3600)%24, intdiv($t%3600,60), $t%60);
        }, 2);
        $pdo->sqliteCreateFunction('TIMESTAMP', fn(string $date, string $time): string => $date . ' ' . $time, 2);

        // Table setup
        $pdo->exec('CREATE TABLE customers (
            id INTEGER PRIMARY KEY,
            first_name TEXT,
            last_name TEXT,
            latitude REAL,
            longitude REAL
        )');
        $pdo->exec('CREATE TABLE jobs (
            id INTEGER PRIMARY KEY,
            customer_id INTEGER,
            description TEXT,
            scheduled_date TEXT,
            scheduled_time TEXT,
            duration_minutes INTEGER,
            status TEXT,
            deleted_at TEXT NULL
        )');
        $pdo->exec('CREATE TABLE skills (
            id INTEGER PRIMARY KEY,
            name TEXT
        )');
        $pdo->exec('CREATE TABLE job_skill (
            job_id INTEGER,
            skill_id INTEGER
        )');
        $pdo->exec('CREATE TABLE people (
            id INTEGER PRIMARY KEY,
            first_name TEXT,
            last_name TEXT,
            latitude REAL,
            longitude REAL
        )');
        $pdo->exec('CREATE TABLE employees (
            id INTEGER PRIMARY KEY,
            person_id INTEGER,
            is_active INTEGER,
            role_id INTEGER
        )');
        $pdo->exec('CREATE TABLE employee_skills (
            id INTEGER PRIMARY KEY,
            employee_id INTEGER,
            skill_id INTEGER
        )');
        $pdo->exec('CREATE TABLE employee_availability (
            id INTEGER PRIMARY KEY,
            employee_id INTEGER,
            day_of_week TEXT,
            start_time TEXT,
            end_time TEXT
        )');
        $pdo->exec('CREATE TABLE employee_availability_overrides (
            id INTEGER PRIMARY KEY,
            employee_id INTEGER,
            date TEXT,
            status TEXT,
            start_time TEXT,
            end_time TEXT
        )');
        $pdo->exec('CREATE TABLE job_employee_assignment (
            job_id INTEGER,
            employee_id INTEGER
        )');

        // Seed data
        $pdo->exec("INSERT INTO customers (id, first_name, last_name, latitude, longitude) VALUES (1,'Cust','One',10,10)");
        $pdo->exec("INSERT INTO jobs (id, customer_id, description, scheduled_date, scheduled_time, duration_minutes, status) VALUES
            (1,1,'Job 1','2024-01-01','10:00:00',60,'scheduled'),
            (2,1,'Job 2','2024-01-01','09:30:00',120,'scheduled')");
        $pdo->exec("INSERT INTO skills (id, name) VALUES (1,'SkillA')");
        $pdo->exec("INSERT INTO job_skill (job_id, skill_id) VALUES (1,1)");
        $pdo->exec("INSERT INTO people (id, first_name, last_name, latitude, longitude) VALUES
            (1,'Charlie','Chaplin',10,10),
            (2,'Bob','Barker',10,10),
            (3,'Dan','Doe',10,10),
            (4,'Evan','Eve',10,10),
            (5,'Aaron','Alpha',11,10),
            (6,'Frank','Foo',10,10)");
        $pdo->exec("INSERT INTO employees (id, person_id, is_active, role_id) VALUES
            (1,1,1,1),
            (2,2,1,1),
            (3,3,1,1),
            (4,4,1,1),
            (5,5,1,1),
            (6,6,1,1)");
        $pdo->exec("INSERT INTO employee_skills (id, employee_id, skill_id) VALUES
            (1,1,1),
            (2,2,1),
            (3,4,1),
            (4,5,1),
            (5,6,1)");
        $pdo->exec("INSERT INTO employee_availability (id, employee_id, day_of_week, start_time, end_time) VALUES
            (1,1,'Monday','00:00:00','23:59:59'),
            (2,2,'Monday','00:00:00','23:59:59'),
            (3,3,'Monday','00:00:00','23:59:59'),
            (4,4,'Monday','00:00:00','23:59:59'),
            (5,5,'Monday','00:00:00','23:59:59'),
            (6,6,'Monday','00:00:00','23:59:59')");
        $pdo->exec("INSERT INTO employee_availability_overrides (id, employee_id, date, status, start_time, end_time) VALUES
            (1,4,'2024-01-01','UNAVAILABLE','00:00:00','23:59:59')");
        $pdo->exec("INSERT INTO job_employee_assignment (job_id, employee_id) VALUES (2,6)");

        $engine = new AssignmentEngine($pdo);
        $result = $engine->eligibleEmployeesForJob(1, '2024-01-01', '10:00:00');

        $qualifiedNames = array_column($result['qualified'], 'name');
        $this->assertSame(['Bob Barker', 'Charlie Chaplin', 'Aaron Alpha'], $qualifiedNames);

        $notQualified = array_map(
            fn(array $row): array => ['name' => $row['name'], 'reasons' => $row['reasons']],
            $result['notQualified']
        );
        $this->assertSame([
            ['name' => 'Dan Doe', 'reasons' => ['missing_required_skills']],
            ['name' => 'Evan Eve', 'reasons' => ['not_available']],
            ['name' => 'Frank Foo', 'reasons' => ['time_conflict']],
        ], $notQualified);
    }
}
