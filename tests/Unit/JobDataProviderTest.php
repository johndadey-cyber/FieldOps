<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/JobDataProvider.php';

final class JobDataProviderTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('CONCAT', fn(...$args) => implode('', $args));
        $this->pdo->sqliteCreateFunction('CONCAT_WS', function ($sep, ...$args) {
            $filtered = array_filter($args, fn($v) => $v !== null && $v !== '');
            return implode($sep, $filtered);
        });

        $this->pdo->exec('CREATE TABLE customers (
            id INTEGER PRIMARY KEY,
            first_name TEXT,
            last_name TEXT,
            address_line1 TEXT,
            city TEXT
        )');

        $this->pdo->exec('CREATE TABLE jobs (
            id INTEGER PRIMARY KEY,
            description TEXT,
            scheduled_date TEXT,
            scheduled_time TEXT,
            duration_minutes INTEGER,
            status TEXT,
            customer_id INTEGER
        )');

        $today = new DateTimeImmutable('now');
        $d1 = $today->modify('+1 day')->format('Y-m-d');
        $d3 = $today->modify('+3 day')->format('Y-m-d');
        $d5 = $today->modify('+5 day')->format('Y-m-d');

        $this->pdo->exec("INSERT INTO customers (id, first_name, last_name, address_line1, city) VALUES
            (1,'Alice','Adams','1 A St','Alpha'),
            (2,'Bob','Brown','2 B St','Beta'),
            (3,'Charlie','Clark','3 C St','Gamma')");

        $stmt = $this->pdo->prepare('INSERT INTO jobs (id, description, scheduled_date, scheduled_time, duration_minutes, status, customer_id) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([1,'Alpha job',$d1,'09:00:00',0,'scheduled',1]);
        $stmt->execute([2,'Beta job',$d3,'08:00:00',0,'completed',2]);
        $stmt->execute([3,'Gamma job',$d3,'08:00:00',0,'scheduled',2]);
        $stmt->execute([4,'Delta job',$d3,'09:00:00',0,'scheduled',3]);
        $stmt->execute([5,'Epsilon job',$d5,'08:00:00',0,'scheduled',1]);
    }

    public function testFiltersRespectDaysStatusAndSearch(): void
    {
        $rows = JobDataProvider::getFiltered($this->pdo, 2, null, null);
        $this->assertSame([1], array_column($rows, 'job_id'));

        $rows = JobDataProvider::getFiltered($this->pdo, 3, 'scheduled', null);
        $this->assertSame([1,3,4], array_column($rows, 'job_id'));

        $rows = JobDataProvider::getFiltered($this->pdo, null, null, 'Bob');
        $this->assertSame([2,3], array_column($rows, 'job_id'));

        $rows = JobDataProvider::getFiltered($this->pdo, null, null, 'Delta');
        $this->assertSame([4], array_column($rows, 'job_id'));
    }

    public function testReturnsRowsOrderedByDateTimeAndId(): void
    {
        $rows = JobDataProvider::getFiltered($this->pdo, null, null, null);
        $this->assertSame([1,2,3,4,5], array_column($rows, 'job_id'));
    }
}

