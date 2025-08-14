<?php declare(strict_types=1);

final class Employee
{
    /**
     * Return all employees (optionally active-only).
     *
     * @return list<array<string,mixed>>
     */
    public static function all(PDO $pdo, bool $activeOnly = true): array
    {
        $sql = <<<SQL
            SELECT e.id, e.is_active, e.person_id,
                   p.first_name, p.last_name, p.email
            FROM employees e
            LEFT JOIN people p ON p.id = e.person_id
        SQL;

        if ($activeOnly) {
            $sql .= " WHERE e.is_active = 1";
        }

        $sql .= " ORDER BY p.last_name, p.first_name, e.id";

        $st = $pdo->prepare($sql);
        if ($st === false) {
            return [];
        }

        $st->execute();

        /** @var list<array<string,mixed>> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * Legacy alias.
     * @return list<array<string,mixed>>
     */
    public static function getAll(PDO $pdo, bool $activeOnly = true): array
    {
        /** @var list<array<string,mixed>> */
        return self::all($pdo, $activeOnly);
    }

    /**
     * Fetch a single employee by id.
     *
     * @return array<string,mixed>|null
     */
    public static function getById(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare("
            SELECT e.id, e.is_active, e.person_id,
                   p.first_name, p.last_name, p.email
            FROM employees e
            LEFT JOIN people p ON p.id = e.person_id
            WHERE e.id = :id
            LIMIT 1
        ");
        if ($st === false) {
            return null;
        }

        $st->execute([':id' => $id]);
        /** @var array<string,mixed>|false $row */
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * IDs currently assigned to a job.
     * @return list<int>
     */
    public static function getAssignedEmployeeIds(PDO $pdo, int $jobId): array
    {
        $st = $pdo->prepare("SELECT employee_id FROM job_employee_assignment WHERE job_id = :job_id");
        if ($st === false) {
            return [];
        }
        $st->execute([':job_id' => $jobId]);
        /** @var list<array{employee_id:int|string}> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int)$r['employee_id'];
        }
        return $ids;
    }

    /**
     * Human-friendly skill names for an employee. Safe if skills tables don't exist.
     * @return list<string>
     */
    public static function getSkillNames(PDO $pdo, int $employeeId): array
    {
        try {
            $st = $pdo->prepare("
                SELECT s.name
                FROM employee_skills es
                JOIN skills s ON s.id = es.skill_id
                WHERE es.employee_id = :eid
                ORDER BY s.name
            ");
            if ($st === false) {
                return [];
            }
            $st->execute([':eid' => $employeeId]);
            /** @var list<array{name:string}> $rows */
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static fn(array $r): string => (string)$r['name'], $rows);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Used by get_assignable_employees.php; simple baseline.
     * @return list<array<string,mixed>>
     */
    public static function getEmployeesWithAvailabilityAndSkills(PDO $pdo, int $jobId): array
    {
        return self::all($pdo, true);
    }
}
