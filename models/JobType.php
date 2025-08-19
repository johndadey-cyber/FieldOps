<?php declare(strict_types=1);

final class JobType
{
    /**
     * Fetch all job types.
     *
     * @return list<array{id:int|string,name:string}>
     */
    public static function all(PDO $pdo): array
    {
        $st = $pdo->prepare('SELECT id, name FROM job_types ORDER BY name, id');
        if ($st === false) {
            return [];
        }
        $st->execute();
        /** @var list<array{id:int|string,name:string}> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * Find a job type by id.
     *
     * @return array{id:int,name:string}|null
     */
    public static function find(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT id, name FROM job_types WHERE id = :id');
        if ($st === false) {
            return null;
        }
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return ['id' => (int)$row['id'], 'name' => (string)$row['name']];
    }

    /**
     * Create a new job type. Returns inserted id.
     */
    public static function create(PDO $pdo, string $name): int
    {
        $st = $pdo->prepare('INSERT INTO job_types (name) VALUES (:name)');
        $st->execute([':name' => $name]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Update an existing job type. Returns true if a row was updated.
     */
    public static function update(PDO $pdo, int $id, string $name): bool
    {
        $st = $pdo->prepare('UPDATE job_types SET name = :name WHERE id = :id');
        $st->execute([':name' => $name, ':id' => $id]);
        return $st->rowCount() > 0;
    }

    /**
     * Delete a job type by id. Returns true if a row was deleted.
     */
    public static function delete(PDO $pdo, int $id): bool
    {
        $st = $pdo->prepare('DELETE FROM job_types WHERE id = :id');
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }
}
