<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Job.php';

$pdo = getPDO();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$job = $id > 0 ? Job::getJobAndCustomerDetails($pdo, $id) : null;
$jobTypes = $id > 0 ? Job::getJobTypesForJob($pdo, $id) : [];
$jobTypeIds = array_map(static fn(array $r): string => (string)$r['id'], $jobTypes);

$mode = 'edit';

require __DIR__ . '/job_form.php';
