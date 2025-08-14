<?php
declare(strict_types=1);

/**
 * tests/test_no_conflict_eligibility.php
 *
 * Purpose:
 *   Verifies that Employee::getEmployeesWithAvailabilityAndSkills(...) does NOT
 *   report a conflict when an employee has availability for a job and no overlapping assignments.
 *
 * Behavior:
 *   - Runs entirely inside a DB transaction and rolls back (no persistent changes).
 *   - Creates a customer, a job for tomorrow at 10:00 for 60 minutes,
 *     a new employee with availability 09:00–12:00 for that day.
 *   - If your system requires job types/skills, it will create/link one as needed.
 *   - Calls the canonical eligibility method and asserts:
 *        1) The new employee is present in results
 *        2) The employee's "conflict" flag (if present) is falsey
 *
 * Usage:
 *   php tests/test_no_conflict_eligibility.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/Employee.php';

function out(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }
function err(string $msg): void { fwrite(STDERR, "[ERR] " . $msg . PHP_EOL); }

/** @var PDO $pdo */
$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->beginTransaction();

try {
    // --- 1) Seed minimal data ---
    // 1a) Customer
    $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, phone) VALUES (:fn,:ln,:ph)");
    $stmt->execute([':fn' => 'Test', ':ln' => 'NoConflict', ':ph' => '555-0100']);
    $customerId = (int)$pdo->lastInsertId();

    // 1b) Person + Employee
    $stmt = $pdo->prepare("INSERT INTO people (first_name, last_name) VALUES (:fn,:ln)");
    $stmt->execute([':fn' => 'Ellen', ':ln' => 'Eligible']);
    $personId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO employees (person_id, is_active) VALUES (:pid, 1)");
    $stmt->execute([':pid' => $personId]);
    $employeeId = (int)$pdo->lastInsertId();

    // 1c) Job — tomorrow @ 10:00, 60 minutes
    $tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $jobTime  = '10:00:00';
    $duration = 60;

    $stmt = $pdo->prepare("
        INSERT INTO jobs (customer_id, description, status, scheduled_date, scheduled_time, duration_minutes)
        VALUES (:cid, :desc, :status, :sd, :st, :dur)
    ");
    $stmt->execute([
        ':cid'   => $customerId,
        ':desc'  => 'No-conflict eligibility test job',
        ':status'=> 'Unassigned',
        ':sd'    => $tomorrow,
        ':st'    => $jobTime,
        ':dur'   => $duration,
    ]);
    $jobId = (int)$pdo->lastInsertId();

    // 1d) Availability for employee on that weekday: 09:00–12:00 (covers the job, no conflict)
    $dayOfWeek = (int)date('w', strtotime($tomorrow)); // 0=Sun..6=Sat

    $stmt = $pdo->prepare("
        INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time)
        VALUES (:eid, :dow, :start, :end)
    ");
    $stmt->execute([
        ':eid'   => $employeeId,
        ':dow'   => $dayOfWeek,
        ':start' => '09:00:00',
        ':end'   => '12:00:00',
    ]);

    // 1e) If your system enforces job types/skills, set one up and link both
    $requiredJobTypeIds = [];
    $needTypes = false;

    // Discover if job types are in play via presence of tables
    $hasJobTypes = (bool)$pdo->query("SHOW TABLES LIKE 'job_types'")->fetchColumn();
    $hasEmployeeSkills = (bool)$pdo->query("SHOW TABLES LIKE 'employee_skills'")->fetchColumn();
    $hasJobJobTypes = (bool)$pdo->query("SHOW TABLES LIKE 'job_job_types'")->fetchColumn();

    if ($hasJobTypes && $hasEmployeeSkills && $hasJobJobTypes) {
        $needTypes = true;
        // Create a job type if none exists
        $typeId = (int)($pdo->query("SELECT id FROM job_types LIMIT 1")->fetchColumn() ?: 0);
        if ($typeId === 0) {
            $pdo->exec("INSERT INTO job_types (name) VALUES ('General')");
            $typeId = (int)$pdo->lastInsertId();
        }

        // Link job to type
        $stmt = $pdo->prepare("INSERT INTO job_job_types (job_id, job_type_id) VALUES (:j,:t)");
        $stmt->execute([':j' => $jobId, ':t' => $typeId]);

        // Grant employee the skill
        $stmt = $pdo->prepare("INSERT INTO employee_skills (employee_id, job_type_id) VALUES (:e,:t)");
        $stmt->execute([':e' => $employeeId, ':t' => $typeId]);

        $requiredJobTypeIds = [$typeId];
    }

    // --- 2) Call canonical eligibility method ---
    if (!method_exists('Employee', 'getEmployeesWithAvailabilityAndSkills')) {
        throw new RuntimeException('Employee::getEmployeesWithAvailabilityAndSkills(...) not found.');
    }

    $scheduledDate   = $tomorrow;
    $scheduledTime   = $jobTime;
    $durationMinutes = $duration;

    // Try common signatures defensively
    $attempts = [
        fn() => Employee::getEmployeesWithAvailabilityAndSkills($pdo, $jobId, $dayOfWeek, $scheduledDate, $scheduledTime, $durationMinutes, $requiredJobTypeIds),
        fn() => Employee::getEmployeesWithAvailabilityAndSkills($pdo, $jobId, $dayOfWeek, $scheduledDate, $scheduledTime, $durationMinutes),
        fn() => Employee::getEmployeesWithAvailabilityAndSkills($pdo, $jobId, $dayOfWeek, $scheduledDate, $scheduledTime),
        fn() => Employee::getEmployeesWithAvailabilityAndSkills($pdo, $jobId),
    ];

    $eligible = null;
    $lastErr = null;
    foreach ($attempts as $call) {
        try {
            $eligible = $call();
            if (is_array($eligible)) break;
        } catch (ArgumentCountError $e) { $lastErr = $e; continue; }
        catch (Throwable $e) { $lastErr = $e; continue; }
    }

    if (!is_array($eligible)) {
        throw new RuntimeException('Failed to get eligibility results: ' . ($lastErr ? $lastErr->getMessage() : 'unknown'));
    }

    // --- 3) Assertions ---
    // Find our test employee in results
    $found = null;
    foreach ($eligible as $row) {
        $rid = (int)($row['id'] ?? $row['employee_id'] ?? 0);
        if ($rid === $employeeId) {
            $found = $row;
            break;
        }
    }

    if (!$found) {
        throw new RuntimeException("Test employee #{$employeeId} not found in eligibility results.");
    }

    // Assert: no conflict flag set (or it's falsey)
    $conflict = $found['conflict'] ?? false;
    if ($conflict) {
        throw new RuntimeException("Expected no conflict for employee #{$employeeId}, but conflict=true was returned.");
    }

    out("OK: No conflict reported for employee #{$employeeId} (job #{$jobId}, {$scheduledDate} {$scheduledTime}).");

    // Roll back to leave DB clean
    $pdo->rollBack();
    exit(0);

} catch (Throwable $e) {
    // Ensure rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err($e->getMessage());
    exit(1);
}
