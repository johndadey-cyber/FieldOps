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
}
