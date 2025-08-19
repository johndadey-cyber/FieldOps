<?php declare(strict_types=1);

final class Skill
{
    /**
     * Return all skills.
     *
     * @return list<array{id:int|string,name:string}>
     */
    public static function all(PDO $pdo): array
    {
        $st = $pdo->prepare('SELECT id, name FROM skills ORDER BY name, id');
        if ($st === false) {
            return [];
        }
        $st->execute();
        /** @var list<array{id:int|string,name:string}> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * Fetch skills for a given employee.
     *
     * @return list<array{id:int|string,name:string}>
     */
    public static function forEmployee(PDO $pdo, int $employeeId): array
    {
        try {
            $st = $pdo->prepare(
                'SELECT s.id, s.name
                 FROM employee_skills es
                 JOIN skills s ON s.id = es.skill_id
                 WHERE es.employee_id = :eid
                 ORDER BY s.name, s.id'
            );
            if ($st === false) {
                return [];
            }
            $st->execute([':eid' => $employeeId]);
            /** @var list<array{id:int|string,name:string}> $rows */
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Find a single skill by id.
     *
     * @return array{id:int|string,name:string}|null
     */
    public static function find(PDO $pdo, int $id): ?array
    {
        try {
            $st = $pdo->prepare('SELECT id, name FROM skills WHERE id = :id');
            if ($st === false) {
                return null;
            }
            $st->execute([':id' => $id]);
            /** @var array{id:int|string,name:string}|false $row */
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Create a new skill and return its id on success.
     *
     * @return int|false
     */
    public static function create(PDO $pdo, string $name)
    {
        try {
            $st = $pdo->prepare('INSERT INTO skills (name) VALUES (:name)');
            if ($st === false) {
                return false;
            }
            $ok = $st->execute([':name' => $name]);
            if (!$ok) {
                return false;
            }
            /** @var int|string $id */
            $id = $pdo->lastInsertId();
            return is_numeric($id) ? (int)$id : false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Update an existing skill name.
     */
    public static function update(PDO $pdo, int $id, string $name): bool
    {
        try {
            $st = $pdo->prepare('UPDATE skills SET name = :name WHERE id = :id');
            if ($st === false) {
                return false;
            }
            return $st->execute([':name' => $name, ':id' => $id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Delete a skill by id.
     */
    public static function delete(PDO $pdo, int $id): bool
    {
        try {
            $st = $pdo->prepare('DELETE FROM skills WHERE id = :id');
            if ($st === false) {
                return false;
            }
            return $st->execute([':id' => $id]);
        } catch (Throwable $e) {
            return false;
        }
    }
}
