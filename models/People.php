<?php
// models/People.php
declare(strict_types=1);

final class People
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new person.
     * Expects keys (if present): first_name, last_name, email, phone, created_at
     *
     * @param array<string, mixed> $data
     * @return int|false Inserted ID on success, false on failure
     */
    public function create(array $data)
    {
        $sql = 'INSERT INTO people (first_name, last_name, email, phone, created_at)
                VALUES (:fn, :ln, :em, :ph, :ca)';

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $ok = $stmt->execute([
            ':fn' => (string)($data['first_name'] ?? ''),
            ':ln' => (string)($data['last_name']  ?? ''),
            ':em' => (string)($data['email']      ?? ''),
            ':ph' => (string)($data['phone']      ?? ''),
            ':ca' => (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
        ]);

        if (!$ok) {
            return false;
        }

        /** @var int|string $id */
        $id = $this->pdo->lastInsertId();
        return is_numeric($id) ? (int)$id : false;
    }

    /**
     * Update a person by ID.
     * Only provided keys will be updated.
     * Allowed keys: first_name, last_name, email, phone
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        // Build dynamic SET clause from allowed fields
        $allowed = ['first_name', 'last_name', 'email', 'phone'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $param          = ':' . $key;
                $sets[]         = "{$key} = {$param}";
                $params[$param] = (string)$data[$key];
            }
        }

        if ($sets === []) {
            // Nothing to update; consider this a no-op success
            return true;
        }

        $sql  = 'UPDATE people SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            return false;
        }

        return $stmt->execute($params);
    }

    /** @return array<string, mixed>|null */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM people WHERE id = :id');
        if (!$stmt) {
            return null;
        }
        $stmt->execute([':id' => $id]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM people ORDER BY last_name, first_name, id');
        if (!$stmt) {
            return [];
        }
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }
}
