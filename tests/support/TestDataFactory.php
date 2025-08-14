<?php
declare(strict_types=1);

final class TestDataFactory
{
    public static function createCustomer(PDO $pdo, string $first = 'Test', string $last = 'Customer'): int
    {
        $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, phone) VALUES (:fn,:ln,:ph)");
        $stmt->execute([':fn' => $first, ':ln' => $last, ':ph' => '555-0100']);
        return (int)$pdo->lastInsertId();
    }

    public static function createPerson(PDO $pdo, string $first, string $last): int
    {
        $stmt = $pdo->prepare("INSERT INTO people (first_name, last_name) VALUES (:fn,:ln)");
        $stmt->execute([':fn' => $first, ':ln' => $last]);
        return (int)$pdo->lastInsertId();
    }

    public static function createEmployee(PDO $pdo, string $first = 'Ellen', string $last = 'Eligible'): int
    {
        $personId = self::createPerson($pdo, $first, $last);
        $stmt = $pdo->prepare(
            "INSERT INTO employees (person_id, employment_type, hire_date, status, is_active) " .
            "VALUES (:pid, 'Full-Time', CURRENT_DATE, 'Active', 1)"
        );
        $stmt->execute([':pid' => $personId]);
        return (int)$pdo->lastInsertId();
    }

    public static function setAvailability(PDO $pdo, int $employeeId, int $dayOfWeek, string $start = '09:00:00', string $end = '17:00:00'): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time)
            VALUES (:e,:d,:s,:t)
        ");
        $stmt->execute([':e' => $employeeId, ':d' => $dayOfWeek, ':s' => $start, ':t' => $end]);
    }

    public static function createJob(PDO $pdo, int $customerId, string $desc, string $date, string $time, int $duration = 60, string $status = 'scheduled'): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO jobs (customer_id, description, status, scheduled_date, scheduled_time, duration_minutes)
            VALUES (:c,:d,:s,:dt,:tm,:dur)
        ");
        $stmt->execute([
            ':c' => $customerId,
            ':d' => $desc,
            ':s' => $status,
            ':dt'=> $date,
            ':tm'=> $time,
            ':dur'=> $duration,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function createOverride(PDO $pdo, int $employeeId, string $date, string $status = 'UNAVAILABLE', ?string $start = null, ?string $end = null, string $reason = ''): int
    {
        $stmt = $pdo->prepare(
            "INSERT INTO employee_availability_overrides (employee_id, date, status, start_time, end_time, reason)
             VALUES (:e,:d,:s,:st,:et,:r)"
        );
        $stmt->execute([
            ':e'  => $employeeId,
            ':d'  => $date,
            ':s'  => $status,
            ':st' => $start,
            ':et' => $end,
            ':r'  => $reason,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function hasAssignment(PDO $pdo, int $jobId, int $employeeId): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM job_employee_assignment WHERE job_id=:j AND employee_id=:e LIMIT 1");
        $stmt->execute([':j' => $jobId, ':e' => $employeeId]);
        return (bool)$stmt->fetchColumn();
    }
}
