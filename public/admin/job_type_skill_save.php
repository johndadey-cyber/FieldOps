<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../_auth.php';
require_role('admin');
require_once __DIR__ . '/../_csrf.php';
require_csrf();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/JobTypeSkill.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$pdo       = getPDO();
$jobTypeId = isset($_POST['job_type_id']) ? (int)$_POST['job_type_id'] : 0;
$selected  = isset($_POST['skills']) && is_array($_POST['skills'])
    ? array_map('intval', $_POST['skills'])
    : [];

$current = JobTypeSkill::listForJobType($pdo, $jobTypeId);
$toAdd   = array_diff($selected, $current);
$toDel   = array_diff($current, $selected);
foreach ($toAdd as $sid) {
    JobTypeSkill::attach($pdo, $jobTypeId, (int)$sid);
}
foreach ($toDel as $sid) {
    JobTypeSkill::detach($pdo, $jobTypeId, (int)$sid);
}

header('Location: /admin/job_type_skill_map.php?job_type_id=' . $jobTypeId . '&saved=1');
exit;
