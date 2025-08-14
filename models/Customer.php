<?php
declare(strict_types=1);

final class Customer
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllCustomers(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM customers');
        if (!$stmt) {
            return [];
        }
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM customers ORDER BY last_name, first_name');
        if (!$stmt) {
            return [];
        }
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /** @return array<string, mixed>|null */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id');
        if (!$stmt) {
            return null;
        }
        $stmt->execute([':id' => $id]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
