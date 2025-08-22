<?php declare(strict_types=1);

final class Role
{
    /**
     * Return all roles.
     *
     * @return list<array<string,mixed>>
     */
    public static function all(PDO $pdo): array
    {
        $st = $pdo->prepare("
            SELECT id, name
            FROM roles
            ORDER BY name, id
        ");
        if ($st === false) {
            return [];
        }

        $st->execute();

        /** @var list<array<string,mixed>> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * Find a single role by id.
     *
     * @return array{id:int|string,name:string}|null
     */
    public static function find(PDO $pdo, int $id): ?array
    {
        try {
            $st = $pdo->prepare('SELECT id, name FROM roles WHERE id = :id');
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
     * Create a new role and return its id on success.
     *
     * @return int|false
     */
    public static function create(PDO $pdo, string $name)
    {
        try {
            $st = $pdo->prepare('INSERT INTO roles (name) VALUES (:name)');
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
     * Update an existing role name.
     */
    public static function update(PDO $pdo, int $id, string $name): bool
    {
        try {
            $st = $pdo->prepare('UPDATE roles SET name = :name WHERE id = :id');
            if ($st === false) {
                return false;
            }
            return $st->execute([':name' => $name, ':id' => $id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Delete a role by id.
     */
    public static function delete(PDO $pdo, int $id): bool
    {
        try {
            $st = $pdo->prepare('DELETE FROM roles WHERE id = :id');
            if ($st === false) {
                return false;
            }
            return $st->execute([':id' => $id]);
        } catch (Throwable $e) {
            return false;
        }
    }
}
