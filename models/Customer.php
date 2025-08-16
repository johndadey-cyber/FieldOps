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

    /**
     * Create a new customer record.
     *
     * Expected keys: first_name, last_name, email, phone,
     * address_line1, address_line2, city, state, postal_code, country,
     * google_place_id, latitude, longitude
     *
     * @param array<string,mixed> $data
     * @return int|false Inserted ID on success, false on failure
     */
    public function create(array $data)
    {
        $sql = 'INSERT INTO customers (first_name,last_name,email,phone,address_line1,address_line2,city,state,postal_code,country,google_place_id,latitude,longitude)
                VALUES (:fn,:ln,:em,:ph,:a1,:a2,:city,:st,:pc,:country,:pid,:lat,:lon)';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $ok = $stmt->execute([
            ':fn'      => (string)($data['first_name']    ?? ''),
            ':ln'      => (string)($data['last_name']     ?? ''),
            ':em'      => $data['email']      !== '' ? (string)$data['email']      : null,
            ':ph'      => $data['phone']      !== '' ? (string)$data['phone']      : null,
            ':a1'      => $data['address_line1'] !== '' ? (string)$data['address_line1'] : null,
            ':a2'      => $data['address_line2'] !== '' ? (string)$data['address_line2'] : null,
            ':city'    => $data['city']       !== '' ? (string)$data['city']       : null,
            ':st'      => $data['state']      !== '' ? (string)$data['state']      : null,
            ':pc'      => $data['postal_code'] !== '' ? (string)$data['postal_code'] : null,
            ':country' => $data['country']    !== '' ? (string)$data['country']    : null,
            ':pid'     => $data['google_place_id'] !== '' ? (string)$data['google_place_id'] : null,
            ':lat'     => isset($data['latitude']) ? $data['latitude'] : null,
            ':lon'     => isset($data['longitude']) ? $data['longitude'] : null,
        ]);

        if (!$ok) {
            return false;
        }

        /** @var int|string $id */
        $id = $this->pdo->lastInsertId();
        return is_numeric($id) ? (int)$id : false;
    }

    /**
     * Update an existing customer.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $sql = 'UPDATE customers
                   SET first_name=:fn,last_name=:ln,email=:em,phone=:ph,
                       address_line1=:a1,address_line2=:a2,city=:city,state=:st,postal_code=:pc,country=:country,
                       google_place_id=:pid, latitude=:lat, longitude=:lon
                 WHERE id=:id';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            return false;
        }

        return $stmt->execute([
            ':fn'      => (string)($data['first_name']    ?? ''),
            ':ln'      => (string)($data['last_name']     ?? ''),
            ':em'      => $data['email']      !== '' ? (string)$data['email']      : null,
            ':ph'      => $data['phone']      !== '' ? (string)$data['phone']      : null,
            ':a1'      => $data['address_line1'] !== '' ? (string)$data['address_line1'] : null,
            ':a2'      => $data['address_line2'] !== '' ? (string)$data['address_line2'] : null,
            ':city'    => $data['city']       !== '' ? (string)$data['city']       : null,
            ':st'      => $data['state']      !== '' ? (string)$data['state']      : null,
            ':pc'      => $data['postal_code'] !== '' ? (string)$data['postal_code'] : null,
            ':country' => $data['country']    !== '' ? (string)$data['country']    : null,
            ':pid'     => $data['google_place_id'] !== '' ? (string)$data['google_place_id'] : null,
            ':lat'     => isset($data['latitude']) ? $data['latitude'] : null,
            ':lon'     => isset($data['longitude']) ? $data['longitude'] : null,
            ':id'      => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM customers WHERE id = :id');
        if (!$stmt) {
            return false;
        }
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Build a full formatted address string from parts.
     *
     * @param array<string,mixed> $data
     */
    public static function getFullAddress(array $data): string
    {
        $parts = [
            $data['address_line1'] ?? null,
            $data['address_line2'] ?? null,
            $data['city'] ?? null,
            $data['state'] ?? null,
            $data['postal_code'] ?? null,
            $data['country'] ?? null,
        ];
        $parts = array_filter($parts, static fn($v): bool => $v !== null && $v !== '');
        return implode(', ', $parts);
    }
}
