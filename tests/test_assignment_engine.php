<?php
declare(strict_types=1);

/**
 * tests/test_assignment_engine.php
 *
 * Purpose:
 *   Replaces the old call to AssignmentEngine::getEligibleEmployees(...)
 *   with a direct call to Employee::getEmployeesWithAvailabilityAndSkills(...),
 *   using safe fallbacks for varying method signatures.
 *
 * Usage:
 *   php tests/test_assignment_engine.php [job_id]
 *
 * Behavior:
 *   - If job_id is provided, uses that job.
 *   - Otherwise picks the next upcoming job; if none, the most recently scheduled job.
 *   - Derives dayOfWeek, scheduledDate, scheduledTime, durationMinutes from the job.
 *   - Attempts several common signatures of getEmployeesWithAvailabilityAndSkills().
 *   - Prints eligible count and a compact listing.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/Employee.php';

function out(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }
function err(string $msg): void { fwrite(STDERR, "[ERR] " . $msg . PHP_EOL); }

/** @var PDO $pdo */
$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1) Resolve the target job
$jobIdArg = isset($argv[1]) ? (int)$argv[1] : 0;

$job = null;
if ($jobIdArg > 0 && method_exists('Job', 'getById')) {
    $job = Job::getById($pdo, $jobIdArg);
    if (!$job) {
        err("Job #{$jobIdArg} not found. Falling back to auto-select.");
    }
}

if (!$job) {
    // Try to find an upcoming job first
    $stmt = $pdo->query("
        SELECT id, description, scheduled_date, scheduled_time, duration_minutes
        FROM jobs
        WHERE TIMESTAMP(scheduled_date, scheduled_time) >= NOW()
        ORDER BY scheduled_date ASC, scheduled_time ASC
        LIMIT 1
    ");
    $job = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$job) {
    // Fallback: most recently scheduled job
    $stmt = $pdo->query("
        SELECT id, description, scheduled_date, scheduled_time, duration_minutes
        FROM jobs
        ORDER BY scheduled_date DESC, scheduled_time DESC
        LIMIT 1
    ");
    $job = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$job) {
    err("No jobs available to evaluate. Create a job first (job_form.php) and re-run.");
    exit(1);
}

$jobId           = (int)($job['id'] ?? 0);
$scheduledDate   = (string)($job['scheduled_date'] ?? '');
$scheduledTime   = (string)($job['scheduled_time'] ?? '');
$durationMinutes = (int)($job['duration_minutes'] ?? 60);
$ts              = strtotime($scheduledDate . ' ' . $scheduledTime);
$dayOfWeek       = $ts ? (int)date('w', $ts) : (int)date('w');

$requiredJobTypeIds = [];
if (method_exists('Job', 'getJobTypeIds')) {
    try {
        $requiredJobTypeIds = Job::getJobTypeIds($pdo, $jobId) ?? [];
        if (!is_array($requiredJobTypeIds)) {
            $requiredJobTypeIds = [];
        }
    } catch (Throwable $e) {
        // Non-fatal; proceed without type constraints
        $requiredJobTypeIds = [];
    }
}

out("Testing eligibility for Job #{$jobId} on {$scheduledDate} {$scheduledTime} (duration {$durationMinutes}m, DOW={$dayOfWeek})");

// 2) Verify target method exists
if (!method_exists('Employee', 'getEmployeesWithAvailabilityAndSkills')) {
    err("Employee::getEmployeesWithAvailabilityAndSkills(...) not found.");
    err("Please implement it or switch this test to your canonical eligibility method.");
    exit(1);
}

// 3) Try common signatures (defensive against minor interface drift)
$eligible = [];
$attempts = [
    // Full signature (pdo, jobId, dayOfWeek, scheduledDate, scheduledTime, durationMinutes, requiredJobTypeIds)
    fn() => Employee::getEmployeesWithAvailabilityAndSkills($pdo, $jobId, $dayOfWeek, $scheduledDate, $scheduledTime, $durationMinutes, $requiredJobTypeIds),
    // Without requiredJobTypeIds
    fn() => Employee::getEmployeesWithAvailabilityAndSkills($pdo, $jobId, $dayOfWeek, $scheduledDate, $scheduledTime, $durationMinutes),
    // Without durationMinutes + types
    fn() => Employee::getEmployeesWithAvailabilityAndSkills($pdo, $jobId, $dayOfWeek, $scheduledDate, $scheduledTime),
    // Minimal (pdo, jobId)
    fn() => Employee::getEmployeesWithAvailabilityAndSkills($pdo, $jobId),
];

$lastError = null;
foreach ($attempts as $i => $call) {
    try {
        $eligible = $call();
        if (is_array($eligible)) {
            break;
        }
        $eligible = [];
    } catch (ArgumentCountError $e) {
        $lastError = $e;
        continue;
    } catch (Throwable $e) {
        $lastError = $e;
        continue;
    }
}

if (!is_array($eligible)) {
    err("Failed to retrieve eligible employees. Last error: " . ($lastError ? $lastError->getMessage() : 'unknown'));
    exit(2);
}

// 4) Output results
$count = count($eligible);
out("Eligible employees: {$count}");

if ($count > 0) {
    $max = 15;
    $i   = 0;
    foreach ($eligible as $row) {
        $i++;
        $empId  = (int)($row['id'] ?? $row['employee_id'] ?? 0);
        $first  = (string)($row['first_name'] ?? '');
        $last   = (string)($row['last_name'] ?? '');
        $extra  = [];
        if (isset($row['conflict']) && $row['conflict']) $extra[] = 'conflict';
        if (isset($row['skill_match']) && !$row['skill_match']) $extra[] = 'no-skill';
        $extraStr = $extra ? ' [' . implode(',', $extra) . ']' : '';
        out(sprintf("- #%d %s %s%s", $empId, $first, $last, $extraStr));
        if ($i >= $max) {
            out(sprintf("... and %d more", $count - $max));
            break;
        }
    }
}

exit(0);
