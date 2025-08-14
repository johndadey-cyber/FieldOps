<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/JobType.php';

$pdo = getPDO();

if (isset($_GET['job_id'])) {
    $jobId = (int)$_GET['job_id'];
    $job = Job::getJobAndCustomerDetails($pdo, $jobId);

    if ($job) {
        echo json_encode(['success' => true, 'job' => $job]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Job not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
}
?>
