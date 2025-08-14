<?php declare(strict_types=1);

/**
 * AvailabilityOverride model provides simple CRUD helpers for
 * date-specific availability overrides such as vacation or special
 * projects. Times are stored as TIME fields in UTC.
 */
final class AvailabilityOverride
{
    /**
     * List overrides for an employee. Optionally filter by date.
     * @return list<array<string,mixed>>
     */
    public static function listForEmployee(PDO $pdo, int $employeeId, ?string $date = null): array
    {
        $sql = "SELECT id, employee_id, date, status, start_time, end_time, reason
                FROM employee_availability_overrides
                WHERE employee_id = :eid";
        $params = [':eid' => $employeeId];
        if ($date !== null) {
            $sql .= " AND date = :d";
            $params[':d'] = $date;
        }
        $sql .= " ORDER BY date, start_time";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        /** @var list<array<string,mixed>> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * Insert or update an override. Returns the id of the row.
     * @param array<string,mixed> $data
     */
    public static function save(PDO $pdo, array $data): int
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $sql = $id > 0
            ? "UPDATE employee_availability_overrides
                 SET employee_id=:eid, date=:d, status=:s, start_time=:st, end_time=:et, reason=:r
                 WHERE id=:id"
            : "INSERT INTO employee_availability_overrides (employee_id, date, status, start_time, end_time, reason)
                 VALUES (:eid,:d,:s,:st,:et,:r)";

        $st = $pdo->prepare($sql);
        $st->execute([
            ':eid' => (int)$data['employee_id'],
            ':d'   => (string)$data['date'],
            ':s'   => (string)$data['status'],
            ':st'  => $data['start_time'] ?? null,
            ':et'  => $data['end_time'] ?? null,
            ':r'   => $data['reason'] ?? null,
            ':id'  => $id > 0 ? $id : null,
        ]);

        return $id > 0 ? $id : (int)$pdo->lastInsertId();
    }

    /** Delete an override by id. */
    public static function delete(PDO $pdo, int $id): bool
    {
        $st = $pdo->prepare("DELETE FROM employee_availability_overrides WHERE id = :id");
        return $st->execute([':id' => $id]);
    }
}

