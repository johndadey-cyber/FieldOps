<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/Availability.php';

/** HTML escape */
function s(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
/** Sticky helper */
function sticky(string $name, ?string $default = null): string {
    $v = $_POST[$name] ?? $_GET[$name] ?? $default ?? '';
    return is_string($v) ? $v : (string)$v;
}
/** Maps API key */
function getMapsApiKey(): string {
    if (getenv('MAPS_API_KEY')) return (string)getenv('MAPS_API_KEY');
    if (defined('MAPS_API_KEY'))  return (string)MAPS_API_KEY;
    return '';
}

$pdo = getPDO();
$jobId = (int)($_GET['job_id'] ?? 0);
if ($jobId <= 0) { http_response_code(400); echo "Missing job_id"; exit; }

$assignedIds = Employee::getAssignedEmployeeIds($pdo, $jobId);

$job = Job::getJobAndCustomerDetails($pdo, $jobId);
if (!$job) { http_response_code(404); echo "Job not found"; exit; }

$avail = Availability::getAvailabilityForEmployeeAndJob($pdo, [
    'id' => $jobId,
    'scheduled_date'   => (string)$job['scheduled_date'],
    'scheduled_time'   => (string)$job['scheduled_time'],
    'duration_minutes' => (int)($job['duration_minutes'] ?? 0),
]);

$employees = Employee::getAll($pdo);

// Map availability by employee_id
$availMap = [];
foreach ($avail as $a) { $availMap[(int)$a['employee_id']] = $a; }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Assign Employees</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h3>Assign Employees</h3>

<form id="assignForm" method="post" action="assignment_process.php">
  <input type="hidden" name="job_id" value="<?= (int)$jobId ?>">
  <ul>
    <?php foreach ($employees as $e):
      $eid = (int)$e['id'];
      $checked = in_array($eid, $assignedIds, true) ? 'checked' : '';
      $st = $availMap[$eid]['status'] ?? 'unavailable';
    ?>
      <li>
        <label>
          <input type="checkbox" name="employee_ids[]" value="<?= $eid ?>" <?= $checked ?>>
          <?= s($e['first_name'] . ' ' . $e['last_name']) ?> — <?= s($st) ?>
        </label>
      </li>
    <?php endforeach; ?>
  </ul>
  <button type="submit">Save</button>
</form>
</body>
</html>
