<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/CustomerDataProvider.php';

final class CustomerDataProviderSortTest extends TestCase
{
    public function testSortsByNameAscendingAndDescending(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE customers (
            id INTEGER PRIMARY KEY,
            first_name TEXT,
            last_name TEXT,
            company TEXT,
            notes TEXT,
            email TEXT,
            phone TEXT,
            address_line1 TEXT,
            address_line2 TEXT,
            city TEXT,
            state TEXT,
            postal_code TEXT,
            country TEXT,
            created_at TEXT
        )');
        $pdo->exec("INSERT INTO customers (id, first_name, last_name) VALUES
            (1,'John','Zulu'),
            (2,'Jane','Alpha'),
            (3,'Bob','Mike')");

        $rowsAsc = CustomerDataProvider::getFiltered($pdo, null, null, null, null, 'name', 'asc');
        $lastAsc = array_column($rowsAsc, 'last_name');
        $this->assertSame(['Alpha', 'Mike', 'Zulu'], $lastAsc);

        $rowsDesc = CustomerDataProvider::getFiltered($pdo, null, null, null, null, 'name', 'desc');
        $lastDesc = array_column($rowsDesc, 'last_name');
        $this->assertSame(['Zulu', 'Mike', 'Alpha'], $lastDesc);
    }

    public function testSortsByCreatedAtAscendingAndDescending(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE customers (
            id INTEGER PRIMARY KEY,
            first_name TEXT,
            last_name TEXT,
            company TEXT,
            notes TEXT,
            email TEXT,
            phone TEXT,
            address_line1 TEXT,
            address_line2 TEXT,
            city TEXT,
            state TEXT,
            postal_code TEXT,
            country TEXT,
            created_at TEXT
        )');

        $pdo->exec("INSERT INTO customers (id, created_at) VALUES
            (1, '2024-01-01 10:00:00'),
            (2, '2024-01-02 10:00:00'),
            (3, '2024-01-03 10:00:00')");

        $rowsAsc = CustomerDataProvider::getFiltered($pdo, null, null, null, null, 'created_at', 'asc');
        $datesAsc = array_column($rowsAsc, 'created_at');
        $this->assertSame([
            '2024-01-01 10:00:00',
            '2024-01-02 10:00:00',
            '2024-01-03 10:00:00',
        ], $datesAsc);

        $rowsDesc = CustomerDataProvider::getFiltered($pdo, null, null, null, null, 'created_at', 'desc');
        $datesDesc = array_column($rowsDesc, 'created_at');
        $this->assertSame([
            '2024-01-03 10:00:00',
            '2024-01-02 10:00:00',
            '2024-01-01 10:00:00',
        ], $datesDesc);
    }
}
