<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/CustomerDataProvider.php';

final class CustomerDataProviderFilterTest extends TestCase
{
    private function createPdo(): PDO
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
        $pdo->exec("INSERT INTO customers (id, first_name, last_name, company, notes, city, state, created_at) VALUES
            (1, 'Alice', 'Smith', 'Acme', 'Friend', 'New York', 'NY', '2024-01-01'),
            (2, 'Bob', 'Jones', 'Beta', 'VIP', 'New York', 'CA', '2024-01-02'),
            (3, 'Charlie', 'Brown', 'Gamma', 'Important', 'Los Angeles', 'CA', '2024-01-03')");
        return $pdo;
    }

    public function testAppliesSearchFilter(): void
    {
        $pdo = $this->createPdo();
        $rows = CustomerDataProvider::getFiltered($pdo, 'Acme');
        $this->assertSame([1], array_column($rows, 'id'));
    }

    public function testAppliesCityAndStateFilters(): void
    {
        $pdo = $this->createPdo();

        $rowsCity = CustomerDataProvider::getFiltered($pdo, null, 'New York');
        $this->assertSame([1, 2], array_column($rowsCity, 'id'));

        $rowsState = CustomerDataProvider::getFiltered($pdo, null, null, 'CA');
        $this->assertSame([2, 3], array_column($rowsState, 'id'));

        $rowsBoth = CustomerDataProvider::getFiltered($pdo, null, 'New York', 'NY');
        $this->assertSame([1], array_column($rowsBoth, 'id'));
    }

    public function testAppliesLimit(): void
    {
        $pdo = $this->createPdo();
        $rows = CustomerDataProvider::getFiltered($pdo, null, null, null, '2');
        $this->assertCount(2, $rows);
        $this->assertSame([1, 2], array_column($rows, 'id'));
    }
}
