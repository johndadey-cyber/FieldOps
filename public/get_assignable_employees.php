<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Employee.php';

header('Content-Type: application/json; charset=utf-8');

$pdo   = getPDO();
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

$candidates = [];
if ($jobId > 0) {
    // Uses helpers added in models/Employee.php
    $candidates = Employee::getEmployeesWithAvailabilityAndSkills($pdo, $jobId);
}

echo json_encode(
    ['ok' => true, 'job_id' => $jobId, 'candidates' => $candidates],
    JSON_UNESCAPED_SLASHES
);
