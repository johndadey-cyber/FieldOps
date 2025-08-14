<?php declare(strict_types=1);

final class Availability
{
    /**
     * List availability rows for a given employee.
     * @return list<array<string,mixed>>
     */
    public static function forEmployee(PDO $pdo, int $employeeId): array
    {
        $sql = <<<SQL
            SELECT id, employee_id, day_of_week, start_time, end_time
            FROM employee_availability
            WHERE employee_id = :employee_id
            ORDER BY day_of_week, start_time
        SQL;

        $st = $pdo->prepare($sql);
        if ($st === false) {
            return [];
        }

        $st->execute([':employee_id' => $employeeId]);

        /** @var list<array<string,mixed>> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * Flexible helper used by UI.
     * @param int|array<string,mixed> $employee Either the id or an employee row (with 'id')
     * @return list<array<string,mixed>>
     */
    public static function getAvailabilityForEmployeeAndJob(
        PDO $pdo,
        int|array $employee,
        ?string $scheduledDate = null,
        ?string $scheduledTime = null,
        ?int $durationMinutes = null
    ): array {
        $employeeId = is_array($employee) ? (int)($employee['id'] ?? 0) : (int)$employee;
        if ($employeeId <= 0) {
            return [];
        }
        return self::forEmployee($pdo, $employeeId);
    }
}
