<?php declare(strict_types=1);

/**
 * Utilities for querying employee availability and deriving schedule details.
 *
 * Status and summary information are computed by loading an employee's
 * availability blocks for a given day and subtracting any jobs scheduled for
 * that same period. The remaining free time determines both values:
 *
 * - "No Hours"         when no availability is defined.
 * - "Booked"           when jobs consume all available time.
 * - "Available"        when availability exists with no overlapping jobs.
 * - "Partially Booked" when some, but not all, availability remains.
 *
 * The summary is a human-readable list of the free time ranges or "Off" when
 * none are left.
 */
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
     * Return availability summaries for a list of employees.
     *
     * Each summary is a human friendly string like "Mon–Fri 8–5".
     *
     * @param list<int> $employeeIds
     * @return array<int,string> Map of employee id to summary string
     */
    public static function summaryForEmployees(PDO $pdo, array $employeeIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $employeeIds)));
        $ids = array_filter($ids, static fn(int $v): bool => $v > 0);
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT employee_id, day_of_week, "
             . "DATE_FORMAT(start_time,'%H:%i') AS start_time, "
             . "DATE_FORMAT(end_time,'%H:%i')   AS end_time "
             . "FROM employee_availability "
             . "WHERE employee_id IN ($placeholders)";

        $st = $pdo->prepare($sql);
        if ($st === false) {
            return [];
        }
        foreach ($ids as $i => $id) {
            $st->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $st->execute();

        /** @var list<array{employee_id:int, day_of_week:string, start_time:string, end_time:string}> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        $nameMap = [
            'sunday'    => 0, 'sun' => 0, '0' => 0, '1' => 0,
            'monday'    => 1, 'mon' => 1, '2' => 1,
            'tuesday'   => 2, 'tue' => 2, '3' => 2,
            'wednesday' => 3, 'wed' => 3, '4' => 3,
            'thursday'  => 4, 'thu' => 4, '5' => 4,
            'friday'    => 5, 'fri' => 5, '6' => 5,
            'saturday'  => 6, 'sat' => 6, '7' => 6,
        ];
        $shortNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

        foreach ($rows as $r) {
            $eid = (int)$r['employee_id'];
            $dow = strtolower((string)$r['day_of_week']);
            $day = $nameMap[$dow] ?? null;
            if ($day === null && is_numeric($dow)) {
                $int = (int)$dow;
                if ($int >= 0 && $int <= 6) {
                    $day = $int;
                } elseif ($int >= 1 && $int <= 7) {
                    $day = $int - 1;
                }
            }
            if ($day === null) {
                continue;
            }
            $pattern = $r['start_time'] . '|' . $r['end_time'];
            $map[$eid][$pattern][] = $day;
        }

        $out = [];
        foreach ($map as $eid => $patterns) {
            $parts = [];
            foreach ($patterns as $pattern => $days) {
                sort($days);
                $uniqueDays = array_values(array_unique($days));

                [$startTime, $endTime] = explode('|', $pattern);
                $ranges = [];
                $rangeStart = $uniqueDays[0];
                $prev = $uniqueDays[0];
                for ($i = 1, $cnt = count($uniqueDays); $i < $cnt; $i++) {
                    $d = $uniqueDays[$i];
                    if ($d === $prev + 1) {
                        $prev = $d;
                        continue;
                    }
                    $ranges[] = [$rangeStart, $prev];
                    $rangeStart = $prev = $d;
                }
                $ranges[] = [$rangeStart, $prev];

                foreach ($ranges as [$startDay, $endDay]) {
                    $dayPart = $shortNames[$startDay];
                    if ($startDay !== $endDay) {
                        $dayPart .= '–' . $shortNames[$endDay];
                    }
                    $timePart = self::formatTime($startTime) . '–' . self::formatTime($endTime);
                    $parts[] = $dayPart . ' ' . $timePart;
                }
            }
            $out[(int)$eid] = implode(', ', $parts);
        }

        return $out;
    }

    /**
     * Return availability summary for each employee on a specific date.
     *
     * The summary lists remaining free time blocks after subtracting any jobs
     * scheduled on the given date. When an employee has no remaining free time,
     * including when no availability is defined, "Off" is returned.
     *
     * @param list<int> $employeeIds
     * @return array<int,string> Map of employee id to summary string
     */
    public static function summaryForEmployeesOnDate(PDO $pdo, array $employeeIds, string $date): array
    {
        $map = self::statusForEmployeesOnDate($pdo, $employeeIds, $date);
        $out = [];
        foreach ($map as $eid => $info) {
            $out[$eid] = $info['summary'];
        }
        return $out;
    }

    /**
     * Return availability status and summary for each employee on a specific date.
     *
     * For each employee, availability blocks and assigned jobs on the given date
     * are loaded to determine both a status string and remaining free time
     * summary. The status values are:
     *
     *  - "No Hours"         when the employee has no availability defined.
     *  - "Available"        when availability exists and no jobs overlap.
     *  - "Booked"           when jobs fully cover the available time.
     *  - "Partially Booked" when jobs cover only part of the availability.
     *
     * The summary lists remaining free intervals in a human readable form or
     * "Off" when none are left.
     *
     * @param list<int> $employeeIds
     * @return array<int,array{status:string,summary:string}> Map of employee id to status/summary
     */
    public static function statusForEmployeesOnDate(PDO $pdo, array $employeeIds, string $date): array
    {
        $ids = array_values(array_unique(array_map('intval', $employeeIds)));
        $ids = array_filter($ids, static fn(int $v): bool => $v > 0);
        if ($ids === []) {
            return [];
        }

        $dayIndex = (int)date('w', strtotime($date));

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT employee_id, day_of_week, "
             . "DATE_FORMAT(start_time,'%H:%i') AS start_time, "
             . "DATE_FORMAT(end_time,'%H:%i')   AS end_time "
             . "FROM employee_availability "
             . "WHERE employee_id IN ($placeholders)";

        $st = $pdo->prepare($sql);
        if ($st === false) {
            return [];
        }
        foreach ($ids as $i => $id) {
            $st->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $st->execute();

        /** @var list<array{employee_id:int, day_of_week:string, start_time:string, end_time:string}> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $nameMap = [
            'sunday'    => 0, 'sun' => 0, '0' => 0, '1' => 0,
            'monday'    => 1, 'mon' => 1, '2' => 1,
            'tuesday'   => 2, 'tue' => 2, '3' => 2,
            'wednesday' => 3, 'wed' => 3, '4' => 3,
            'thursday'  => 4, 'thu' => 4, '5' => 4,
            'friday'    => 5, 'fri' => 5, '6' => 5,
            'saturday'  => 6, 'sat' => 6, '7' => 6,
        ];

        $avail = [];
        foreach ($rows as $r) {
            $eid = (int)$r['employee_id'];
            $dow = strtolower((string)$r['day_of_week']);
            $day = $nameMap[$dow] ?? null;
            if ($day === null && is_numeric($dow)) {
                $int = (int)$dow;
                if ($int >= 0 && $int <= 6) {
                    $day = $int;
                } elseif ($int >= 1 && $int <= 7) {
                    $day = $int - 1;
                }
            }
            if ($day === null || $day !== $dayIndex) {
                continue;
            }
            $avail[$eid][] = [self::toMinutes($r['start_time']), self::toMinutes($r['end_time'])];
        }

        $placeholders2 = implode(',', array_fill(0, count($ids), '?'));
        $sql2 = "SELECT a.employee_id, DATE_FORMAT(j.scheduled_time,'%H:%i') AS start_time, j.duration_minutes "
              . "FROM job_employee_assignment a JOIN jobs j ON j.id = a.job_id "
              . "WHERE a.employee_id IN ($placeholders2) AND j.scheduled_date = ?";

        $st2 = $pdo->prepare($sql2);
        if ($st2 === false) {
            return [];
        }
        $pos = 1;
        foreach ($ids as $id) {
            $st2->bindValue($pos++, $id, PDO::PARAM_INT);
        }
        $st2->bindValue($pos, $date, PDO::PARAM_STR);
        $st2->execute();

        /** @var list<array{employee_id:int, start_time:string, duration_minutes:int}> $jobRows */
        $jobRows = $st2->fetchAll(PDO::FETCH_ASSOC);
        $jobs = [];
        foreach ($jobRows as $r) {
            $eid = (int)$r['employee_id'];
            $start = self::toMinutes($r['start_time']);
            $end = $start + (int)$r['duration_minutes'];
            $jobs[$eid][] = [$start, $end];
        }

        $out = [];
        foreach ($ids as $eid) {
            $blocks = $avail[$eid] ?? null;
            if ($blocks === null) {
                $out[$eid] = ['status' => 'No Hours', 'summary' => 'Off'];
                continue;
            }

            $free = $blocks;
            $jobList = $jobs[$eid] ?? [];
            foreach ($jobList as [$js, $je]) {
                $next = [];
                foreach ($free as [$fs, $fe]) {
                    if ($je <= $fs || $js >= $fe) {
                        $next[] = [$fs, $fe];
                        continue;
                    }
                    if ($js > $fs) {
                        $next[] = [$fs, min($js, $fe)];
                    }
                    if ($je < $fe) {
                        $next[] = [max($je, $fs), $fe];
                    }
                }
                $free = $next;
                if ($free === []) {
                    break;
                }
            }

            if ($free === []) {
                $out[$eid] = ['status' => 'Booked', 'summary' => 'Off'];
                continue;
            }

            usort($free, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
            $parts = [];
            foreach ($free as [$s, $e]) {
                $parts[] = self::formatTime(sprintf('%02d:%02d', intdiv($s, 60), $s % 60))
                    . '–'
                    . self::formatTime(sprintf('%02d:%02d', intdiv($e, 60), $e % 60));
            }
            $summary = implode(', ', $parts);

            if ($jobList === []) {
                $status = 'Available';
            } elseif ($free === $blocks) {
                $status = 'Available';
            } else {
                $status = 'Partially Booked';
            }

            $out[$eid] = ['status' => $status, 'summary' => $summary];
        }

        return $out;
    }

    private static function formatTime(string $t): string
    {
        $t = substr($t, 0, 5); // HH:MM
        [$h, $m] = array_map('intval', explode(':', $t));
        $h12 = $h % 12;
        if ($h12 === 0) {
            $h12 = 12;
        }
        if ($m === 0) {
            return (string)$h12;
        }
        return $h12 . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
    }

    private static function toMinutes(string $t): int
    {
        $t = substr($t, 0, 5);
        [$h, $m] = array_map('intval', explode(':', $t));
        return $h * 60 + $m;
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
