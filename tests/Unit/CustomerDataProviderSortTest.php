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
            email TEXT,
            phone TEXT,
            address_line1 TEXT,
            city TEXT,
            state TEXT
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
}
