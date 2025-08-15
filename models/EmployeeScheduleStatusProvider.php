<?php
declare(strict_types=1);

// /models/EmployeeScheduleStatusProvider.php

final class EmployeeScheduleStatusProvider
{
    /**
     * Determine schedule status for a set of employees on a given date.
     *
     * @param PDO $pdo
     * @param array<int> $employeeIds
     * @param string $date Y-m-d
     * @return array<int,string> map of employee_id => status
     */
    public static function forDate(PDO $pdo, array $employeeIds, string $date): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $sql = "SELECT a.employee_id, j.scheduled_time, j.duration_minutes
                FROM job_employee_assignment a
                JOIN jobs j ON j.id = a.job_id
                WHERE j.scheduled_date = ?
                  AND j.scheduled_time IS NOT NULL
                  AND j.duration_minutes IS NOT NULL
                  AND a.employee_id IN ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $params = array_merge([$date], $employeeIds);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $r) {
            $eid = (int)$r['employee_id'];
            $start = strtotime($date . ' ' . (string)$r['scheduled_time']);
            $end   = $start + ((int)$r['duration_minutes'] * 60);
            $grouped[$eid][] = [$start, $end];
        }

        $statuses = [];
        foreach ($employeeIds as $eid) {
            $intervals = $grouped[$eid] ?? [];
            if ($intervals === []) {
                $statuses[$eid] = 'Available';
                continue;
            }
            usort($intervals, static fn($a, $b) => $a[0] <=> $b[0]);
            $overlap = false;
            $lastEnd = null;
            foreach ($intervals as $int) {
                [$s, $e] = $int;
                if ($lastEnd !== null && $s < $lastEnd) {
                    $overlap = true;
                    break;
                }
                $lastEnd = max($lastEnd ?? 0, $e);
            }
            $statuses[$eid] = $overlap ? 'Partially Booked' : 'Booked';
        }

        return $statuses;
    }
}
